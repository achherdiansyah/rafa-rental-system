<?php

namespace Tests\Feature\Integration;

use App\Actions\Booking\CancelBookingAction;
use App\Actions\Payment\VerifyPaymentAction;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Enums\RefundSource;
use App\Enums\RefundStatus;
use App\Exceptions\InvalidStateTransitionException;
use App\Models\Booking;
use App\Models\Invoice;
use App\Models\NotificationDelivery;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\User;
use App\Services\Finance\CustomerOutstandingService;
use App\Services\Refund\RefundLifecycleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RefundOutstandingIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Log::spy();
        Storage::fake('local');
        Storage::fake('public');
    }

    /**
     * Build booking + invoice + approved payment.
     *
     * @return array{0: User, 1: Booking, 2: Invoice}
     */
    private function paidInvoice(float $grand, float $approved, float $rejected = 0.0): array
    {
        $user = User::factory()->create(['phone_number' => '081234567890']);
        $booking = Booking::factory()->create(['user_id' => $user->id, 'status' => 'CONFIRMED']);
        $invoice = Invoice::factory()->create([
            'booking_id' => $booking->id,
            'invoice_type' => 'DAILY_WORK',
            'status' => InvoiceStatus::PAID->value,
            'grand_total' => $grand,
            'paid_amount' => $approved,
        ]);
        Payment::factory()->create(['invoice_id' => $invoice->id, 'amount' => $approved, 'status' => PaymentStatus::APPROVED]);
        if ($rejected > 0) {
            Payment::factory()->create(['invoice_id' => $invoice->id, 'amount' => $rejected, 'status' => PaymentStatus::REJECTED]);
        }

        return [$user, $booking, $invoice];
    }

    public function test_cancellation_after_payment_registers_single_refund(): void
    {
        [$user, $booking, $invoice] = $this->paidInvoice(800000, 800000);

        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);
        $booking->update(['status' => 'APPROVED', 'payment_met_at' => now()->subMinutes(5)]);
        app(CancelBookingAction::class)->execute($admin, $booking, 'Pengembalian dana via policy perusahaan.');

        $refunds = Refund::where('invoice_id', $invoice->id)->get();
        $this->assertCount(1, $refunds, 'Tidak boleh ada refund duplikat.');
        $this->assertEquals(RefundSource::CANCELLATION->value, $refunds->first()->source->value);
        $this->assertEquals(RefundStatus::PENDING->value, $refunds->first()->status->value);
        $this->assertEqualsWithDelta(800000.00, (float) $refunds->first()->amount, 0.01);

        // Financial history immutable; booking unchanged rows intact
        $this->assertDatabaseCount('payments', 1);
        $this->assertEquals(800000.00, (float) $invoice->fresh()->paid_amount);
    }

    public function test_overpayment_registers_single_refund_and_settles_to_completed(): void
    {
        [$user, , $invoice] = $this->paidInvoice(800000, 0);

        // Overpay via verification
        $invoice->update(['status' => InvoiceStatus::ISSUED->value, 'overpayment_amount' => 0]);
        $payment = Payment::factory()->create([
            'invoice_id' => $invoice->id,
            'amount' => 1000000,
            'status' => PaymentStatus::SUBMITTED,
        ]);
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);
        app(VerifyPaymentAction::class)->approve($admin, $payment);

        $this->assertEquals(InvoiceStatus::OVERPAID->value, $invoice->fresh()->status->value);
        $refund = Refund::where('invoice_id', $invoice->id)->where('source', RefundSource::OVERPAYMENT->value)->first();
        $this->assertNotNull($refund);
        $this->assertEqualsWithDelta(200000.00, (float) $refund->amount, 0.01);

        // Settle: PENDING -> APPROVED (owner) -> PROCESSING (admin) -> COMPLETED (proof)
        $owner = User::factory()->owner()->create();
        $service = app(RefundLifecycleService::class);
        $r = $service->approve($owner, $refund, []);
        $this->assertEquals(RefundStatus::APPROVED->value, $r->status->value);

        $r = $service->process($admin, $r->fresh(), ['customer_bank_info' => 'BCA 123 a/n Customer', 'transfer_reference' => 'TRF-R']);
        $this->assertEquals(RefundStatus::PROCESSING->value, $r->status->value);

        $r = $service->complete($admin, $r->fresh(), [], UploadedFile::fake()->image('resi.png', 600, 400));
        $this->assertEquals(RefundStatus::COMPLETED->value, $r->status->value);

        // Single refund only (idempotent source registration)
        $this->assertCount(1, Refund::where('invoice_id', $invoice->id)->get());
        $this->assertEquals(800000.00, (float) $invoice->fresh()->paid_amount, 'paid_amount tidak berubah off-book.');
    }

    public function test_refund_fail_path_preserves_history(): void
    {
        [$user, , $invoice] = $this->paidInvoice(500000, 500000);
        $refund = Refund::factory()->create([
            'invoice_id' => $invoice->id,
            'source' => RefundSource::CANCELLATION,
            'status' => RefundStatus::PENDING,
            'amount' => 500000,
        ]);
        $admin = User::factory()->admin()->create();

        $failed = app(RefundLifecycleService::class)->fail($admin, $refund, 'Rekening tujuan tidak valid.');
        $this->assertEquals(RefundStatus::FAILED->value, $failed->status->value);
        $this->assertEquals('Rekening tujuan tidak valid.', $failed->failure_reason);

        // History preserved: row stays, financial data untouched
        $this->assertDatabaseHas('refunds', ['id' => $refund->id, 'status' => RefundStatus::FAILED->value]);
        $this->assertEquals(500000.00, (float) $invoice->fresh()->paid_amount);
    }

    public function test_outstanding_per_customer_and_deadline_invariance(): void
    {
        // Customer with open invoices; approved basis; PAID/OVERPAID excluded
        $user = User::factory()->create();
        $b1 = Booking::factory()->create(['user_id' => $user->id, 'status' => 'CONFIRMED']);
        Invoice::factory()->create(['booking_id' => $b1->id, 'status' => InvoiceStatus::UNPAID->value, 'grand_total' => 500000, 'paid_amount' => 100000]);
        Payment::factory()->create(['invoice_id' => Invoice::where('booking_id', $b1->id)->value('id'), 'amount' => 100000, 'status' => PaymentStatus::APPROVED]);
        Payment::factory()->create(['invoice_id' => Invoice::where('booking_id', $b1->id)->value('id'), 'amount' => 50000, 'status' => PaymentStatus::REJECTED]);
        $b2 = Booking::factory()->create(['user_id' => $user->id, 'status' => 'CONFIRMED']);
        Invoice::factory()->create(['booking_id' => $b2->id, 'status' => InvoiceStatus::OVERDUE->value, 'grand_total' => 200000, 'paid_amount' => 0]);
        $b3 = Booking::factory()->create(['user_id' => $user->id, 'status' => 'CONFIRMED']);
        Invoice::factory()->create(['booking_id' => $b3->id, 'status' => InvoiceStatus::PARTIALLY_PAID->value, 'grand_total' => 300000, 'paid_amount' => 300000]);
        Payment::factory()->create(['invoice_id' => Invoice::where('booking_id', $b3->id)->value('id'), 'amount' => 300000, 'status' => PaymentStatus::APPROVED]);

        $outstanding = app(CustomerOutstandingService::class)->forCustomer((int) $user->id);
        $this->assertEqualsWithDelta(600000.00, $outstanding['total_outstanding'], 0.01); // 400k + 200k + 0
        $this->assertEquals(3, $outstanding['open_invoice_count']);
        $this->assertEquals(1, $outstanding['overdue_invoice_count']);
        $this->assertFalse($outstanding['eligible']);

        // UNPAID balance excludes REJECTED payment (approved basis)
        $unpaid = collect($outstanding['invoices'])->firstWhere('status', 'UNPAID');
        $this->assertEqualsWithDelta(100000.00, $unpaid['paid_amount'], 0.01);
        $this->assertEqualsWithDelta(400000.00, $unpaid['balance_amount'], 0.01);

        // Payment rejection does NOT change invoice deadline
        $issuedAt = now()->subHour();
        $issued = Invoice::factory()->create([
            'booking_id' => $b1->id,
            'status' => InvoiceStatus::ISSUED->value,
            'issued_at' => $issuedAt,
            'due_at' => $issuedAt->copy()->addHours(24),
            'grand_total' => 100000,
        ]);
        $pendingPayment = Payment::factory()->create(['invoice_id' => $issued->id, 'amount' => 100000, 'status' => PaymentStatus::SUBMITTED]);
        $admin = User::factory()->admin()->create();
        $dueBefore = $issued->fresh()->due_at->timestamp;
        Sanctum::actingAs($admin);
        app(VerifyPaymentAction::class)->reject($admin, $pendingPayment, 'Bukti tidak sesuai mutasi.');
        $this->assertEquals($dueBefore, $issued->fresh()->due_at->timestamp, 'Reject tidak boleh mengubah deadline.');
        $this->assertNotNull(Payment::find($pendingPayment->id), 'Payment ditolak tetap tersimpan.');
    }

    public function test_notifications_no_duplicates_race_and_authorization(): void
    {
        $user = User::factory()->create(['phone_number' => '081234567890']);
        $booking = Booking::factory()->create(['user_id' => $user->id, 'status' => 'CONFIRMED']);
        $issuedAt = now()->subMinutes(30);
        $invoice = Invoice::factory()->create([
            'booking_id' => $booking->id,
            'status' => InvoiceStatus::ISSUED->value,
            'issued_at' => $issuedAt,
            'due_at' => $issuedAt->copy()->addHours(24),
            'grand_total' => 800000,
            'paid_amount' => 0,
        ]);

        // Payment approval -> single in-app + single WA delivery
        $payment = Payment::factory()->create([
            'invoice_id' => $invoice->id,
            'amount' => 1000000,
            'status' => PaymentStatus::SUBMITTED,
        ]);
        $admin = User::factory()->admin()->create();
        $owner = User::factory()->owner()->create();
        Sanctum::actingAs($admin);
        app(VerifyPaymentAction::class)->approve($admin, $payment);

        $this->assertCount(1, $user->notifications()->where('data->event', 'PAYMENT_APPROVED')->get());
        $this->assertCount(1, NotificationDelivery::where('event', 'PAYMENT_APPROVED')->get());

        // Race/idempotency: second approval attempt is rejected without side effects
        $before = $user->notifications()->count();
        try {
            app(VerifyPaymentAction::class)->approve($admin, $payment);
            $this->fail('Approval kedua seharusnya gagal.');
        } catch (InvalidStateTransitionException $e) {
            $this->assertTrue(true);
        }
        $this->assertEquals($before, $user->notifications()->count(), 'Tidak boleh ada notifikasi duplikat.');
        $this->assertEqualsWithDelta(800000.00, (float) $invoice->fresh()->paid_amount, 0.01);
        $this->assertEqualsWithDelta(200000.00, (float) $invoice->fresh()->overpayment_amount, 0.01);
        $this->assertCount(1, Refund::where('invoice_id', $invoice->id)->get(), 'Satu refund per overpayment (idempotent).');

        // Authorization matrix
        $stranger = User::factory()->create();
        $refund = Refund::where('invoice_id', $invoice->id)->first();

        Sanctum::actingAs($stranger);
        $this->getJson("/api/v1/refunds/{$refund->id}")->assertStatus(403);
        $this->getJson('/api/v1/notifications/deliveries')->assertStatus(403);

        Sanctum::actingAs($admin);
        $this->postJson("/api/v1/refunds/{$refund->id}/approve", [])->assertStatus(403); // admin tidak bisa approve

        Sanctum::actingAs($owner);
        $this->postJson("/api/v1/refunds/{$refund->id}/process", ['customer_bank_info' => 'BCA x'])->assertStatus(403); // owner tidak bisa process
        $this->postJson("/api/v1/refunds/{$refund->id}/approve", ['approval_reason' => 'Ok.'])->assertOk();

        // Audit chain present (payment) + refund-pending notified to owner once (in-app)
        Log::shouldHaveReceived('info')
            ->withArgs(fn ($msg) => is_string($msg) && str_contains($msg, '[PAYMENT_APPROVED]'));
        $this->assertEquals(1, $owner->notifications()->where('data->event', 'REFUND_PENDING')->count());
    }
}
