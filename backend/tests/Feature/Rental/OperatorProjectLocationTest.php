<?php

namespace Tests\Feature\Rental;

use App\Enums\AssignmentStatus;
use App\Enums\BookingStatus;
use App\Enums\EquipmentStatus;
use App\Enums\RentalStatus;
use App\Enums\UserRole;
use App\Models\Booking;
use App\Models\BookingDetail;
use App\Models\BookingUnitAssignment;
use App\Models\CustomerProfile;
use App\Models\EquipmentModel;
use App\Models\EquipmentUnit;
use App\Models\ProjectLocation;
use App\Models\Rental;
use App\Models\RentalDetail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OperatorProjectLocationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{rental: Rental, detail: RentalDetail, location: ProjectLocation}
     */
    private function paidRentalWithLocation(?float $lat = -6.91474400, ?float $lng = 107.60981000): array
    {
        $admin = User::factory()->admin()->create();
        $customer = User::factory()->create(['role' => UserRole::USER]);
        CustomerProfile::factory()->verified()->create(['user_id' => $customer->id]);

        $location = ProjectLocation::factory()->create([
            'user_id' => $customer->id,
            'project_name' => 'Proyek Bendungan Leuwikeris',
            'address' => 'Kecamatan Ciamis, Kabupaten Ciamis',
            'city' => 'Ciamis',
            'latitude' => $lat,
            'longitude' => $lng,
        ]);

        $model = EquipmentModel::factory()->create(['is_active' => true]);
        $unit = EquipmentUnit::factory()->create([
            'equipment_model_id' => $model->id,
            'status' => EquipmentStatus::ASSIGNED,
        ]);

        $booking = Booking::factory()->create([
            'user_id' => $customer->id,
            'project_location_id' => $location->id,
            'status' => BookingStatus::CONFIRMED->value,
            'payment_met_at' => now(),
        ]);
        $bookingDetail = BookingDetail::factory()->create([
            'booking_id' => $booking->id,
            'equipment_model_id' => $model->id,
            'quantity' => 1,
        ]);
        $unitAssignment = BookingUnitAssignment::factory()->create([
            'booking_detail_id' => $bookingDetail->id,
            'equipment_unit_id' => $unit->id,
            'status' => AssignmentStatus::ASSIGNED,
            'is_current' => true,
            'assigned_by' => $admin->id,
        ]);
        $rental = Rental::factory()->create(['booking_id' => $booking->id, 'status' => RentalStatus::ASSIGNED->value]);
        $rentalDetail = RentalDetail::factory()->create([
            'rental_id' => $rental->id,
            'assignment_id' => $unitAssignment->id,
        ]);

        return ['rental' => $rental, 'detail' => $rentalDetail, 'location' => $location];
    }

    private function assign(User $admin, User $operator, Rental $rental, RentalDetail $detail): int
    {
        Sanctum::actingAs($admin);

        return (int) $this->postJson('/api/v1/admin/rentals/'.$rental->id.'/operator-assignments', [
            'rental_detail_id' => $detail->id,
            'operator_user_id' => $operator->id,
        ])->assertCreated()->json('data.id');
    }

    public function test_operator_sees_own_project_address_and_coordinates(): void
    {
        ['rental' => $rental, 'detail' => $detail] = $this->paidRentalWithLocation();
        $admin = User::factory()->admin()->create();
        $operator = User::factory()->operator()->create();
        $assignmentId = $this->assign($admin, $operator, $rental, $detail);

        Sanctum::actingAs($operator);
        $this->getJson('/api/v1/operator/assignments/'.$assignmentId)
            ->assertOk()
            ->assertJsonPath('data.project_location.project_name', 'Proyek Bendungan Leuwikeris')
            ->assertJsonPath('data.project_location.address', 'Kecamatan Ciamis, Kabupaten Ciamis')
            ->assertJsonPath('data.project_location.city', 'Ciamis')
            ->assertJsonPath('data.project_location.latitude', -6.914744)
            ->assertJsonPath('data.project_location.longitude', 107.60981);
    }

    public function test_missing_coordinates_are_not_fabricated(): void
    {
        ['rental' => $rental, 'detail' => $detail] = $this->paidRentalWithLocation(null, null);
        $admin = User::factory()->admin()->create();
        $operator = User::factory()->operator()->create();
        $assignmentId = $this->assign($admin, $operator, $rental, $detail);

        Sanctum::actingAs($operator);
        $this->getJson('/api/v1/operator/assignments/'.$assignmentId)
            ->assertOk()
            ->assertJsonPath('data.project_location.latitude', null)
            ->assertJsonPath('data.project_location.longitude', null)
            ->assertJsonPath('data.project_location.project_name', 'Proyek Bendungan Leuwikeris');
    }

    public function test_operator_cannot_access_other_operators_location(): void
    {
        ['rental' => $rental, 'detail' => $detail] = $this->paidRentalWithLocation();
        $admin = User::factory()->admin()->create();
        $operatorA = User::factory()->operator()->create();
        $operatorB = User::factory()->operator()->create();
        $assignmentId = $this->assign($admin, $operatorA, $rental, $detail);

        Sanctum::actingAs($operatorB);
        $this->getJson('/api/v1/operator/assignments/'.$assignmentId)->assertForbidden();
        $this->getJson('/api/v1/operator/assignments')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_replaced_operator_loses_and_new_operator_gains_location_access(): void
    {
        ['rental' => $rental, 'detail' => $detail] = $this->paidRentalWithLocation();
        $admin = User::factory()->admin()->create();
        $operatorA = User::factory()->operator()->create();
        $operatorB = User::factory()->operator()->create();
        $oldId = $this->assign($admin, $operatorA, $rental, $detail);

        Sanctum::actingAs($admin);
        $newId = (int) $this->postJson('/api/v1/admin/operator-assignments/'.$oldId.'/replace', [
            'operator_user_id' => $operatorB->id,
            'reason' => 'Rotasi operator lapangan.',
        ])->assertOk()->json('data.id');

        Sanctum::actingAs($operatorA);
        $this->getJson('/api/v1/operator/assignments/'.$oldId)->assertForbidden();
        $this->getJson('/api/v1/operator/assignments')->assertOk()->assertJsonCount(0, 'data');

        Sanctum::actingAs($operatorB);
        $this->getJson('/api/v1/operator/assignments/'.$newId)
            ->assertOk()
            ->assertJsonPath('data.project_location.city', 'Ciamis');
    }

    public function test_new_assignment_grants_access_only_after_saved(): void
    {
        ['rental' => $rental, 'detail' => $detail] = $this->paidRentalWithLocation();
        $admin = User::factory()->admin()->create();
        $operator = User::factory()->operator()->create();

        Sanctum::actingAs($operator);
        $this->getJson('/api/v1/operator/assignments')->assertOk()->assertJsonCount(0, 'data');

        $assignmentId = $this->assign($admin, $operator, $rental, $detail);

        Sanctum::actingAs($operator);
        $this->getJson('/api/v1/operator/assignments/'.$assignmentId)->assertOk();
    }

    public function test_operator_without_assignment_gets_empty_list(): void
    {
        Sanctum::actingAs(User::factory()->operator()->create());
        $this->getJson('/api/v1/operator/assignments')
            ->assertOk()
            ->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/operator/dashboard')
            ->assertOk()
            ->assertJsonPath('data.active_assignments', 0)
            ->assertJsonPath('data.active_rentals', 0);
    }

    public function test_admin_and_other_roles_cannot_use_operator_endpoints(): void
    {
        ['rental' => $rental, 'detail' => $detail] = $this->paidRentalWithLocation();
        $admin = User::factory()->admin()->create();
        $operator = User::factory()->operator()->create();
        $assignmentId = $this->assign($admin, $operator, $rental, $detail);

        Sanctum::actingAs($admin);
        $this->getJson('/api/v1/operator/assignments/'.$assignmentId)->assertForbidden();

        Sanctum::actingAs(User::factory()->create(['role' => UserRole::USER]));
        $this->getJson('/api/v1/operator/assignments')->assertForbidden();
    }

    public function test_admin_retains_location_access_via_rental_detail(): void
    {
        ['rental' => $rental] = $this->paidRentalWithLocation();
        $admin = User::factory()->admin()->create();

        Sanctum::actingAs($admin);
        $this->getJson('/api/v1/rentals/'.$rental->id)
            ->assertOk()
            ->assertJsonPath('data.booking.project_location.city', 'Ciamis')
            ->assertJsonPath('data.booking.project_location.latitude', -6.914744);
    }
}
