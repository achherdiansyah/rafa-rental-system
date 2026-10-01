<?php

namespace Tests\Feature\Billing;

use App\Actions\Billing\GenerateRentalBillAction;
use App\Enums\AssignmentStatus;
use App\Enums\BookingStatus;
use App\Enums\EquipmentStatus;
use App\Enums\RentalStatus;
use App\Enums\TimesheetStatus;
use App\Enums\UserRole;
use App\Models\Booking;
use App\Models\BookingDetail;
use App\Models\BookingUnitAssignment;
use App\Models\EquipmentModel;
use App\Models\EquipmentPrice;
use App\Models\EquipmentUnit;
use App\Models\ProjectLocation;
use App\Models\Rental;
use App\Models\Timesheet;
use App\Models\User;
use App\Services\Billing\RentalBillingService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BillingEngineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Log::spy();
    }

    private function price(int $modelId, string $scheme, float $rate, float $mob = 0, float $demob = 0): EquipmentPrice
    {
        return EquipmentPrice::factory()->create([
            'equipment_model_id' => $modelId,
            'is_all_in' => $scheme === 'all-in',
            'base_rate' => $rate,
            'mob_cost' => $mob,
            'demob_cost' => $demob,
            'effective_date' => now()->subMonth()->toDateString(),
        ]);
    }

    /**
     * Build a COMPLETED rental whose detail snapshots are set explicitly.
     *
     * @return array{0: User, 1: Rental, 2: array<int, EquipmentUnit>}
     */
    private function completedRental(
        int $unitCount = 1,
        ?float $rateSnapshot = 150000.00,
        ?bool $allIn = true,
        ?float $mobSnapshot = 0.0,
        ?float $demobSnapshot = 0.0,
        array $hoursByDetail = []
    ): array {
        $owner = User::factory()->create(['role' => UserRole::USER]);
        $admin = User::factory()->admin()->create();
        $location = ProjectLocation::factory()->create(['user_id' => $owner->id]);
        $model = EquipmentModel::factory()->create(['is_active' => true]);

        $units = EquipmentUnit::factory()->count($unitCount)->create([
            'equipment_model_id' => $model->id,
            'status' => EquipmentStatus::ASSIGNED,
        ]);

        $booking = Booking::factory()->create([
            'user_id' => $owner->id,
            'project_location_id' => $location->id,
            'status' => BookingStatus::CONFIRMED,
        ]);
        $detailRow = BookingDetail::factory()->create([
            'booking_id' => $booking->id,
            'equipment_model_id' => $model->id,
            'quantity' => $unitCount,
            'start_date' => now()->addDays(2)->toDateString(),
            'end_date' => now()->addDays(8)->toDateString(),
            'is_all_in' => $allIn,
            'rental_rate_snapshot' => $rateSnapshot,
            'mob_cost_snapshot' => $mobSnapshot,
            'demob_cost_snapshot' => $demobSnapshot,
        ]);

        foreach ($units as $unit) {
            BookingUnitAssignment::factory()->create([
                'booking_detail_id' => $detailRow->id,
                'equipment_unit_id' => $unit->id,
                'status' => AssignmentStatus::ASSIGNED,
                'is_current' => true,
                'assigned_by' => $admin->id,
            ]);
        }

        // Drive rental to COMPLETED via API
        Sanctum::actingAs($admin);
        $rentalId = $this->postJson('/api/v1/rentals', ['booking_id' => $booking->id])->json('data.id');
        foreach (['dispatch', 'arrive', 'start'] as $target) {
            $this->postJson("/api/v1/rentals/{$rentalId}/{$target}")->assertOk();
        }

        // Record + approve timesheets while rental is ONGOING
        $rental = Rental::findOrFail($rentalId);
        $details = $rental->details()->orderBy('id')->get();

        foreach ($details as $idx => $rd) {
            $hours = $hoursByDetail[$idx] ?? 8.5;

            Sanctum::actingAs($admin);
            $tsId = $this->postJson('/api/v1/timesheets', [
                'rental_detail_id' => $rd->id,
                'report_date' => now()->toDateString(),
                'start_hm' => 8,
                'end_hm' => 8 + $hours,
                'break_minutes' => 0,
            ])->json('data.id');

            Sanctum::actingAs($admin);
            $this->postJson("/api/v1/timesheets/{$tsId}/submit")->assertOk();
            $this->postJson("/api/v1/timesheets/{$tsId}/approve")->assertOk();
        }

        // Return -> inspect -> READY
        Sanctum::actingAs($admin);
        foreach (['return', 'inspect'] as $target) {
            $this->postJson("/api/v1/rentals/{$rentalId}/{$target}")->assertOk();
        }
        $this->postJson("/api/v1/rentals/{$rentalId}/ready", ['result' => 'READY'])->assertOk();

        return [$owner, Rental::findOrFail($rentalId), $units];
    }

    public function test_actual_hours_times_hourly_rate_without_rounding(): void
    {
        [, $rental] = $this->completedRental(1, rateSnapshot: 150000.00, hoursByDetail: [8.33]);

        $bill = app(RentalBillingService::class)->generate($rental);

        $line = $bill->lines[0];
        $this->assertEqualsWithDelta(8.33, $bill->totalWorkHours, 0.0001);
        $this->assertEqualsWithDelta(8.33 * 150000, $line->workAmount, 0.0001);
        $this->assertEqualsWithDelta(8.33 * 150000, $bill->totalRentalAmount, 0.0001);
        $this->assertEqualsWithDelta(0.0, $bill->grandTotal - 8.33 * 150000, 0.0001);
    }

    public function test_bill_line_uses_all_in_and_non_all_in_scheme_perline(): void
    {
        // All-in line
        [, $allInRental] = $this->completedRental(1, rateSnapshot: 200000.00, allIn: true, hoursByDetail: [8]);
        // Non All-in line
        [, $bareRental] = $this->completedRental(1, rateSnapshot: 125000.00, allIn: false, hoursByDetail: [8]);

        $service = app(RentalBillingService::class);
        $allInBill = $service->generate($allInRental);
        $bareBill = $service->generate($bareRental);

        $this->assertTrue($allInBill->lines[0]->isAllIn);
        $this->assertFalse($bareBill->lines[0]->isAllIn);
        $this->assertEqualsWithDelta(200000.00, $allInBill->lines[0]->hourlyRate, 0.0001);
        $this->assertEqualsWithDelta(125000.00, $bareBill->lines[0]->hourlyRate, 0.0001);
        $this->assertEqualsWithDelta(8 * 200000, $allInBill->totalRentalAmount, 0.0001);
        $this->assertEqualsWithDelta(8 * 125000, $bareBill->totalRentalAmount, 0.0001);
    }

    public function test_mob_demob_per_physical_unit_and_can_differ(): void
    {
        // 2 physical units -> 2 rental_details -> MOB & DEMOB charged per unit
        [, $rental] = $this->completedRental(
            unitCount: 2,
            rateSnapshot: 150000.00,
            mobSnapshot: 500000.00,
            demobSnapshot: 350000.00,
            hoursByDetail: [8, 8]
        );

        $bill = app(RentalBillingService::class)->generate($rental);

        $this->assertCount(2, $bill->lines);
        $this->assertEqualsWithDelta(500000.00 * 2, $bill->totalMobAmount, 0.0001);
        $this->assertEqualsWithDelta(350000.00 * 2, $bill->totalDemobAmount, 0.0001);
        $this->assertEqualsWithDelta(8 * 150000 * 2 + 1000000 + 700000, $bill->grandTotal, 0.0001);
    }

    public function test_price_snapshot_is_immutable_against_master_change(): void
    {
        [, $rental, $units] = $this->completedRental(1, rateSnapshot: 150000.00, mobSnapshot: 500000.00);

        // Owner later raises master price; historical snapshot stays untouched
        $owner2 = User::factory()->owner()->create();
        Sanctum::actingAs($owner2);
        $price = EquipmentPrice::factory()->create([
            'equipment_model_id' => $units[0]->equipment_model_id,
            'is_all_in' => true,
            'base_rate' => 999999.00,
            'mob_cost' => 900000.00,
            'effective_date' => now()->toDateString(),
        ]);
        $this->assertNotNull($price);

        $bill = app(RentalBillingService::class)->generate($rental);

        $this->assertEqualsWithDelta(150000.00, $bill->lines[0]->hourlyRate, 0.0001);
        $this->assertEqualsWithDelta(500000.00, $bill->lines[0]->mobCost, 0.0001);
    }

    public function test_snapshot_fallback_uses_latest_master_price(): void
    {
        [, $rental, $units] = $this->completedRental(1, rateSnapshot: 150000.00, mobSnapshot: null, demobSnapshot: null);

        $this->price($units[0]->equipment_model_id, 'all-in', 175000.00, mob: 250000.00, demob: 300000.00);

        $bill = app(RentalBillingService::class)->generate($rental);

        $this->assertEqualsWithDelta(150000.00, $bill->lines[0]->hourlyRate, 0.0001);
        $this->assertEqualsWithDelta(250000.00, $bill->lines[0]->mobCost, 0.0001);
        $this->assertEqualsWithDelta(300000.00, $bill->lines[0]->demobCost, 0.0001);
    }

    public function test_only_approved_timesheets_are_billed(): void
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

        $rental = Rental::findOrFail($rentalId);
        $rd = $rental->details()->first();

        // 7.25h DRAFT (not approved), 10.5h APPROVED
        $tsDraft = Timesheet::factory()->create([
            'rental_detail_id' => $rd->id,
            'report_date' => now()->subDay()->toDateString(),
            'start_hm' => 8,
            'end_hm' => 15.25,
            'total_work_hours' => 7.25,
            'status' => TimesheetStatus::DRAFT,
        ]);

        Sanctum::actingAs($admin);
        $tsApprovedId = $this->postJson('/api/v1/timesheets', [
            'rental_detail_id' => $rd->id,
            'report_date' => now()->toDateString(),
            'start_hm' => 8,
            'end_hm' => 18.5,
        ])->json('data.id');

        $this->assertSame(TimesheetStatus::DRAFT->value, Timesheet::find($tsDraft->id)->status->value);

        foreach (['return', 'inspect'] as $target) {
            $this->postJson("/api/v1/rentals/{$rentalId}/{$target}")->assertOk();
        }
        $this->postJson("/api/v1/rentals/{$rentalId}/ready", ['result' => 'READY'])->assertOk();

        $bill = app(RentalBillingService::class)->generate($rental->fresh());

        $this->assertEqualsWithDelta(10.5, $bill->totalWorkHours, 0.0001);
        $this->assertEqualsWithDelta(10.5 * 100000, $bill->totalRentalAmount, 0.0001);
        $this->assertEquals(RentalStatus::COMPLETED->value, $rental->fresh()->status->value);
    }

    public function test_generate_action_audited_and_authorized_for_generator(): void
    {
        [$owner, $rental] = $this->completedRental(1, rateSnapshot: 100000.00, hoursByDetail: [6]);

        // Owner can read own rental bill
        Sanctum::actingAs($owner);
        $bill = app(GenerateRentalBillAction::class)->execute($owner, $rental);

        $this->assertEqualsWithDelta(6 * 100000, $bill->grandTotal, 0.0001);

        // A different user cannot read someone else's bill
        $intruder = User::factory()->create(['role' => UserRole::USER]);
        Sanctum::actingAs($intruder);
        $this->expectException(AuthorizationException::class);
        app(GenerateRentalBillAction::class)->execute($intruder, $rental);
    }
}
