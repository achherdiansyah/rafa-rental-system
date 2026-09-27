<?php

namespace Tests\Feature\Payment;

use App\Enums\AssignmentStatus;
use App\Enums\BookingStatus;
use App\Enums\EquipmentStatus;
use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
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
use App\Models\Refund;
use App\Models\Rental;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PaymentVerificationTest extends TestCase
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
     * @return array{0: User, 1: Invoice, 2: float}
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
        Sanctum::actingAs($owner);
        $tsId = $this->postJson('/api/v1/timesheets', [
            'rental_detail_id' => $rental->details()->first()->id,
            'report_date' => now()->toDateString(),
            'start_hm' => 8,
            'end_hm' => 8 + $hours,
        ])->json('data.id');
        Sanctum::actingAs($admin);
        $this->postJson("/api/v1/timesheets/{$tsId}/submit")->assertOk();
        $this->postJson("/api/v1/timesheets/{$tsId}/approve")->assertOk();

        foreach (['return', 'inspect'] as $target) {
            $this->postJson("/api/v1/rentals/{$rentalId}/{$target}")->assertOk();
        }
        $this->postJson("/api/v1/rentals/{$rentalId}/ready", ['result' => 'READY'])->assertOk();

        Sanctum::actingAs($admin);
        $invoiceId = $this->postJson('/api/v1/invoices', [
            'booking_id' => $booking->id,
            'invoice_type' => InvoiceType::DAILY_WORK->value,
        ])->json('data.id');
        $this->postJson("/api/v1/invoices/{$invoiceId}/issue")->assertOk();

        return [$owner, Invoice::findOrFail($invoiceId)];
    }

    private function submit(User $as, Invoice $invoice, float $amount, string $reference, ?float $dueBefore = null): Payment
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

    private function verify(User $as, Payment $payment, string $action): array
    {
        Sanctum::actingAs($as);
        $res = $this->postJson("/api/v1/payments/{$payment->id}/{$action}");
        if ($action === 'reject') {
            return [$res, $payment];
        }
        $res->assertOk();

        return [$res, $res->json('data')];
    }

    public function test_approve_exact_payment_marks_invoice_paid(): void
    {
        [$owner, $invoice] = $this->issuedInvoice();
        $admin = User::factory()->admin()->create();
        $dueAt = $invoice->due_at;

        $payment = $this->submit($owner, $invoice, 800000.00, 'TRF-EXACT');
        $this->assertSame(800000.00, (float) $payment->amount, 'Nominal transfer tidak boleh berubah.');

        [$res, $data] = $this->verify($admin, $payment, 'approve');

        $this->assertEquals(PaymentStatus::APPROVED->value, $data['status']);
        $this->assertEquals(InvoiceStatus::PAID->value, $invoice->fresh()->status->value);
        $this->assertEqualsWithDelta(800000.00, (float) $invoice->fresh()->paid_amount, 0.01);
        $this->assertEqualsWithDelta(0.0, (float) $invoice->fresh()->balance(), 0.01);
        $this->assertEquals($admin->id, $payment->fresh()->verified_by);
        $this->assertEquals($dueAt->timestamp, $invoice->fresh()->due_at->timestamp, 'Deadline invoice tidak boleh berubah.');
    }

    public function test_approve_partial_then_full_marks_partial_then_paid(): void
    {
        [$owner, $invoice] = $this->issuedInvoice();
        $admin = User::factory()->admin()->create();

        $first = $this->submit($owner, $invoice, 300000.00, 'TRF-DP1');
        [$res1] = $this->verify($admin, $first, 'approve');
        $this->assertEquals(InvoiceStatus::PARTIALLY_PAID->value, $invoice->fresh()->status->value);
        $this->assertEqualsWithDelta(300000.00, (float) $invoice->fresh()->paid_amount, 0.01);
        $this->assertEqualsWithDelta(500000.00, (float) $invoice->fresh()->balance(), 0.01);

        $second = $this->submit($owner, $invoice, 500000.00, 'TRF-DP2');
        $this->verify($admin, $second, 'approve');

        $this->assertEquals(InvoiceStatus::PAID->value, $invoice->fresh()->status->value);
        $this->assertEqualsWithDelta(800000.00, (float) $invoice->fresh()->paid_amount, 0.01);
        $this->assertEqualsWithDelta(0.0, (float) $invoice->fresh()->balance(), 0.01);
    }

    public function test_overpayment_flags_invoice_and_parks_excess_without_refund(): void
    {
        [$owner, $invoice] = $this->issuedInvoice();
        $admin = User::factory()->admin()->create();

        $payment = $this->submit($owner, $invoice, 1000000.00, 'TRF-OVER');
        [$res, $data] = $this->verify($admin, $payment, 'approve');

        $this->assertEquals(PaymentStatus::APPROVED->value, $data['status']);
        $this->assertEquals(InvoiceStatus::OVERPAID->value, $invoice->fresh()->status->value);
        $this->assertEqualsWithDelta(800000.00, (float) $invoice->fresh()->paid_amount, 0.01);
        $this->assertEqualsWithDelta(200000.00, (float) $invoice->fresh()->overpayment_amount, 0.01);

        // Phase 11: overpayment registers a PENDING refund (no auto-settlement).
        $refund = Refund::where('invoice_id', $invoice->id)->where('source', 'OVERPAYMENT')->first();
        $this->assertNotNull($refund);
        $this->assertEquals(RefundStatus::PENDING->value, $refund->status->value);
        $this->assertEqualsWithDelta(200000.00, (float) $refund->amount, 0.01);
    }

    public function test_reject_keeps_row_and_invoice_untouched(): void
    {
        [$owner, $invoice] = $this->issuedInvoice();
        $admin = User::factory()->admin()->create();

        $payment = $this->submit($owner, $invoice, 500000.00, 'TRF-BOGUS');
        Sanctum::actingAs($admin);
        $res = $this->postJson("/api/v1/payments/$payment->id/reject", [
            'reason' => 'Nominal tidak cocok dengan mutasi bank.',
        ]);
        $res->assertOk()->assertJsonPath('data.status', PaymentStatus::REJECTED->value);

        $fresh = $payment->fresh();
        $this->assertEquals('Nominal tidak cocok dengan mutasi bank.', $fresh->rejection_reason);
        $this->assertEquals($admin->id, $fresh->verified_by);

        // Invoice untouched; deadline unchanged
        $this->assertEquals(InvoiceStatus::ISSUED->value, $invoice->fresh()->status->value);
        $this->assertEqualsWithDelta(0.0, (float) $invoice->fresh()->paid_amount, 0.01);
        $this->assertDatabaseHas('payments', ['id' => $payment->id, 'status' => PaymentStatus::REJECTED->value]);
    }

    public function test_user_can_reupload_after_rejection_with_same_reference(): void
    {
        [$owner, $invoice] = $this->issuedInvoice();
        $admin = User::factory()->admin()->create();

        $bad = $this->submit($owner, $invoice, 500000.00, 'TRF-RETRY');
        Sanctum::actingAs($admin);
        $this->postJson("/api/v1/payments/$bad->id/reject", [
            'reason' => 'Bukti tidak jelas.',
        ])->assertOk();

        // Same reference allowed again after rejection
        $retry = $this->submit($owner, $invoice, 800000.00, 'TRF-RETRY');
        $this->assertEquals(PaymentStatus::SUBMITTED->value, $retry->status->value);

        $this->verify($admin, $retry, 'approve');
        $this->assertEquals(InvoiceStatus::PAID->value, $invoice->fresh()->status->value);
    }

    public function test_verification_requires_admin_and_mandatory_reason(): void
    {
        [$owner, $invoice] = $this->issuedInvoice();
        $user = $this->freshUser();
        $payment = $this->submit($owner, $invoice, 400000.00, 'TRF-AUTH');

        // USER cannot approve/reject
        Sanctum::actingAs($user);
        $this->postJson("/api/v1/payments/$payment->id/approve")->assertStatus(403);
        $this->postJson("/api/v1/payments/$payment->id/reject", ['reason' => 'Tidak sah.'])
            ->assertStatus(403);

        // Admin reject requires reason >= 5 chars
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);
        $this->postJson("/api/v1/payments/$payment->id/reject", ['reason' => 'ab'])
            ->assertStatus(422)->assertJsonValidationErrors(['reason']);

        // Double processing is impossible
        $this->postJson("/api/v1/payments/$payment->id/approve")->assertOk();
        $this->postJson("/api/v1/payments/$payment->id/approve")
            ->assertStatus(409)
            ->assertJson(['code' => 'INVALID_STATE_TRANSITION']);
    }

    public function test_verification_is_audited(): void
    {
        [$owner, $invoice] = $this->issuedInvoice();
        $admin = User::factory()->admin()->create();

        $ok = $this->submit($owner, $invoice, 800000.00, 'TRF-AUDIT-OK');
        $nope = $this->submit($owner, $invoice, 100000.00, 'TRF-AUDIT-NO');

        Sanctum::actingAs($admin);
        $this->postJson("/api/v1/payments/$ok->id/approve")->assertOk();
        $this->postJson("/api/v1/payments/$nope->id/reject", [
            'reason' => 'Mutasi bank tidak ditemukan.',
        ])->assertOk();

        Log::shouldHaveReceived('info')
            ->withArgs(fn ($msg) => is_string($msg) && str_contains($msg, '[PAYMENT_APPROVED]'));
        Log::shouldHaveReceived('info')
            ->withArgs(fn ($msg) => is_string($msg) && str_contains($msg, '[PAYMENT_REJECTED]'));
    }

    private function freshUser(): User
    {
        return User::factory()->create(['role' => UserRole::USER]);
    }
}
