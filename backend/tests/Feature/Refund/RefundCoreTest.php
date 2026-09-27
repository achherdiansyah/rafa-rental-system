<?php

namespace Tests\Feature\Refund;

use App\Enums\PaymentStatus;
use App\Enums\RefundSource;
use App\Enums\RefundStatus;
use App\Exceptions\InvalidStateTransitionException;
use App\Models\Booking;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\User;
use App\Services\Refund\RefundLifecycleService;
use App\Services\Refund\RefundRegistrationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RefundCoreTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Log::spy();
        Storage::fake('local');
        Storage::fake('public');
    }

    private function paidInvoice(float $amount): Invoice
    {
        $user = User::factory()->create();
        $booking = Booking::factory()->create([
            'user_id' => $user->id,
            'status' => 'CONFIRMED',
        ]);
        $invoice = Invoice::factory()->create([
            'booking_id' => $booking->id,
            'invoice_type' => 'DAILY_WORK',
            'status' => 'PAID',
            'grand_total' => $amount,
            'paid_amount' => $amount,
        ]);
        Payment::factory()->create([
            'invoice_id' => $invoice->id,
            'amount' => $amount,
            'status' => PaymentStatus::APPROVED,
        ]);

        return $invoice;
    }

    public function test_overpayment_registers_pending_refund_once(): void
    {
        $invoice = $this->paidInvoice(800000.00);
        $service = app(RefundRegistrationService::class);

        $service->noteOverpayment($invoice, 200000.00);
        $service->noteOverpayment($invoice, 200000.00); // idempotent

        $refunds = Refund::where('invoice_id', $invoice->id)->get();
        $this->assertCount(1, $refunds);
        $refund = $refunds->first();
        $this->assertEquals(RefundSource::OVERPAYMENT->value, $refund->source->value);
        $this->assertEquals(RefundStatus::PENDING->value, $refund->status->value);
        $this->assertEqualsWithDelta(200000.00, (float) $refund->amount, 0.01);
    }

    public function test_cancellation_registers_pending_refund_with_approved_sum(): void
    {
        $invoice = $this->paidInvoice(500000.00);
        $invoice2 = Invoice::factory()->create([
            'booking_id' => $invoice->booking_id,
            'invoice_type' => 'MOB_DEMOB',
            'status' => 'PAID',
            'grand_total' => 300000.00,
            'paid_amount' => 300000.00,
        ]);
        Payment::factory()->create([
            'invoice_id' => $invoice2->id,
            'amount' => 300000.00,
            'status' => PaymentStatus::APPROVED,
        ]);

        app(RefundRegistrationService::class)
            ->registerPendingRefund($invoice->booking, 'Pembatalan atas persetujuan tim operasional.');

        $refunds = Refund::where('invoice_id', $invoice->id)->get();
        $this->assertCount(1, $refunds);
        $this->assertEquals(RefundSource::CANCELLATION->value, $refunds->first()->source->value);
        $this->assertEqualsWithDelta(500000.00, (float) $refunds->first()->amount, 0.01);
        $this->assertCount(1, Refund::where('invoice_id', $invoice2->id)->get());
    }

    public function test_cancellation_without_payment_creates_no_refund(): void
    {
        $user = User::factory()->create();
        $booking = Booking::factory()->create(['user_id' => $user->id, 'status' => 'CONFIRMED']);
        Invoice::factory()->create(['booking_id' => $booking->id, 'status' => 'ISSUED', 'grand_total' => 800000.00]);

        app(RefundRegistrationService::class)->registerPendingRefund($booking, 'Batal sebelum bayar.');

        $this->assertCount(0, Refund::all());
    }

    public function test_lifecycle_processing_to_completed_with_proof(): void
    {
        $invoice = $this->paidInvoice(100000.00);
        $refund = Refund::factory()->create([
            'invoice_id' => $invoice->id,
            'source' => RefundSource::OVERPAYMENT,
            'status' => RefundStatus::PENDING,
            'amount' => 200000.00,
        ]);
        $staff = User::factory()->admin()->create();

        $service = app(RefundLifecycleService::class);

        $processing = $service->process($staff, $refund, [
            'customer_bank_info' => 'BCA 1234567890 a/n Customer',
            'transfer_reference' => 'REF-XYZ',
        ]);
        $this->assertEquals(RefundStatus::PROCESSING->value, $processing->status->value);
        $this->assertEquals($staff->id, $processing->processed_by);
        $this->assertNotNull($processing->processed_at);
        $this->assertEquals('BCA 1234567890 a/n Customer', $processing->customer_bank_info);

        // Invalid transition: PENDING cannot be completed directly (process first)
        $pendingCopy = Refund::factory()->create([
            'invoice_id' => $invoice->id,
            'status' => RefundStatus::PENDING,
            'amount' => 200000.00,
        ]);
        $this->expectException(InvalidStateTransitionException::class);
        app(RefundLifecycleService::class)->complete($staff, $pendingCopy, [], UploadedFile::fake()->image('b.png'));
    }

    public function test_complete_marks_completed_with_private_proof(): void
    {
        $invoice = $this->paidInvoice(100000.00);
        $refund = Refund::factory()->create([
            'invoice_id' => $invoice->id,
            'source' => RefundSource::OVERPAYMENT,
            'status' => RefundStatus::PENDING,
            'amount' => 250000.00,
        ]);
        $staff = User::factory()->admin()->create();
        $service = app(RefundLifecycleService::class);

        $processing = $service->process($staff, $refund, ['customer_bank_info' => 'BNI 0981 a/n Customer']);
        $completed = $service->complete($staff, $processing, ['transfer_reference' => 'TRF-BACK'], UploadedFile::fake()->image('resi.png', 600, 400));

        $this->assertEquals(RefundStatus::COMPLETED->value, $completed->status->value);
        $this->assertNotNull($completed->completed_at);
        $this->assertEquals('TRF-BACK', $completed->transfer_reference);

        $proof = $completed->attachments()->where('document_type', 'REFUND_PROOF')->first();
        $this->assertNotNull($proof);
        Storage::disk('local')->assertExists($proof->file_path);

        Log::shouldHaveReceived('info')->withArgs(fn ($msg) => is_string($msg) && str_contains($msg, '[REFUND_COMPLETED]'));
    }

    public function test_fail_from_pending_or_processing(): void
    {
        $invoice = $this->paidInvoice(100000.00);
        $refund = Refund::factory()->create(['invoice_id' => $invoice->id, 'status' => RefundStatus::PENDING, 'amount' => 10000.00]);
        $staff = User::factory()->admin()->create();

        $failed = app(RefundLifecycleService::class)->fail($staff, $refund, 'Rekening pelanggan tidak valid.');
        $this->assertEquals(RefundStatus::FAILED->value, $failed->status->value);
        $this->assertEquals('Rekening pelanggan tidak valid.', $failed->failure_reason);

        // completed/failed are terminal
        $this->expectException(InvalidStateTransitionException::class);
        app(RefundLifecycleService::class)->fail($staff, $failed, 'Ulangi.');
    }

    public function test_authorization_and_scoping(): void
    {
        $invoice = $this->paidInvoice(100000.00);
        $refund = Refund::factory()->create(['invoice_id' => $invoice->id, 'status' => RefundStatus::PENDING, 'amount' => 50000.00]);
        $admin = User::factory()->admin()->create();
        $stranger = User::factory()->create();

        // Stranger cannot view someone else's refund
        Sanctum::actingAs($stranger);
        $this->getJson("/api/v1/refunds/{$refund->id}")->assertStatus(403);

        // Non-admin cannot process/complete/fail
        $this->postJson("/api/v1/refunds/{$refund->id}/process", ['customer_bank_info' => 'BCA x'])->assertStatus(403);

        // Owner role may view but not manage
        $owner = User::factory()->owner()->create();
        Sanctum::actingAs($owner);
        $this->getJson("/api/v1/refunds/{$refund->id}")->assertOk();
        $this->postJson("/api/v1/refunds/{$refund->id}/fail", ['failure_reason' => 'Alasan administrasi ganda.'])->assertStatus(403);

        // Admin processes then completes via API
        Sanctum::actingAs($admin);
        $this->postJson("/api/v1/refunds/{$refund->id}/process", [
            'customer_bank_info' => 'MANDIRI 111 a/n Customer',
            'transfer_reference' => 'TRF-LIVE',
        ])->assertOk()->assertJsonPath('data.status', RefundStatus::PROCESSING->value);

        $this->postJson("/api/v1/refunds/{$refund->id}/complete", [
            'transfer_reference' => 'TRF-LIVE',
            'proof' => UploadedFile::fake()->image('resi-final.png', 600, 400),
        ])->assertOk()->assertJsonPath('data.status', RefundStatus::COMPLETED->value);

        // History kept
        $this->assertDatabaseHas('refunds', ['id' => $refund->id, 'status' => RefundStatus::COMPLETED->value]);
    }
}
