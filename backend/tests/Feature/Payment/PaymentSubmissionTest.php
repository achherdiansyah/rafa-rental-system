<?php

namespace Tests\Feature\Payment;

use App\Enums\AssignmentStatus;
use App\Enums\BookingStatus;
use App\Enums\EquipmentStatus;
use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PaymentSubmissionTest extends TestCase
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
     * @return array{0: User, 1: Invoice, 2: BankAccount}
     */
    private function issuedInvoice(): array
    {
        $owner = User::factory()->create(['role' => UserRole::USER]);
        $admin = User::factory()->admin()->create();
        $location = ProjectLocation::factory()->create(['user_id' => $owner->id]);
        $model = EquipmentModel::factory()->create(['is_active' => true]);
        $unit = EquipmentUnit::factory()->create(['equipment_model_id' => $model->id, 'status' => EquipmentStatus::ASSIGNED]);
        $bank = \App\Models\BankAccount::factory()->create();

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
            'rental_rate_snapshot' => 100000.00,
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

        // Record + approve timesheet while rental is ONGOING
        $rental = Rental::findOrFail($rentalId);
        $tsId = null;
        Sanctum::actingAs($admin);
        $tsId = $this->postJson('/api/v1/timesheets', [
            'rental_detail_id' => $rental->details()->first()->id,
            'report_date' => now()->toDateString(),
            'start_hm' => 8,
            'end_hm' => 16,
        ])->json('data.id');
        $this->postJson("/api/v1/timesheets/{$tsId}/submit")->assertOk();

        $this->postJson("/api/v1/timesheets/{$tsId}/approve")->assertOk();

        // Return -> inspect -> READY
        foreach (['return', 'inspect'] as $target) {
            $this->postJson("/api/v1/rentals/{$rentalId}/{$target}")->assertOk();
        }
        $this->postJson("/api/v1/rentals/{$rentalId}/ready", ['result' => 'READY'])->assertOk();

        // Create + issue DAILY_WORK invoice
        Sanctum::actingAs($admin);
        $invoiceId = $this->postJson('/api/v1/invoices', [
            'booking_id' => $booking->id,
            'invoice_type' => InvoiceType::DAILY_WORK->value,
        ])->json('data.id');
        $this->postJson("/api/v1/invoices/{$invoiceId}/issue")->assertOk();

        return [$owner, Invoice::findOrFail($invoiceId)];
    }

    private function submitPayment(User $as, Invoice $invoice, array $overrides = [], ?int $bankId = null): array
    {
        Sanctum::actingAs($as);
        $bank = $bankId ?? \App\Models\BankAccount::factory()->create()->id;
        $res = $this->postJson("/api/v1/invoices/{$invoice->id}/payments", array_merge([
            'amount' => 500000.00,
            'payment_date' => now()->toDateString(),
            'bank_account_id' => $bank,
            'sender_name' => 'PT Mitra Sejahtera',
            'reference' => 'TRF-'.$invoice->id,
            'proof' => UploadedFile::fake()->image('bukti.png', 800, 400),
        ], $overrides));

        return [$res, $res->json('data')];
    }

    public function test_valid_submission_creates_submitted_payment_with_private_proof(): void
    {
        [$owner, $invoice] = $this->issuedInvoice();

        [$res, $data] = $this->submitPayment($owner, $invoice);

        $res->assertCreated()
            ->assertJsonPath('data.status', PaymentStatus::SUBMITTED->value)
            ->assertJsonPath('data.invoice_id', $invoice->id)
            ->assertJsonPath('data.reference', "TRF-{$invoice->id}");

        $payment = Payment::findOrFail($data['id']);
        $proof = $payment->attachments()->where('document_type', 'PAYMENT_PROOF')->first();
        $this->assertNotNull($proof);
        Storage::disk('local')->assertExists($proof->file_path);
        $this->assertNull($data['proof']['url'], 'Bukti privat tidak boleh terekspos URL public.');

        // Not approved yet; invoice remains untouched
        $this->assertEquals(PaymentStatus::SUBMITTED->value, $data['status']);
        $this->assertEquals(0.00, (float) $invoice->fresh()->paid_amount);
        $this->assertEquals(InvoiceStatus::ISSUED->value, $invoice->fresh()->status->value);
    }

    public function test_invalid_amount_is_rejected(): void
    {
        [$owner, $invoice] = $this->issuedInvoice();

        [$zero] = $this->submitPayment($owner, $invoice, ['amount' => 0]);
        $zero->assertStatus(422)->assertJsonValidationErrors(['amount']);

        [$neg] = $this->submitPayment($owner, $invoice, ['amount' => -100]);
        $neg->assertStatus(422)->assertJsonValidationErrors(['amount']);
    }

    public function test_wrong_invoice_and_ownership_are_rejected(): void
    {
        [, $invoice] = $this->issuedInvoice();
        $other = User::factory()->create(['role' => UserRole::USER]);

        // Non-owner cannot submit
        [$res] = $this->submitPayment($other, $invoice);
        $res->assertStatus(403);

        // Nonexistent invoice -> 404
        Sanctum::actingAs($other);
        $this->postJson('/api/v1/invoices/999999/payments', [
            'amount' => 100,
            'payment_date' => now()->toDateString(),
            'bank_account_id' => 1,
            'proof' => UploadedFile::fake()->image('b.png'),
        ])->assertStatus(404);
    }

    public function test_duplicate_reference_for_same_invoice_is_rejected(): void
    {
        [$owner, $invoice] = $this->issuedInvoice();

        [$first] = $this->submitPayment($owner, $invoice);
        $first->assertCreated();

        // Same reference again -> rejected as duplicate
        [$dup] = $this->submitPayment($owner, $invoice, ['amount' => 300000.00]);
        $dup->assertStatus(409)->assertJson(['code' => 'BUSINESS_RULE_VIOLATION']);

        // Different reference (new DP term) -> allowed (1 invoice, many payments)
        [$again] = $this->submitPayment($owner, $invoice, ['reference' => 'TRF-B-'.$invoice->id, 'amount' => 200000.00]);
        $again->assertCreated();
        $this->assertCount(2, $invoice->fresh()->payments);
    }

    public function test_payment_requires_valid_proof_file(): void
    {
        [$owner, $invoice] = $this->issuedInvoice();

        // Missing proof
        [$missing] = $this->submitPayment($owner, $invoice, ['proof' => null]);
        $missing->assertStatus(422)->assertJsonValidationErrors(['proof']);

        // Forged script file
        [$bad] = $this->submitPayment($owner, $invoice, [
            'proof' => UploadedFile::fake()->create('script.php', 100, 'application/x-php'),
        ]);
        $bad->assertStatus(422)->assertJsonValidationErrors(['proof']);

        // Oversized file
        [$big] = $this->submitPayment($owner, $invoice, [
            'proof' => UploadedFile::fake()->image('besar.png')->size(6000),
        ]);
        $big->assertStatus(422)->assertJsonValidationErrors(['proof']);

        $this->assertCount(0, $invoice->fresh()->payments);
    }

    public function test_payment_is_not_approved_and_no_auto_side_effects(): void
    {
        [$owner, $invoice] = $this->issuedInvoice();

        [$res] = $this->submitPayment($owner, $invoice);

        $payment = Payment::findOrFail($res->json('data.id'));
        $this->assertEquals(PaymentStatus::SUBMITTED->value, $payment->status->value);
        $this->assertNull($payment->verified_by);
        $this->assertNull($payment->rejection_reason);
    }

    public function test_queue_lists_submitted_payments_and_proof_is_streamed_to_admin(): void
    {
        [$owner, $invoice] = $this->issuedInvoice();
        [$submit] = $this->submitPayment($owner, $invoice);
        $payment = Payment::findOrFail($submit->json('data.id'));
        $admin = User::factory()->admin()->create();

        // User cannot open the global queue
        Sanctum::actingAs($owner);
        $this->getJson('/api/v1/payments')->assertStatus(403);

        // Admin sees the submitted payment in queue
        Sanctum::actingAs($admin);
        $queue = $this->getJson('/api/v1/payments');
        $queue->assertOk()
            ->assertJsonPath('data.0.id', $payment->id)
            ->assertJsonPath('data.0.status', PaymentStatus::SUBMITTED->value)
            ->assertJsonPath('meta.total', 1);

        // Admin streams the private proof
        $proof = $this->getJson("/api/v1/payments/{$payment->id}/proof", ['Accept' => 'image/png']);
        $proof->assertOk();
        $this->assertStringContainsString('image/png', $proof->headers->get('content-type'));

        // Owner cannot stream someone else's proof? (owner may view own payment proof)
        Sanctum::actingAs($owner);
        $this->getJson("/api/v1/payments/{$payment->id}/proof")->assertOk();
    }
}
