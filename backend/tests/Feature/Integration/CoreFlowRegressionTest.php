<?php

namespace Tests\Feature\Integration;

use App\Enums\AssignmentStatus;
use App\Enums\BookingStatus;
use App\Enums\EquipmentStatus;
use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Enums\RentalStatus;
use App\Enums\UserRole;
use App\Models\BankAccount;
use App\Models\Booking;
use App\Models\BookingDetail;
use App\Models\BookingUnitAssignment;
use App\Models\EquipmentModel;
use App\Models\EquipmentUnit;
use App\Models\Invoice;
use App\Models\ProjectLocation;
use App\Models\Rental;
use App\Models\Timesheet;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Phase 13A: golden path regression across Phase 2..12.
 * CONFIRMED -> rental -> timesheet -> invoice -> payment (paid). Each hop is
 * asserted to keep the state machine and amounts consistent.
 */
class CoreFlowRegressionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('public');
    }

    public function test_golden_path_booking_to_paid_invoice_stays_consistent(): void
    {
        $owner = User::factory()->create(['role' => UserRole::USER, 'name' => 'PT Mitra Sejahtera']);
        $admin = User::factory()->admin()->create();

        $location = ProjectLocation::factory()->create(['user_id' => $owner->id, 'project_name' => 'Tol Cisauk']);
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
            'rental_rate_snapshot' => 100000.00,
            'mob_cost_snapshot' => 0,
            'demob_cost_snapshot' => 0,
        ]);
        $assignment = BookingUnitAssignment::factory()->create([
            'booking_detail_id' => $detail->id,
            'equipment_unit_id' => $unit->id,
            'status' => AssignmentStatus::ASSIGNED,
            'is_current' => true,
            'assigned_by' => $admin->id,
        ]);

        // 1) Rental lifecycle dispatch -> arrive -> start
        Sanctum::actingAs($admin);
        $rentalId = $this->postJson('/api/v1/rentals', ['booking_id' => $booking->id])->json('data.id');
        foreach (['dispatch', 'arrive', 'start'] as $t) {
            $this->postJson("/api/v1/rentals/{$rentalId}/{$t}")->assertOk();
        }
        $rental = Rental::findOrFail($rentalId);
        $this->assertEquals(RentalStatus::ONGOING->value, $rental->status->value);

        // 2) Timesheet + approval
        Sanctum::actingAs($owner);
        $rd = $rental->details()->first();
        $tsid = $this->postJson('/api/v1/timesheets', [
            'rental_detail_id' => $rd->id,
            'report_date' => now()->toDateString(),
            'start_hm' => 8,
            'end_hm' => 16,
        ])->json('data.id');
        Sanctum::actingAs($admin);
        $this->postJson("/api/v1/timesheets/{$tsid}/submit")->assertOk();
        $this->postJson("/api/v1/timesheets/{$tsid}/approve")->assertOk();

        // 3) Return + inspection -> READY
        foreach (['return', 'inspect'] as $t) {
            $this->postJson("/api/v1/rentals/{$rentalId}/{$t}")->assertOk();
        }
        $this->postJson("/api/v1/rentals/{$rentalId}/ready", ['result' => 'READY'])->assertOk();
        $this->assertEquals(RentalStatus::COMPLETED->value, $rental->fresh()->status->value);
        $this->assertEquals(EquipmentStatus::AVAILABLE->value, $unit->fresh()->status->value);

        // 4) Invoice issued (8h x 100k)
        $invoiceId = $this->postJson('/api/v1/invoices', [
            'booking_id' => $booking->id,
            'invoice_type' => InvoiceType::DAILY_WORK->value,
        ])->assertCreated()->json('data.id');
        $this->postJson("/api/v1/invoices/{$invoiceId}/issue")->assertOk();
        $invoice = Invoice::findOrFail($invoiceId);
        $this->assertEqualsWithDelta(800000.0, (float) $invoice->grand_total, 0.01);
        $this->assertNotNull($invoice->due_at);

        // 5) Payment submitted + approved -> PAID
        Sanctum::actingAs($owner);
        $paymentId = $this->postJson("/api/v1/invoices/{$invoiceId}/payments", [
            'amount' => 800000,
            'payment_date' => now()->toDateString(),
            'bank_account_id' => BankAccount::factory()->create()->id,
            'sender_name' => 'PT Mitra Sejahtera',
            'reference' => 'TRF-GOLD',
            'proof' => UploadedFile::fake()->image('bukti.png', 800, 400),
        ])->assertCreated()->json('data.id');

        Sanctum::actingAs($admin);
        $this->postJson("/api/v1/payments/{$paymentId}/approve")->assertOk();

        $invoice = $invoice->fresh();
        $this->assertEquals(InvoiceStatus::PAID->value, $invoice->status->value);
        $this->assertEqualsWithDelta(800000.0, (float) $invoice->paid_amount, 0.01);
        $this->assertEqualsWithDelta(0.0, (float) $invoice->balance(), 0.01);

        // Cross-entity consistency checks
        $this->assertEquals(BookingStatus::CONFIRMED->value, $booking->fresh()->status->value, 'Booking sumber tidak berubah.');
        $this->assertEquals('APPROVED', Timesheet::find($tsid)->status->value);
        $this->assertDatabaseMissing('refunds', ['invoice_id' => $invoiceId]);
        $this->assertEquals(1, $invoice->details()->count());
    }
}
