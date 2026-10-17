<?php

namespace Tests\Feature\Equipment;

use App\Enums\AssignmentStatus;
use App\Enums\BookingStatus;
use App\Enums\EquipmentStatus;
use App\Models\Booking;
use App\Models\BookingDetail;
use App\Models\BookingUnitAssignment;
use App\Models\EquipmentModel;
use App\Models\EquipmentUnit;
use App\Models\ProjectLocation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Catalog/detail availability status must reflect real booking/rental end dates,
 * handle multiple physical units, maintenance units, and honest unknown dates.
 */
class CatalogAvailabilityStatusTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $user;
    private EquipmentModel $model;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create();
        $this->user = User::factory()->create();
        $this->model = EquipmentModel::factory()->create(['is_active' => true]);
    }

    private function unit(EquipmentStatus $status = EquipmentStatus::AVAILABLE): EquipmentUnit
    {
        return EquipmentUnit::factory()->create([
            'equipment_model_id' => $this->model->id,
            'status' => $status,
        ]);
    }

    private function bookingWithAssignment(
        EquipmentUnit $unit,
        string $start,
        string $end,
        BookingStatus $status = BookingStatus::ONGOING
    ): Booking {
        $location = ProjectLocation::factory()->create(['user_id' => $this->user->id]);
        $booking = Booking::factory()->create([
            'user_id' => $this->user->id,
            'project_location_id' => $location->id,
            'status' => $status,
        ]);
        $detail = BookingDetail::factory()->create([
            'booking_id' => $booking->id,
            'equipment_model_id' => $this->model->id,
            'quantity' => 1,
            'start_date' => $start,
            'end_date' => $end,
        ]);
        BookingUnitAssignment::factory()->create([
            'booking_detail_id' => $detail->id,
            'equipment_unit_id' => $unit->id,
            'status' => AssignmentStatus::ASSIGNED,
            'is_current' => true,
            'assigned_by' => $this->admin->id,
        ]);

        return $booking;
    }

    public function test_active_rental_reports_rented_until_real_end_date(): void
    {
        $unit = $this->unit(EquipmentStatus::ON_SITE);
        $this->bookingWithAssignment($unit, now()->subDays(2)->toDateString(), '2026-11-15', BookingStatus::ONGOING);

        Sanctum::actingAs($this->user);
        $res = $this->getJson("/api/v1/equipment/models/{$this->model->id}")->assertOk();

        $res->assertJsonPath('data.availability_status', 'RENTED')
            ->assertJsonPath('data.rented_until', '2026-11-15')
            ->assertJsonPath('data.available_units_count', 0);
    }

    public function test_future_booking_conflict_uses_selected_period_end_date(): void
    {
        $unit = $this->unit(EquipmentStatus::ASSIGNED);
        $this->bookingWithAssignment(
            $unit,
            '2026-12-01',
            '2026-12-10',
            BookingStatus::CONFIRMED
        );

        Sanctum::actingAs($this->user);
        $res = $this->getJson(
            "/api/v1/equipment/models/{$this->model->id}?start_date=2026-12-02&end_date=2026-12-05"
        )->assertOk();

        $res->assertJsonPath('data.availability_status', 'RENTED')
            ->assertJsonPath('data.rented_until', '2026-12-10')
            ->assertJsonPath('data.available_units_count', 0);
    }

    public function test_multiple_units_with_one_free_stays_available(): void
    {
        $busy = $this->unit(EquipmentStatus::ON_SITE);
        $this->unit(EquipmentStatus::AVAILABLE);
        $this->bookingWithAssignment($busy, now()->subDay()->toDateString(), '2026-11-20', BookingStatus::ONGOING);

        Sanctum::actingAs($this->user);
        $res = $this->getJson("/api/v1/equipment/models/{$this->model->id}")->assertOk();

        $res->assertJsonPath('data.availability_status', 'AVAILABLE')
            ->assertJsonPath('data.available_units_count', 1)
            ->assertJsonPath('data.rented_until', null);
    }

    public function test_maintenance_unit_reports_maintenance_not_rented(): void
    {
        $this->unit(EquipmentStatus::MAINTENANCE);

        Sanctum::actingAs($this->user);
        $res = $this->getJson("/api/v1/equipment/models/{$this->model->id}")->assertOk();

        $res->assertJsonPath('data.availability_status', 'MAINTENANCE')
            ->assertJsonPath('data.rented_until', null)
            ->assertJsonPath('data.maintenance_units', 1);
    }

    public function test_unknown_unavailability_has_no_invented_date(): void
    {
        // Assigned unit without any committed booking (data anomaly) -> unknown.
        $this->unit(EquipmentStatus::ASSIGNED);

        Sanctum::actingAs($this->user);
        $res = $this->getJson("/api/v1/equipment/models/{$this->model->id}")->assertOk();

        $res->assertJsonPath('data.availability_status', 'UNAVAILABLE')
            ->assertJsonPath('data.rented_until', null);
    }

    public function test_extension_updates_rented_until(): void
    {
        $unit = $this->unit(EquipmentStatus::ON_SITE);
        $booking = $this->bookingWithAssignment($unit, now()->subDays(2)->toDateString(), '2026-11-10', BookingStatus::ONGOING);

        Sanctum::actingAs($this->user);
        $this->getJson("/api/v1/equipment/models/{$this->model->id}")
            ->assertJsonPath('data.rented_until', '2026-11-10');

        // Rental extended -> booking detail end date moves forward.
        $booking->details()->first()->update(['end_date' => '2026-11-25']);

        $this->getJson("/api/v1/equipment/models/{$this->model->id}")
            ->assertJsonPath('data.rented_until', '2026-11-25');
    }

    public function test_completed_rental_returns_to_available(): void
    {
        $unit = $this->unit(EquipmentStatus::ON_SITE);
        $booking = $this->bookingWithAssignment(
            $unit,
            now()->subDays(5)->toDateString(),
            now()->addDays(2)->toDateString(),
            BookingStatus::ONGOING
        );

        Sanctum::actingAs($this->user);
        $this->getJson("/api/v1/equipment/models/{$this->model->id}")
            ->assertJsonPath('data.availability_status', 'RENTED');

        // Rental completed & unit released by inspection.
        $booking->update(['status' => BookingStatus::COMPLETED]);
        $unit->update(['status' => EquipmentStatus::AVAILABLE]);

        $this->getJson("/api/v1/equipment/models/{$this->model->id}")
            ->assertJsonPath('data.availability_status', 'AVAILABLE')
            ->assertJsonPath('data.available_units_count', 1)
            ->assertJsonPath('data.rented_until', null);
    }
}
