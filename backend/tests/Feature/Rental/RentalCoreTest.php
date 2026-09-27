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
use App\Models\EquipmentModel;
use App\Models\EquipmentUnit;
use App\Models\ProjectLocation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RentalCoreTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Log::spy();
    }

    private function confirmedBookingWithUnits(int $units = 2): array
    {
        $user = User::factory()->create(['role' => UserRole::USER]);
        $admin = User::factory()->admin()->create();
        $location = ProjectLocation::factory()->create(['user_id' => $user->id]);

        $model = EquipmentModel::factory()->create(['is_active' => true]);
        $unitList = EquipmentUnit::factory()->count($units)->create([
            'equipment_model_id' => $model->id,
            'status' => EquipmentStatus::ASSIGNED,
        ]);

        $booking = Booking::factory()->create([
            'user_id' => $user->id,
            'project_location_id' => $location->id,
            'status' => BookingStatus::CONFIRMED,
        ]);
        $detail = BookingDetail::factory()->create([
            'booking_id' => $booking->id,
            'equipment_model_id' => $model->id,
            'quantity' => $units,
            'start_date' => now()->addDays(3)->toDateString(),
            'end_date' => now()->addDays(9)->toDateString(),
        ]);

        $assignments = [];
        foreach ($unitList as $unit) {
            $assignments[] = BookingUnitAssignment::factory()->create([
                'booking_detail_id' => $detail->id,
                'equipment_unit_id' => $unit->id,
                'status' => AssignmentStatus::ASSIGNED,
                'is_current' => true,
                'assigned_by' => $admin->id,
            ]);
        }

        return [$booking, $assignments, $unitList];
    }

    public function test_admin_can_create_rental_from_confirmed_booking(): void
    {
        $admin = User::factory()->admin()->create();
        [$booking, $assignments] = $this->confirmedBookingWithUnits(2);

        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/v1/rentals', ['booking_id' => $booking->id]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'booking_id',
                    'status',
                    'details' => ['*' => ['id', 'assignment_id', 'status', 'unit' => ['id', 'serial_number', 'status']]],
                ],
            ]);

        $this->assertEquals(RentalStatus::ASSIGNED->value, $response->json('data.status'));
        $this->assertCount(2, $response->json('data.details'));

        $detail = $response->json('data.details.0');
        $this->assertEquals($assignments[0]->id, $detail['assignment_id']);
        $this->assertEquals($assignments[0]->unit->id, $detail['unit']['id']);
    }

    public function test_rental_creation_requires_confirmed_booking(): void
    {
        $admin = User::factory()->admin()->create();
        [$booking] = $this->confirmedBookingWithUnits(1);
        $booking->update(['status' => BookingStatus::APPROVED]);

        Sanctum::actingAs($admin);

        $this->postJson('/api/v1/rentals', ['booking_id' => $booking->id])
            ->assertStatus(409)
            ->assertJson(['code' => 'BUSINESS_RULE_VIOLATION']);
    }

    public function test_rental_creation_rejects_booking_without_assignments(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create(['role' => UserRole::USER]);
        $location = ProjectLocation::factory()->create(['user_id' => $user->id]);
        $booking = Booking::factory()->create([
            'user_id' => $user->id,
            'project_location_id' => $location->id,
            'status' => BookingStatus::CONFIRMED,
        ]);

        Sanctum::actingAs($admin);

        $this->postJson('/api/v1/rentals', ['booking_id' => $booking->id])
            ->assertStatus(409)
            ->assertJson(['code' => 'BUSINESS_RULE_VIOLATION']);
        $this->assertStringContainsString('belum dialokasikan', $this->postJson('/api/v1/rentals', ['booking_id' => $booking->id])->json('message'));
    }

    public function test_rental_duplicate_creation_is_blocked(): void
    {
        $admin = User::factory()->admin()->create();
        [$booking] = $this->confirmedBookingWithUnits(1);

        Sanctum::actingAs($admin);

        $this->postJson('/api/v1/rentals', ['booking_id' => $booking->id])->assertStatus(201);
        $this->postJson('/api/v1/rentals', ['booking_id' => $booking->id])
            ->assertStatus(409)
            ->assertJson(['code' => 'BUSINESS_RULE_VIOLATION']);
    }

    public function test_full_rental_state_machine_updates_unit_statuses(): void
    {
        $admin = User::factory()->admin()->create();
        [$booking, $assignments, $units] = $this->confirmedBookingWithUnits(1);

        Sanctum::actingAs($admin);

        $rentalId = $this->postJson('/api/v1/rentals', ['booking_id' => $booking->id])->json('data.id');
        $unit = $units[0];

        // DISPATCHED -> unit MOBILIZING
        $this->postJson("/api/v1/rentals/{$rentalId}/dispatch")->assertStatus(200);
        $this->assertEquals(EquipmentStatus::MOBILIZING, $unit->fresh()->status);

        // ARRIVED -> unit ON_SITE
        $this->postJson("/api/v1/rentals/{$rentalId}/arrive")->assertStatus(200);
        $this->assertEquals(EquipmentStatus::ON_SITE, $unit->fresh()->status);

        // START (ONGOING) -> started_at set
        $startRes = $this->postJson("/api/v1/rentals/{$rentalId}/start");
        $startRes->assertStatus(200)->assertJson(['data' => ['status' => RentalStatus::ONGOING->value]]);
        $this->assertNotNull($startRes->json('data.started_at'));

        // RETURN (DEMOBILIZING) -> unit DEMOBILIZING
        $this->postJson("/api/v1/rentals/{$rentalId}/return")->assertStatus(200);
        $this->assertEquals(EquipmentStatus::DEMOBILIZING, $unit->fresh()->status);

        // INSPECT (RETURN_INSPECTED) -> unit RETURN_INSPECTION
        $this->postJson("/api/v1/rentals/{$rentalId}/inspect")->assertStatus(200);
        $this->assertEquals(EquipmentStatus::RETURN_INSPECTION, $unit->fresh()->status);

        // COMPLETE -> unit AVAILABLE, completed_at set
        $completeRes = $this->postJson("/api/v1/rentals/{$rentalId}/complete");
        $completeRes->assertStatus(200)->assertJson(['data' => ['status' => RentalStatus::COMPLETED->value]]);
        $this->assertNotNull($completeRes->json('data.completed_at'));
        $this->assertEquals(EquipmentStatus::AVAILABLE, $unit->fresh()->status);
    }

    public function test_invalid_rental_transition_is_rejected(): void
    {
        $admin = User::factory()->admin()->create();
        [$booking] = $this->confirmedBookingWithUnits(1);

        Sanctum::actingAs($admin);

        $rentalId = $this->postJson('/api/v1/rentals', ['booking_id' => $booking->id])->json('data.id');

        // Cannot skip DISPATCHED and jump straight to ONGOING
        $this->postJson("/api/v1/rentals/{$rentalId}/start")
            ->assertStatus(409)
            ->assertJson(['code' => 'INVALID_STATE_TRANSITION']);
    }

    public function test_user_can_view_own_rental_but_not_operate(): void
    {
        $admin = User::factory()->admin()->create();
        [$booking, $assignments] = $this->confirmedBookingWithUnits(1);
        $owner = $booking->user;

        Sanctum::actingAs($admin);
        $rentalId = $this->postJson('/api/v1/rentals', ['booking_id' => $booking->id])->json('data.id');

        // Owner (USER) can view own rental
        Sanctum::actingAs($owner);
        $this->getJson("/api/v1/rentals/{$rentalId}")->assertStatus(200);

        // Owner cannot dispatch
        $this->postJson("/api/v1/rentals/{$rentalId}/dispatch")->assertStatus(403);
    }

    public function test_user_cannot_view_other_users_rental(): void
    {
        $admin = User::factory()->admin()->create();
        [$booking] = $this->confirmedBookingWithUnits(1);

        Sanctum::actingAs($admin);
        $rentalId = $this->postJson('/api/v1/rentals', ['booking_id' => $booking->id])->json('data.id');

        $intruder = User::factory()->create(['role' => UserRole::USER]);
        Sanctum::actingAs($intruder);
        $this->getJson("/api/v1/rentals/{$rentalId}")->assertStatus(403);
    }

    public function test_owner_has_read_access_list(): void
    {
        $admin = User::factory()->admin()->create();
        $owner = User::factory()->owner()->create();
        [$booking] = $this->confirmedBookingWithUnits(1);

        Sanctum::actingAs($admin);
        $this->postJson('/api/v1/rentals', ['booking_id' => $booking->id])->assertStatus(201);

        Sanctum::actingAs($owner);
        $this->getJson('/api/v1/rentals')->assertStatus(200);
    }
}
