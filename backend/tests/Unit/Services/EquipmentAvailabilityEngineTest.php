<?php

namespace Tests\Unit\Services;

use App\Enums\BookingStatus;
use App\Enums\EquipmentStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Booking;
use App\Models\BookingDetail;
use App\Models\BookingUnitAssignment;
use App\Models\EquipmentModel;
use App\Models\EquipmentUnit;
use App\Models\User;
use App\Services\Equipment\EquipmentAvailabilityService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class EquipmentAvailabilityEngineTest extends TestCase
{
    use RefreshDatabase;

    protected EquipmentAvailabilityService $service;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Log::spy();
        $this->service = app(EquipmentAvailabilityService::class);
        $this->user = User::factory()->create();
    }

    private function createModel(int $units = 2): EquipmentModel
    {
        $model = EquipmentModel::factory()->create(['is_active' => true]);
        EquipmentUnit::factory()->count($units)->create([
            'equipment_model_id' => $model->id,
            'status' => EquipmentStatus::AVAILABLE,
        ]);

        return $model;
    }

    private function assign(int $unitId, int $modelId, string $start, string $end, string $status = 'CONFIRMED'): void
    {
        // Create booking + detail + current assignment (mirrors committed topology)
        $booking = Booking::factory()->create(['user_id' => $this->user->id, 'status' => $status]);
        $detail = BookingDetail::factory()->create([
            'booking_id' => $booking->id,
            'equipment_model_id' => $modelId,
            'start_date' => $start,
            'end_date' => $end,
        ]);

        BookingUnitAssignment::factory()->create([
            'booking_detail_id' => $detail->id,
            'equipment_unit_id' => $unitId,
            'is_current' => true,
            'assigned_by' => $this->user->id,
        ]);
    }

    public function test_available_unit_is_candidate(): void
    {
        $model = $this->createModel(2);
        $unit = $model->units()->first();

        $candidates = $this->service->getCandidateUnits(
            $model->id,
            Carbon::parse('2026-11-01'),
            Carbon::parse('2026-11-05')
        );

        $this->assertCount(2, $candidates);
        $this->assertTrue($this->service->isUnitAvailable($unit, Carbon::parse('2026-11-01'), Carbon::parse('2026-11-05')));
        $this->assertEquals(2, $this->service->getAvailableUnitsCount($model, Carbon::parse('2026-11-01'), Carbon::parse('2026-11-05')));
    }

    public function test_overlapping_booking_reduces_availability(): void
    {
        $model = $this->createModel(2);
        $unit = $model->units()->first();

        $this->assign($unit->id, $model->id, '2026-11-02', '2026-11-06');

        $candidates = $this->service->getCandidateUnits(
            $model->id,
            Carbon::parse('2026-11-01'),
            Carbon::parse('2026-11-05')
        );

        $this->assertCount(1, $candidates);
        $this->assertFalse($this->service->isUnitAvailable($unit, Carbon::parse('2026-11-01'), Carbon::parse('2026-11-05')));
    }

    public function test_non_overlapping_booking_keeps_unit_available(): void
    {
        $model = $this->createModel(1);
        $unit = $model->units()->first();

        // Committed Nov 02-06; buffer (3+2) frees the unit from Nov 12
        $this->assign($unit->id, $model->id, '2026-11-02', '2026-11-06');

        $this->assertTrue(
            $this->service->isUnitAvailable($unit, Carbon::parse('2026-11-14'), Carbon::parse('2026-11-20'))
        );
    }

    public function test_buffer_conflict_blocks_booking_inside_post_buffer_window(): void
    {
        $model = $this->createModel(1);
        $unit = $model->units()->first();

        // Committed until Nov 06. Buffer occupies Nov 07-11 (3 ext + 2 insp).
        $this->assign($unit->id, $model->id, '2026-11-02', '2026-11-06');

        // Booking starting inside the buffer must be blocked
        $this->assertFalse(
            $this->service->isUnitAvailable($unit, Carbon::parse('2026-11-09'), Carbon::parse('2026-11-14'))
        );

        // Booking starting exactly on the release day (Nov 12) is free
        $this->assertTrue(
            $this->service->isUnitAvailable($unit, Carbon::parse('2026-11-12'), Carbon::parse('2026-11-14'))
        );
    }

    public function test_expired_booking_releases_capacity(): void
    {
        $model = $this->createModel(1);
        $unit = $model->units()->first();

        $this->assign($unit->id, $model->id, '2026-11-02', '2026-11-06', BookingStatus::EXPIRED->value);

        $this->assertTrue(
            $this->service->isUnitAvailable($unit, Carbon::parse('2026-11-02'), Carbon::parse('2026-11-06'))
        );
    }

    public function test_maintenance_or_damaged_unit_excluded_from_candidates(): void
    {
        $model = EquipmentModel::factory()->create(['is_active' => true]);

        EquipmentUnit::factory()->create([
            'equipment_model_id' => $model->id,
            'status' => EquipmentStatus::MAINTENANCE,
        ]);
        EquipmentUnit::factory()->create([
            'equipment_model_id' => $model->id,
            'status' => EquipmentStatus::RETURN_INSPECTION,
        ]);
        EquipmentUnit::factory()->create([
            'equipment_model_id' => $model->id,
            'status' => EquipmentStatus::DECOMMISSIONED,
        ]);
        EquipmentUnit::factory()->create([
            'equipment_model_id' => $model->id,
            'status' => EquipmentStatus::AVAILABLE,
        ]);

        $candidates = $this->service->getCandidateUnits(
            $model->id,
            Carbon::parse('2026-11-01'),
            Carbon::parse('2026-11-05')
        );

        $this->assertCount(1, $candidates);

        $map = $this->service->getAvailabilityMap(
            [$model->id],
            Carbon::parse('2026-11-01'),
            Carbon::parse('2026-11-05')
        );
        $this->assertEquals(1, $map->get($model->id));
    }

    public function test_pessimistic_lock_prevents_double_allocation_within_concurrent_transactions(): void
    {
        $model = $this->createModel(1); // exactly 1 physical unit

        $periodStart = Carbon::parse('2026-12-01');
        $periodEnd = Carbon::parse('2026-12-05');

        // Transaction A: locks the single available unit, records the committed
        // assignment (mirrors real approve/assign flow), and marks unit ASSIGNED.
        DB::transaction(function () use ($model, $periodStart, $periodEnd) {
            $locked = $this->service->lockAvailableUnits($model->id, $periodStart, $periodEnd, 1);
            $this->assertCount(1, $locked);

            /** @var EquipmentUnit $unit */
            $unit = $locked->first();
            $unit->update(['status' => EquipmentStatus::ASSIGNED]);

            $booking = Booking::factory()->create([
                'user_id' => $this->user->id,
                'status' => BookingStatus::CONFIRMED,
            ]);
            $detail = BookingDetail::factory()->create([
                'booking_id' => $booking->id,
                'equipment_model_id' => $model->id,
                'start_date' => $periodStart->toDateString(),
                'end_date' => $periodEnd->toDateString(),
            ]);
            BookingUnitAssignment::factory()->create([
                'booking_detail_id' => $detail->id,
                'equipment_unit_id' => $unit->id,
                'is_current' => true,
                'assigned_by' => $this->user->id,
            ]);

            // No other unit of this model is free within the same transaction
            $this->assertCount(0, $this->service->getCandidateUnits($model->id, $periodStart, $periodEnd));
        });

        // Even after commit, the unit is no longer AVAILABLE for a second allocation
        $this->assertEquals(0, $this->service->getAvailableUnitsCount($model, $periodStart, $periodEnd));

        // Attempting a second lock for quantity 1 must throw
        $this->expectException(BusinessRuleException::class);
        DB::transaction(function () use ($model, $periodStart, $periodEnd) {
            $this->service->lockAvailableUnits($model->id, $periodStart, $periodEnd, 1);
        });
    }

    public function test_unit_replacement_releases_old_unit_and_allocates_upgraded_one(): void
    {
        $model = $this->createModel(2); // unitA + unitB
        [$unitA, $unitB] = $model->units()->orderBy('id')->get()->all();

        $periodStart = Carbon::parse('2026-12-01');
        $periodEnd = Carbon::parse('2026-12-05');

        // Initial assignment on unitA
        $this->assign($unitA->id, $model->id, '2026-12-01', '2026-12-05');

        // Replacement process: retire old assignment (is_current = false), unitA to MAINTENANCE
        $assignment = BookingUnitAssignment::where('equipment_unit_id', $unitA->id)->where('is_current', true)->first();
        $assignment->update(['is_current' => false, 'replaced_reason' => 'Mesin rusak pra-kirim']);
        $unitA->update(['status' => EquipmentStatus::MAINTENANCE]);

        // unitB should now be allocatable for the same period
        $candidates = $this->service->getCandidateUnits($model->id, $periodStart, $periodEnd);
        $this->assertTrue($candidates->contains(fn ($c) => $c->id === $unitB->id));
        $this->assertFalse($candidates->contains(fn ($c) => $c->id === $unitA->id));
    }

    public function test_reschedule_recalculates_availability_and_rejects_conflict(): void
    {
        $model = $this->createModel(2);
        [$unitA, $unitB] = $model->units()->orderBy('id')->get()->all();

        // Original committed window on both units Dec 01-05
        $this->assign($unitA->id, $model->id, '2026-12-01', '2026-12-05');
        $this->assign($unitB->id, $model->id, '2026-12-01', '2026-12-05');

        // Reschedule candidate: entire fleet already committed Dec 01-05
        $this->assertEquals(0, $this->service->getAvailableUnitsCount($model, Carbon::parse('2026-12-01'), Carbon::parse('2026-12-05')));

        // Reschedule to a brand-new far window with zero current commitments is free
        $this->assertEquals(2, $this->service->getAvailableUnitsCount($model, Carbon::parse('2027-01-10'), Carbon::parse('2027-01-15')));
    }
}
