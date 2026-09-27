<?php

namespace Tests\Feature\Billing;

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
use App\Models\EquipmentPrice;
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

class BillingPaymentIntegrationTest extends TestCase
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
     * Build a COMPLETED rental with an approved timesheet + issued DAILY_WORK invoice.
     *
     * @return array{0: User, 1: User, 2: Booking, 3: Invoice}
     */
    private function issuedDailyWorkInvoice(int $hours = 8, float $rate = 100000.00, float $mob = 250000.00, float $demob = 150000.00): array
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
            'mob_cost_snapshot' => $mob,
            'demob_cost_snapshot' => $demob,
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
        foreach (['dispatch', 'arrive', 'start'] as $t) {
            $this->postJson("/api/v1/rentals/{$rentalId}/{$t}")->assertOk();
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

        foreach (['return', 'inspect'] as $t) {
            $this->postJson("/api/v1/rentals/{$rentalId}/{$t}")->assertOk();
        }
        $this->postJson("/api/v1/rentals/{$rentalId}/ready", ['result' => 'READY'])->assertOk();

        $invoiceId = $this->postJson('/api/v1/invoices', [
            'booking_id' => $booking->id,
            'invoice_type' => InvoiceType::DAILY_WORK->value,
        ])->json('data.id');
        $this->postJson("/api/v1/invoices/{$invoiceId}/issue")->assertOk();

        return [$owner, $admin, $booking, Invoice::findOrFail($invoiceId)];
    }

    private function submit(User $owner, Invoice $invoice, float $amount, string $reference): Payment
    {
        Sanctum::actingAs($owner);
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

    public function test_full_billing_payment_flow_paid_via_multiple_payments(): void
    {
        [$owner, $admin, , $invoice] = $this->issuedDailyWorkInvoice();
        $total = (float) $invoice->grand_total;

        // 24h deadline anchored to issued_at
        $this->assertEqualsWithDelta($invoice->issued_at->copy()->addHours(24)->timestamp, $invoice->due_at->timestamp, 1);
        $dueAt = $invoice->due_at;

        // Partial → partial → exact (multiple payments, one invoice)
        $third = round($total / 3, 2);
        $p1 = $this->submit($owner, $invoice, $third, 'TRF-I1');
        $this->approve($admin, $p1);
        $this->assertEquals(InvoiceStatus::PARTIALLY_PAID->value, $invoice->fresh()->status->value);

        $p2 = $this->submit($owner, $invoice, $third, 'TRF-I2');
        $this->approve($admin, $p2);
        $this->assertEquals(InvoiceStatus::PARTIALLY_PAID->value, $invoice->fresh()->status->value);

        $p3 = $this->submit($owner, $invoice, $total - $third * 2, 'TRF-I3');
        $this->approve($admin, $p3);

        $fresh = $invoice->fresh();
        $this->assertEquals(InvoiceStatus::PAID->value, $fresh->status->value);
        $this->assertEqualsWithDelta($total, (float) $fresh->paid_amount, 0.01);
        $this->assertEqualsWithDelta(0.0, (float) $fresh->balance(), 0.01);

        // One payment -> one invoice; each belongs to the same invoice
        foreach (Payment::all() as $payment) {
            $this->assertEquals($invoice->id, (int) $payment->invoice_id);
        }
        $this->assertCount(3, $invoice->fresh()->payments);

        // Deadline not mutated by verification
        $this->assertEquals($dueAt->timestamp, $invoice->fresh()->due_at->timestamp);
    }

    public function test_reject_reupload_deadline_and_overpayment(): void
    {
        [$owner, $admin, $booking, $invoice] = $this->issuedDailyWorkInvoice();
        $total = (float) $invoice->grand_total;
        $dueAt = $invoice->due_at;

        // Rejected payment: history kept, invoice untouched, deadline intact
        $bad = $this->submit($owner, $invoice, 100000.00, 'TRF-R1');
        Sanctum::actingAs($admin);
        $this->postJson("/api/v1/payments/$bad->id/reject", ['reason' => 'Nominal tidak cocok dengan mutasi bank.'])->assertOk();
        $this->assertEquals(PaymentStatus::REJECTED->value, Payment::find($bad->id)->status->value);
        $this->assertEquals($dueAt->timestamp, $invoice->fresh()->due_at->timestamp, 'Rejection tidak boleh mereset deadline.');

        // Re-upload after rejection with SAME reference is allowed
        $retry = $this->submit($owner, $invoice, $total, 'TRF-R1');
        $this->approve($admin, $retry);
        $this->assertEquals(InvoiceStatus::PAID->value, $invoice->fresh()->status->value);

        // New booking → MOB_DEMOB invoice overpay → OVERPAID + excess parked
        [, , , $mobInvoice] = $this->issuedDailyWorkInvoice(mob: 500000.00, demob: 300000.00);
        $mobTotal = (float) $mobInvoice->grand_total;

        $over = Payment::factory()->create([
            'invoice_id' => $mobInvoice->id,
            'amount' => $mobTotal + 250000.00,
            'status' => PaymentStatus::SUBMITTED,
            'payment_date' => now(),
        ]);
        $this->approve($admin, $over);

        $freshMob = $mobInvoice->fresh();
        $this->assertEquals(InvoiceStatus::OVERPAID->value, $freshMob->status->value);
        $this->assertEqualsWithDelta(250000.00, (float) $freshMob->overpayment_amount, 0.01);
        $this->assertEqualsWithDelta($mobTotal, (float) $freshMob->paid_amount, 0.01);
    }

    public function test_edge_rules_authorization_and_duplicate(): void
    {
        [$owner, $admin, $booking, $invoice] = $this->issuedDailyWorkInvoice();
        $user = User::factory()->create(['role' => UserRole::USER]);

        // Invalid transition: approve an already-approved payment
        $p = $this->submit($owner, $invoice, 100.00, 'TRF-E1');
        $this->approve($admin, $p);
        $this->postJson("/api/v1/payments/$p->id/approve")
            ->assertStatus(409)->assertJson(['code' => 'INVALID_STATE_TRANSITION']);

        // Authorization: another user cannot submit, view, or verify
        Sanctum::actingAs($user);
        $this->postJson("/api/v1/invoices/{$invoice->id}/payments", [
            'amount' => 50000, 'payment_date' => now()->toDateString(),
            'bank_account_id' => BankAccount::factory()->create()->id,
            'proof' => UploadedFile::fake()->image('b.png'),
        ])->assertStatus(403);
        $this->getJson("/api/v1/invoices/{$invoice->id}")->assertStatus(403);
        $this->postJson("/api/v1/payments/$p->id/approve")->assertStatus(403);

        // Duplicate reference guard (non-rejected)
        [, $freshOwner, , $freshInvoice] = $this->issuedDailyWorkInvoice();
        $this->submit($freshOwner, $freshInvoice, 200000.00, 'TRF-DUP');
        Sanctum::actingAs($freshOwner);
        $dup = $this->postJson("/api/v1/invoices/{$freshInvoice->id}/payments", [
            'amount' => 200000.00,
            'payment_date' => now()->toDateString(),
            'bank_account_id' => BankAccount::factory()->create()->id,
            'reference' => 'TRF-DUP',
            'proof' => UploadedFile::fake()->image('c.png'),
        ]);
        $dup->assertStatus(409)->assertJson(['code' => 'BUSINESS_RULE_VIOLATION']);

        // Historical price: master change after invoicing never mutates totals
        $before = (float) $invoice->grand_total;
        $modelId = $invoice->booking->details()->first()->equipment_model_id;
        EquipmentPrice::factory()->create([
            'equipment_model_id' => $modelId, 'is_all_in' => true, 'base_rate' => 999999.00,
            'effective_date' => now()->toDateString(),
        ]);
        $after = Invoice::find($invoice->id);
        $this->assertEquals($before, (float) $after->grand_total, 'Total invoice tidak boleh berubah akibat harga master.');
        $this->assertEquals(100.00, (float) $after->paid_amount, 'paid_amount tidak dibukukan ulang.');
    }
}
