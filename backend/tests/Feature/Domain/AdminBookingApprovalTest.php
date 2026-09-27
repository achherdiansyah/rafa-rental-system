<?php

namespace Tests\Feature\Domain;

use App\Enums\AssignmentStatus;
use App\Enums\BookingStatus;
use App\Enums\EquipmentStatus;
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

class AdminBookingApprovalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Log::spy();
    }

    private function makeBooking(User $user, BookingStatus|string $status = BookingStatus::PENDING_APPROVAL, int $units = 2): Booking
    {
        $model = EquipmentModel::factory()->create(['is_active' => true]);
        EquipmentUnit::factory()->count($units)->create([
            'equipment_model_id' => $model->id,
            'status' => EquipmentStatus::AVAILABLE,
        ]);

        $location = ProjectLocation::factory()->create(['user_id' => $user->id]);
        $booking = Booking::factory()->create([
            'user_id' => $user->id,
            'project_location_id' => $location->id,
            'status' => $status instanceof BookingStatus ? $status->value : $status,
        ]);
        BookingDetail::factory()->create([
            'booking_id' => $booking->id,
            'equipment_model_id' => $model->id,
            'quantity' => 1,
            'start_date' => now()->addDays(5)->toDateString(),
            'end_date' => now()->addDays(9)->toDateString(),
        ]);

        return $booking->load('details');
    }

    public function test_admin_can_approve_pending_booking(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create(['role' => UserRole::USER]);
        $booking = $this->makeBooking($user);

        Sanctum::actingAs($admin);

        $response = $this->postJson("/api/v1/bookings/{$booking->id}/approve");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'status' => BookingStatus::APPROVED->value,
                ],
            ]);

        $this->assertEquals(BookingStatus::APPROVED, $booking->fresh()->status);
    }

    public function test_user_cannot_approve_or_reject_booking(): void
    {
        $user1 = User::factory()->create(['role' => UserRole::USER]);
        $booking = $this->makeBooking($user1);

        Sanctum::actingAs($user1);

        $this->postJson("/api/v1/bookings/{$booking->id}/approve")->assertStatus(403);
        $this->postJson("/api/v1/bookings/{$booking->id}/reject", [
            'rejection_reason' => 'Ketersediaan armada tidak memadai.',
        ])->assertStatus(403);
    }

    public function test_admin_can_reject_booking_with_reason(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create(['role' => UserRole::USER]);
        $booking = $this->makeBooking($user);

        Sanctum::actingAs($admin);

        $response = $this->postJson("/api/v1/bookings/{$booking->id}/reject", [
            'rejection_reason' => 'Unit mengalami perbaikan darurat di bengkel.',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'status' => BookingStatus::REJECTED->value,
                    'rejection_reason' => 'Unit mengalami perbaikan darurat di bengkel.',
                ],
            ]);
    }

    public function test_reject_requires_minimum_reason_length(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create(['role' => UserRole::USER]);
        $booking = $this->makeBooking($user);

        Sanctum::actingAs($admin);

        $this->postJson("/api/v1/bookings/{$booking->id}/reject", [
            'rejection_reason' => 'habis',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['rejection_reason']);
    }

    public function test_approve_fails_for_non_pending_booking(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create(['role' => UserRole::USER]);
        $booking = $this->makeBooking($user, BookingStatus::CONFIRMED);

        Sanctum::actingAs($admin);

        $this->postJson("/api/v1/bookings/{$booking->id}/approve")
            ->assertStatus(409)
            ->assertJson(['code' => 'INVALID_STATE_TRANSITION']);
    }

    public function test_admin_can_assign_physical_units_to_approved_booking(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create(['role' => UserRole::USER]);
        $booking = $this->makeBooking($user, BookingStatus::APPROVED, 3);

        $unit = EquipmentUnit::where('equipment_model_id', $booking->details->first()->equipment_model_id)
            ->where('status', EquipmentStatus::AVAILABLE)
            ->first();

        Sanctum::actingAs($admin);

        $response = $this->postJson("/api/v1/bookings/{$booking->id}/assign-units", [
            'assignments' => [[
                'booking_detail_id' => $booking->details->first()->id,
                'equipment_unit_id' => $unit->id,
            ]],
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('booking_unit_assignments', [
            'booking_detail_id' => $booking->details->first()->id,
            'equipment_unit_id' => $unit->id,
            'is_current' => true,
            'status' => AssignmentStatus::ASSIGNED->value,
            'assigned_by' => $admin->id,
        ]);

        $this->assertEquals(EquipmentStatus::ASSIGNED, $unit->fresh()->status);
    }

    public function test_assign_requires_exact_detail_quantity(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create(['role' => UserRole::USER]);
        $booking = $this->makeBooking($user, BookingStatus::APPROVED, 3);

        $detail = $booking->details->first(); // quantity = 1
        $unit = EquipmentUnit::where('equipment_model_id', $detail->equipment_model_id)
            ->where('status', EquipmentStatus::AVAILABLE)
            ->first();

        Sanctum::actingAs($admin);

        // Provide 2 units but detail needs exactly 1
        $unit2 = EquipmentUnit::where('equipment_model_id', $detail->equipment_model_id)
            ->where('status', EquipmentStatus::AVAILABLE)
            ->where('id', '!=', $unit->id)
            ->first();

        $response = $this->postJson("/api/v1/bookings/{$booking->id}/assign-units", [
            'assignments' => [
                ['booking_detail_id' => $detail->id, 'equipment_unit_id' => $unit->id],
                ['booking_detail_id' => $detail->id, 'equipment_unit_id' => $unit2->id],
            ],
        ]);

        $response->assertStatus(409)
            ->assertJson(['code' => 'BUSINESS_RULE_VIOLATION']);
    }

    public function test_assign_rejects_unavailable_unit(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create(['role' => UserRole::USER]);
        $booking = $this->makeBooking($user, BookingStatus::APPROVED, 2);

        $detail = $booking->details->first();
        $units = EquipmentUnit::where('equipment_model_id', $detail->equipment_model_id)->get();

        // Pre-commit one unit to another booking (overlapping period)
        $other = User::factory()->create(['role' => UserRole::USER]);
        $otherBooking = Booking::factory()->create(['user_id' => $other->id, 'status' => BookingStatus::CONFIRMED]);
        $otherDetail = BookingDetail::factory()->create([
            'booking_id' => $otherBooking->id,
            'equipment_model_id' => $detail->equipment_model_id,
            'start_date' => $detail->start_date->toDateString(),
            'end_date' => $detail->end_date->toDateString(),
        ]);
        BookingUnitAssignment::factory()->create([
            'booking_detail_id' => $otherDetail->id,
            'equipment_unit_id' => $units[0]->id,
            'is_current' => true,
            'assigned_by' => $admin->id,
        ]);
        $units[0]->update(['status' => EquipmentStatus::ASSIGNED]);

        Sanctum::actingAs($admin);

        // Attempt to assign the already-committed unit -> conflict
        $response = $this->postJson("/api/v1/bookings/{$booking->id}/assign-units", [
            'assignments' => [[
                'booking_detail_id' => $detail->id,
                'equipment_unit_id' => $units[0]->id,
            ]],
        ]);

        $response->assertStatus(409)
            ->assertJson(['code' => 'BUSINESS_RULE_VIOLATION']);
    }

    public function test_assign_rejects_unit_of_wrong_model(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create(['role' => UserRole::USER]);
        $booking = $this->makeBooking($user, BookingStatus::APPROVED, 1);

        // A unit of a different model
        $otherModel = EquipmentModel::factory()->create(['is_active' => true]);
        $wrongUnit = EquipmentUnit::factory()->create(['equipment_model_id' => $otherModel->id]);

        Sanctum::actingAs($admin);

        $response = $this->postJson("/api/v1/bookings/{$booking->id}/assign-units", [
            'assignments' => [[
                'booking_detail_id' => $booking->details->first()->id,
                'equipment_unit_id' => $wrongUnit->id,
            ]],
        ]);

        $response->assertStatus(409)
            ->assertJson(['code' => 'BUSINESS_RULE_VIOLATION']);
    }

    public function test_non_admin_cannot_assign_units(): void
    {
        $owner = User::factory()->owner()->create();
        $user = User::factory()->create(['role' => UserRole::USER]);
        $booking = $this->makeBooking($user, BookingStatus::APPROVED, 1);

        $unit = EquipmentUnit::where('equipment_model_id', $booking->details->first()->equipment_model_id)
            ->where('status', EquipmentStatus::AVAILABLE)
            ->first();

        // OWNER is not allowed to assign units (admin-exclusive)
        Sanctum::actingAs($owner);
        $this->postJson("/api/v1/bookings/{$booking->id}/assign-units", [
            'assignments' => [[
                'booking_detail_id' => $booking->details->first()->id,
                'equipment_unit_id' => $unit->id,
            ]],
        ])->assertStatus(403);
    }

    public function test_replacement_keeps_assignment_history(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create(['role' => UserRole::USER]);

        $model = EquipmentModel::factory()->create(['is_active' => true]);
        $unitA = EquipmentUnit::factory()->create(['equipment_model_id' => $model->id, 'status' => EquipmentStatus::AVAILABLE]);
        $unitB = EquipmentUnit::factory()->create(['equipment_model_id' => $model->id, 'status' => EquipmentStatus::AVAILABLE]);

        $location = ProjectLocation::factory()->create(['user_id' => $user->id]);
        $booking = Booking::factory()->create([
            'user_id' => $user->id,
            'project_location_id' => $location->id,
            'status' => BookingStatus::APPROVED,
        ]);
        $detail = BookingDetail::factory()->create([
            'booking_id' => $booking->id,
            'equipment_model_id' => $model->id,
            'quantity' => 1,
            'start_date' => now()->addDays(5)->toDateString(),
            'end_date' => now()->addDays(9)->toDateString(),
        ]);

        // Initial assignment unitA
        $assignment = BookingUnitAssignment::factory()->create([
            'booking_detail_id' => $detail->id,
            'equipment_unit_id' => $unitA->id,
            'status' => AssignmentStatus::ASSIGNED,
            'is_current' => true,
            'assigned_by' => $admin->id,
        ]);
        $unitA->update(['status' => EquipmentStatus::ASSIGNED]);

        Sanctum::actingAs($admin);

        $response = $this->postJson("/api/v1/bookings/{$booking->id}/assignments/{$assignment->id}/replace", [
            'new_equipment_unit_id' => $unitB->id,
            'reason' => 'Mesin rusak pra-kirim, diganti unit cadangan.',
        ]);

        $response->assertStatus(200);

        // History preserved: old assignment retired, new one current
        $this->assertEquals(false, $assignment->fresh()->is_current);
        $this->assertEquals(AssignmentStatus::REPLACED, $assignment->fresh()->status);

        $new = BookingUnitAssignment::where('booking_detail_id', $detail->id)->where('is_current', true)->first();
        $this->assertEquals($unitB->id, $new->equipment_unit_id);

        // Old unit to MAINTENANCE, new unit ASSIGNED
        $this->assertEquals(EquipmentStatus::MAINTENANCE, $unitA->fresh()->status);
        $this->assertEquals(EquipmentStatus::ASSIGNED, $unitB->fresh()->status);
    }
}
