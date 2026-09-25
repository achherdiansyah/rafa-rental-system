<?php

namespace Tests\Unit\Services;

use App\Enums\BookingStatus;
use App\Enums\EquipmentStatus;
use App\Models\Booking;
use App\Models\BookingDetail;
use App\Models\EquipmentModel;
use App\Models\EquipmentType;
use App\Models\EquipmentUnit;
use App\Models\User;
use App\Services\Equipment\EquipmentAvailabilityService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EquipmentAvailabilityServiceTest extends TestCase
{
    use RefreshDatabase;

    protected EquipmentAvailabilityService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(EquipmentAvailabilityService::class);
    }

    public function test_get_availability_map_without_period_counts_currently_available_units(): void
    {
        $model = EquipmentModel::factory()->create(['is_active' => true]);

        // 2 AVAILABLE units
        EquipmentUnit::factory()->count(2)->create([
            'equipment_model_id' => $model->id,
            'status' => EquipmentStatus::AVAILABLE,
        ]);

        // 1 MAINTENANCE unit
        EquipmentUnit::factory()->create([
            'equipment_model_id' => $model->id,
            'status' => EquipmentStatus::MAINTENANCE,
        ]);

        // 1 ON_SITE unit
        EquipmentUnit::factory()->create([
            'equipment_model_id' => $model->id,
            'status' => EquipmentStatus::ON_SITE,
        ]);

        $map = $this->service->getAvailabilityMap([$model->id]);

        // 4 total physical units, but only 2 are in AVAILABLE status right now
        $this->assertEquals(2, $map->get($model->id));
        $this->assertTrue($this->service->isModelAvailable($model));
        $this->assertEquals(2, $this->service->getAvailableUnitsCount($model));
    }

    public function test_get_availability_map_with_period_subtracts_overlapping_bookings(): void
    {
        $user = User::factory()->create();
        $model = EquipmentModel::factory()->create(['is_active' => true]);

        // 3 operational units
        $units = EquipmentUnit::factory()->count(3)->create([
            'equipment_model_id' => $model->id,
            'status' => EquipmentStatus::AVAILABLE,
        ]);

        // Create booking overlapping Oct 10 to Oct 15
        $booking = Booking::factory()->create([
            'user_id' => $user->id,
            'status' => BookingStatus::CONFIRMED,
        ]);
        $detail = BookingDetail::factory()->create([
            'booking_id' => $booking->id,
            'equipment_model_id' => $model->id,
            'start_date' => '2026-10-10',
            'end_date' => '2026-10-15',
        ]);

        // Assign 2 units to this booking
        DB::table('booking_unit_assignments')->insert([
            [
                'booking_detail_id' => $detail->id,
                'equipment_unit_id' => $units[0]->id,
                'status' => 'ASSIGNED',
                'is_current' => true,
                'assigned_by' => $user->id,
            ],
            [
                'booking_detail_id' => $detail->id,
                'equipment_unit_id' => $units[1]->id,
                'status' => 'ASSIGNED',
                'is_current' => true,
                'assigned_by' => $user->id,
            ],
        ]);

        // Check availability strictly for Oct 12 to Oct 18 (OVERLAPS!)
        $mapOverlapping = $this->service->getAvailabilityMap(
            [$model->id],
            Carbon::parse('2026-10-12'),
            Carbon::parse('2026-10-18')
        );

        // 3 total units - 2 booked = 1 available
        $this->assertEquals(1, $mapOverlapping->get($model->id));

        // Check availability for Oct 20 to Oct 25 (NO OVERLAP)
        $mapNotOverlapping = $this->service->getAvailabilityMap(
            [$model->id],
            Carbon::parse('2026-10-20'),
            Carbon::parse('2026-10-25')
        );

        // 3 total units - 0 booked = 3 available
        $this->assertEquals(3, $mapNotOverlapping->get($model->id));
    }

    public function test_cancelled_or_rejected_bookings_do_not_reduce_availability(): void
    {
        $user = User::factory()->create();
        $model = EquipmentModel::factory()->create(['is_active' => true]);

        $unit = EquipmentUnit::factory()->create([
            'equipment_model_id' => $model->id,
            'status' => EquipmentStatus::AVAILABLE,
        ]);

        $booking = Booking::factory()->create([
            'user_id' => $user->id,
            'status' => BookingStatus::CANCELLED,
        ]);
        $detail = BookingDetail::factory()->create([
            'booking_id' => $booking->id,
            'equipment_model_id' => $model->id,
            'start_date' => '2026-10-10',
            'end_date' => '2026-10-15',
        ]);

        DB::table('booking_unit_assignments')->insert([
            'booking_detail_id' => $detail->id,
            'equipment_unit_id' => $unit->id,
            'status' => 'ASSIGNED',
            'is_current' => true,
            'assigned_by' => $user->id,
        ]);

        $map = $this->service->getAvailabilityMap(
            [$model->id],
            Carbon::parse('2026-10-10'),
            Carbon::parse('2026-10-15')
        );

        // 1 total unit - 0 booked (because booking is CANCELLED) = 1 available
        $this->assertEquals(1, $map->get($model->id));
    }

    public function test_read_only_availability_does_not_mutate_equipment_status(): void
    {
        $model = EquipmentModel::factory()->create(['is_active' => true]);
        $unit = EquipmentUnit::factory()->create([
            'equipment_model_id' => $model->id,
            'status' => EquipmentStatus::AVAILABLE,
        ]);

        $this->service->getAvailabilityMap([$model->id]);
        $this->service->isModelAvailable($model);
        $this->service->getAvailableUnitsCount($model);

        // Status remains unchanged
        $this->assertEquals(EquipmentStatus::AVAILABLE, $unit->fresh()->status);
    }
}
