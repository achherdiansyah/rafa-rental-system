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
use App\Models\Rental;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RentalLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Log::spy();
    }

    private function makeRental(User $admin): array
    {
        $user = User::factory()->create(['role' => UserRole::USER]);
        $location = ProjectLocation::factory()->create(['user_id' => $user->id]);
        $model = EquipmentModel::factory()->create(['is_active' => true]);
        $unit = EquipmentUnit::factory()->create([
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
            'quantity' => 1,
        ]);
        $assignment = BookingUnitAssignment::factory()->create([
            'booking_detail_id' => $detail->id,
            'equipment_unit_id' => $unit->id,
            'status' => AssignmentStatus::ASSIGNED,
            'is_current' => true,
            'assigned_by' => $admin->id,
        ]);

        Sanctum::actingAs($admin);
        $rentalId = $this->postJson('/api/v1/rentals', ['booking_id' => $booking->id])->json('data.id');

        return [$rentalId, $booking, $unit];
    }

    public function test_valid_dispatch_arrival_ongoing_sequence(): void
    {
        $admin = User::factory()->admin()->create();
        [$rentalId, $booking, $unit] = $this->makeRental($admin);

        Sanctum::actingAs($admin);

        // DISPATCHED: unit dikirim
        $this->postJson("/api/v1/rentals/{$rentalId}/dispatch")
            ->assertStatus(200)
            ->assertJson(['data' => ['status' => RentalStatus::DISPATCHED->value]]);
        $this->assertEquals(EquipmentStatus::MOBILIZING, $unit->fresh()->status);

        // ARRIVED: unit tiba di project
        $this->postJson("/api/v1/rentals/{$rentalId}/arrive")
            ->assertStatus(200)
            ->assertJson(['data' => ['status' => RentalStatus::ARRIVED->value]]);
        $this->assertEquals(EquipmentStatus::ON_SITE, $unit->fresh()->status);

        // ONGOING: pekerjaan dikonfirmasi mulai (operational start)
        $startRes = $this->postJson("/api/v1/rentals/{$rentalId}/start");
        $startRes->assertStatus(200)
            ->assertJson(['data' => ['status' => RentalStatus::ONGOING->value]]);
        $this->assertNotNull($startRes->json('data.started_at'));
        $this->assertEquals(EquipmentStatus::ON_SITE, $unit->fresh()->status);
    }

    public function test_dispatch_is_distinct_from_ongoing(): void
    {
        $admin = User::factory()->admin()->create();
        [$rentalId] = $this->makeRental($admin);

        Sanctum::actingAs($admin);

        // Dispatch only moves to DISPATCHED — NOT ONGOING
        $res = $this->postJson("/api/v1/rentals/{$rentalId}/dispatch");
        $res->assertStatus(200);
        $this->assertEquals(RentalStatus::DISPATCHED->value, $res->json('data.status'));
        $this->assertNull($res->json('data.started_at'));
    }

    public function test_illegal_transition_dispatch_repeat_is_rejected(): void
    {
        $admin = User::factory()->admin()->create();
        [$rentalId] = $this->makeRental($admin);

        Sanctum::actingAs($admin);

        $this->postJson("/api/v1/rentals/{$rentalId}/dispatch")->assertStatus(200);

        // Repeating a non-idempotent transition must be refused
        $this->postJson("/api/v1/rentals/{$rentalId}/dispatch")
            ->assertStatus(409)
            ->assertJson(['code' => 'INVALID_STATE_TRANSITION']);
    }

    public function test_ongoing_requires_previous_states(): void
    {
        $admin = User::factory()->admin()->create();
        [$rentalId] = $this->makeRental($admin);

        Sanctum::actingAs($admin);

        // ONGOING directly from ASSIGNED is illegal
        $this->postJson("/api/v1/rentals/{$rentalId}/start")
            ->assertStatus(409)
            ->assertJson(['code' => 'INVALID_STATE_TRANSITION']);

        // ARRIVED directly from ASSIGNED is illegal
        $this->postJson("/api/v1/rentals/{$rentalId}/arrive")
            ->assertStatus(409)
            ->assertJson(['code' => 'INVALID_STATE_TRANSITION']);
    }

    public function test_operational_start_requires_admin_confirmation(): void
    {
        $admin = User::factory()->admin()->create();
        [$rentalId] = $this->makeRental($admin);

        // Dispatch by admin
        Sanctum::actingAs($admin);
        $this->postJson("/api/v1/rentals/{$rentalId}/dispatch")->assertStatus(200);

        // USER (booking owner) cannot confirm ongoing / arrival
        $bookingOwner = $this->bookingOwner($rentalId);
        Sanctum::actingAs($bookingOwner);
        $this->postJson("/api/v1/rentals/{$rentalId}/start")->assertStatus(403);
        $this->postJson("/api/v1/rentals/{$rentalId}/arrive")->assertStatus(403);
    }

    public function test_lifecycle_transitions_are_audited(): void
    {
        $admin = User::factory()->admin()->create();
        [$rentalId] = $this->makeRental($admin);

        Sanctum::actingAs($admin);

        $this->postJson("/api/v1/rentals/{$rentalId}/dispatch")->assertStatus(200);
        $this->postJson("/api/v1/rentals/{$rentalId}/arrive")->assertStatus(200);

        Log::shouldHaveReceived('info')
            ->with(\Mockery::pattern('/RENTAL_DISPATCHED/'), \Mockery::type('array'));

        Log::shouldHaveReceived('info')
            ->with(\Mockery::pattern('/RENTAL_ARRIVED/'), \Mockery::type('array'));
    }

    private function bookingOwner(int $rentalId): User
    {
        return Rental::find($rentalId)->booking->user;
    }
}
