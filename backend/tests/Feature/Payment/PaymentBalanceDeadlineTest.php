<?php

namespace Tests\Feature\Payment;

use App\Enums\AssignmentStatus;
use App\Enums\BookingStatus;
use App\Enums\EquipmentStatus;
use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Models\BankAccount;
use App\Models\Booking;
use App\Models\BookingDetail;
use App\Models\BookingUnitAssignment;
use App\Models\EquipmentModel;
use App\Models\EquipmentUnit;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\ProjectLocation;
use App\Models\Rental;
use App\Models\User;
use App\Services\Refund\RefundBoundary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Tests\TestCase;

class PaymentBalanceDeadlineTest extends TestCase
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
     * @return array{0: User, 1: Invoice}
     */
    private function issuedInvoice(int $hours = 8, float $rate = 100000.00): array
    {
        $owner = User::factory()->create(['role' => UserRole::USER]);
        $admin = User::factory()->admin()->create();
        $location = ProjectLocation::factory()->create(['user_id' => $owner->id]);
        $model = EquipmentModel::factory()->create(['is_active' => true]);
        $unit = EquipmentUnit::factory()->create(['equipment_model_id' => $model->id, 'status' => EquipmentStatus::ASSIGNED]);

        $booking = Booking::factory()->create([
            'user_id' => $owner->id,
            'project_location_id' => $location->id,
            'status' => BookingStatus::CONFIRMED,
        ]);
        $detail = BookingDetail::factory()->create([
            'booking_id' => $booking->id,
            'equipment_model_id' => $model->id,
            'quantity' => 1,
            'is_all_in' => true,
            'rental_rate_snapshot' => $rate,
        ]);
        BookingUnitAssignment::factory()->create([
            'booking_detail_id' => $detail->id,
            'equipment_unit_id' => $unit->id,
            'status' => AssignmentStatus::ASSIGNED,
            'is_current' => true,
            'assigned_by' => $admin->id,
        ]);

        Sanctum::actingAs($admin);
        $rentalId = $this->postJson('/api/v1/rentals', ['booking_id' => $booking->id])->json('data.id');
        foreach (['dispatch', 'arrive', 'start'] as $target) {
            $this->postJson("/api/v1/rentals/{$rentalId}/{$target}")->assertOk();
        }

        $rental = Rental::findOrFail($rentalId);
        Sanctum::actingAs($admin);
        $tsId = $this->postJson('/api/v1/timesheets', [
            'rental_detail_id' => $rental->details()->first()->id,
            'report_date' => now()->toDateString(),
            'start_hm' => 8,
            'end_hm' => 8 + $hours,
        ])->json('data.id');

        foreach (['return', 'inspect'] as $target) {
            $this->postJson("/api/v1/rentals/{$rentalId}/{$target}")->assertOk();
        }
        $this->postJson("/api/v1/rentals/{$rentalId}/ready", ['result' => 'READY'])->assertOk();

        $invoice = Invoice::where('booking_id', $booking->id)
            ->where('invoice_type', InvoiceType::DAILY_WORK->value)
            ->firstOrFail();

        return [$owner, $invoice];
    }

    private function submit(User $as, Invoice $invoice, float $amount, string $reference): Payment
    {
        Sanctum::actingAs($as);
        $res = $this->postJson("/api/v1/invoices/{$invoice->id}/payments", [
            'amount' => $amount,
            'payment_date' => now()->toDateString(),
            'bank_account_id' => BankAccount::factory()->create()->id,
            'sender_name' => 'PT Mitra Sejahtera',
            'reference' => $reference,
            'proof' => UploadedFile::fake()->image('bukti.png', 800, 400),
        ]);
        $res->assertCreated();

        return Payment::findOrFail($res->json('data.id'));
    }

    private function approve(User $admin, Payment $payment): void
    {
        Sanctum::actingAs($admin);
        $this->postJson("/api/v1/payments/$payment->id/approve")->assertOk();
    }

    public function test_multiple_approved_payments_sum_to_exact_total_no_double_count(): void
    {
        [$owner, $invoice] = $this->issuedInvoice();
        $admin = User::factory()->admin()->create();

        $amounts = [250000.00, 250000.00, 300000.00]; // total 800.000
        foreach ($amounts as $i => $amount) {
            $p = $this->submit($owner, $invoice, $amount, "TRF-DP{$i}");
            $this->approve($admin, $p);
        }

        $fresh = $invoice->fresh();
        $this->assertEquals(InvoiceStatus::PAID->value, $fresh->status->value);
        $this->assertEqualsWithDelta(array_sum($amounts), (float) $fresh->paid_amount, 0.01);
        $this->assertEqualsWithDelta(array_sum($amounts), (float) $invoice->payments()->where('status', PaymentStatus::APPROVED)->sum('amount'), 0.01);
        $this->assertEqualsWithDelta(0.0, (float) $fresh->balance(), 0.01);
        $this->assertEqualsWithDelta(0.0, (float) $fresh->overpayment_amount, 0.01);
    }

    public function test_partial_sequence_stays_partial_until_paid(): void
    {
        [$owner, $invoice] = $this->issuedInvoice();
        $admin = User::factory()->admin()->create();

        $p1 = $this->submit($owner, $invoice, 200000.00, 'TRF-P1');
        $this->approve($admin, $p1);
        $this->assertEquals(InvoiceStatus::PARTIALLY_PAID->value, $invoice->fresh()->status->value);
        $this->assertEqualsWithDelta(600000.00, (float) $invoice->fresh()->balance(), 0.01);

        // Rejected payment does NOT add balance
        $p2 = $this->submit($owner, $invoice, 300000.00, 'TRF-P2');
        Sanctum::actingAs($admin);
        $this->postJson("/api/v1/payments/$p2->id/reject", ['reason' => 'Tidak sesuai mutasi.'])->assertOk();
        $this->assertEqualsWithDelta(600000.00, (float) $invoice->fresh()->balance(), 0.01);

        $p3 = $this->submit($owner, $invoice, 600000.00, 'TRF-P3');
        $this->approve($admin, $p3);
        $this->assertEquals(InvoiceStatus::PAID->value, $invoice->fresh()->status->value);
        $this->assertEqualsWithDelta(0.0, (float) $invoice->fresh()->balance(), 0.01);
        // Only approved payments counted (rejected excluded)
        $this->assertEqualsWithDelta(800000.00, (float) $invoice->fresh()->paid_amount, 0.01);
    }

    public function test_overpayment_parks_excess_and_signals_refund_boundary(): void
    {
        [, $invoice] = $this->issuedInvoice();
        $admin = User::factory()->admin()->create();

        $boundary = Mockery::mock(RefundBoundary::class);
        $boundary->shouldReceive('noteOverpayment')
            ->once()
            ->withArgs(fn ($target, $excess) => $target->id === $invoice->id && abs($excess - 200000.00) < 0.01);
        $this->app->instance(RefundBoundary::class, $boundary);

        // Build owner+payment: we only need submit as owner then approve as admin
        $payment = Payment::factory()->create([
            'invoice_id' => $invoice->id,
            'amount' => 1000000.00,
            'status' => PaymentStatus::SUBMITTED,
            'payment_date' => now(),
        ]);

        $this->approve($admin, $payment);

        $fresh = $invoice->fresh();
        $this->assertEquals(InvoiceStatus::OVERPAID->value, $fresh->status->value);
        $this->assertEqualsWithDelta(800000.00, (float) $fresh->paid_amount, 0.01);
        $this->assertEqualsWithDelta(200000.00, (float) $fresh->overpayment_amount, 0.01);
    }

    public function test_deadline_is_issued_at_plus_24h_and_rejection_does_not_reset(): void
    {
        [$owner, $invoice] = $this->issuedInvoice();
        $admin = User::factory()->admin()->create();

        $this->assertNotNull($invoice->issued_at);
        $this->assertEqualsWithDelta($invoice->issued_at->copy()->addHours(24)->timestamp, $invoice->due_at->timestamp, 1);

        $p = $this->submit($owner, $invoice, 300000.00, 'TRF-DEAD');
        Sanctum::actingAs($admin);
        $this->postJson("/api/v1/payments/$p->id/reject", ['reason' => 'Salah nominal.'])->assertOk();

        // Deadline untouched by rejection
        $this->assertEquals($invoice->due_at->timestamp, $invoice->fresh()->due_at->timestamp, 'Rejection tidak boleh mereset deadline.');
    }

    public function test_admin_can_extend_deadline_with_audit(): void
    {
        [$owner, $invoice] = $this->issuedInvoice();
        $admin = User::factory()->admin()->create();
        $originalDue = $invoice->due_at;

        Sanctum::actingAs($admin);
        $res = $this->postJson("/api/v1/invoices/{$invoice->id}/extend-deadline", ['hours' => 48]);
        $res->assertOk();

        $this->assertEqualsWithDelta($originalDue->copy()->addHours(48)->timestamp, $invoice->fresh()->due_at->timestamp, 1);

        Log::shouldHaveReceived('info')
            ->withArgs(fn ($msg) => is_string($msg) && str_contains($msg, '[INVOICE_DEADLINE_EXTENDED]'));

        // Only the sanctioned action mutates due_at; issued_at persists as source.
        $this->assertEquals($invoice->issued_at->timestamp, $invoice->fresh()->issued_at->timestamp);
    }

    public function test_extension_requires_admin_and_extendable_status(): void
    {
        [$owner, $invoice] = $this->issuedInvoice();
        $user = User::factory()->create(['role' => UserRole::USER]);

        Sanctum::actingAs($user);
        $this->postJson("/api/v1/invoices/{$invoice->id}/extend-deadline", ['hours' => 24])
            ->assertStatus(403);

        // Invalid hours range
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);
        $this->postJson("/api/v1/invoices/{$invoice->id}/extend-deadline", ['hours' => 999])
            ->assertStatus(422)->assertJsonValidationErrors(['hours']);

        // Not extendable once PAID
        $p = Payment::factory()->create([
            'invoice_id' => $invoice->id,
            'amount' => (float) $invoice->grand_total,
            'status' => PaymentStatus::SUBMITTED,
            'payment_date' => now(),
        ]);
        $this->postJson("/api/v1/payments/$p->id/approve")->assertOk();
        $this->assertEquals(InvoiceStatus::PAID->value, $invoice->fresh()->status->value);

        $this->postJson("/api/v1/invoices/{$invoice->id}/extend-deadline", ['hours' => 24])
            ->assertStatus(409)
            ->assertJson(['code' => 'INVALID_STATE_TRANSITION']);
    }
}
