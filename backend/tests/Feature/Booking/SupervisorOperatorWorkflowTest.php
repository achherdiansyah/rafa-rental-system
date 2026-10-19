<?php

namespace Tests\Feature\Booking;

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
use App\Models\RentalOperatorAssignment;
use App\Models\Timesheet;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SupervisorOperatorWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function customer(): User
    {
        $user = User::factory()->create(['role' => UserRole::USER]);
        CustomerProfile::factory()->verified()->create(['user_id' => $user->id]);

        return $user;
    }

    private function booking(User $user, BookingStatus $status = BookingStatus::PENDING_APPROVAL): Booking
    {
        return Booking::factory()->create([
            'user_id' => $user->id,
            'project_location_id' => ProjectLocation::factory()->create(['user_id' => $user->id])->id,
            'status' => $status->value,
        ]);
    }

    /**
     * @return array{0: Rental, 1: RentalDetail, 2: Booking, 3: EquipmentUnit}
     */
    private function paidRental(): array
    {
        $admin = User::factory()->admin()->create();
        $customer = $this->customer();
        $model = EquipmentModel::factory()->create(['is_active' => true]);
        $unit = EquipmentUnit::factory()->create([
            'equipment_model_id' => $model->id,
            'status' => EquipmentStatus::ASSIGNED,
        ]);

        $booking = Booking::factory()->create([
            'user_id' => $customer->id,
            'project_location_id' => ProjectLocation::factory()->create(['user_id' => $customer->id])->id,
            'status' => BookingStatus::CONFIRMED->value,
            'payment_met_at' => now(),
        ]);
        $detail = BookingDetail::factory()->create([
            'booking_id' => $booking->id,
            'equipment_model_id' => $model->id,
            'quantity' => 1,
            'start_date' => now()->addDays(2)->toDateString(),
            'end_date' => now()->addDays(5)->toDateString(),
        ]);
        $unitAssignment = BookingUnitAssignment::factory()->create([
            'booking_detail_id' => $detail->id,
            'equipment_unit_id' => $unit->id,
            'status' => AssignmentStatus::ASSIGNED,
            'is_current' => true,
            'assigned_by' => $admin->id,
        ]);

        $rental = Rental::factory()->create([
            'booking_id' => $booking->id,
            'status' => RentalStatus::ASSIGNED->value,
        ]);
        $rentalDetail = RentalDetail::factory()->create([
            'rental_id' => $rental->id,
            'assignment_id' => $unitAssignment->id,
            'status' => RentalStatus::ASSIGNED->value,
        ]);

        return [$rental, $rentalDetail, $booking, $unit];
    }

    public function test_all_five_roles_can_login(): void
    {
        foreach (UserRole::cases() as $role) {
            User::factory()->create([
                'email' => strtolower($role->value).'@example.com',
                'password' => 'password',
                'role' => $role->value,
            ]);
        }

        foreach (UserRole::cases() as $role) {
            $this->postJson('/api/v1/auth/login', [
                'email' => strtolower($role->value).'@example.com',
                'password' => 'password',
            ])->assertOk()->assertJsonPath('data.user.role', $role->value);
        }
    }

    public function test_supervisor_validates_and_admin_can_then_approve(): void
    {
        $admin = User::factory()->admin()->create();
        $supervisor = User::factory()->supervisor()->create();
        $booking = $this->booking($this->customer());

        Sanctum::actingAs($supervisor);
        $this->getJson('/api/v1/supervisor/bookings')
            ->assertOk()
            ->assertJsonPath('data.0.id', $booking->id);
        $this->postJson('/api/v1/supervisor/bookings/'.$booking->id.'/validate', [
            'notes' => 'Data booking sesuai.',
        ])->assertOk()
            ->assertJsonPath('data.supervisor_decision', 'VALIDATED');

        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'supervisor_user_id' => $supervisor->id,
            'supervisor_decision' => 'VALIDATED',
        ]);

        Sanctum::actingAs($admin);
        $this->postJson('/api/v1/bookings/'.$booking->id.'/approve')
            ->assertOk()
            ->assertJsonPath('data.status', BookingStatus::APPROVED->value);
    }

    public function test_admin_cannot_approve_before_supervisor_validation(): void
    {
        $admin = User::factory()->admin()->create();
        $booking = $this->booking($this->customer());

        Sanctum::actingAs($admin);
        $this->postJson('/api/v1/bookings/'.$booking->id.'/approve')
            ->assertStatus(409)
            ->assertJsonPath('code', 'INVALID_STATE_TRANSITION');
    }

    public function test_return_for_revision_requires_notes_and_allows_resubmission(): void
    {
        $admin = User::factory()->admin()->create();
        $supervisor = User::factory()->supervisor()->create();
        $customer = $this->customer();
        $booking = $this->booking($customer);

        Sanctum::actingAs($supervisor);
        $this->postJson('/api/v1/supervisor/bookings/'.$booking->id.'/return', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['notes']);

        $this->postJson('/api/v1/supervisor/bookings/'.$booking->id.'/return', [
            'notes' => 'Lokasi proyek perlu dilengkapi.',
        ])->assertOk()
            ->assertJsonPath('data.supervisor_decision', 'REVISION');

        Sanctum::actingAs($admin);
        $this->postJson('/api/v1/bookings/'.$booking->id.'/approve')->assertStatus(409);

        Sanctum::actingAs($customer);
        $this->postJson('/api/v1/bookings/'.$booking->id.'/submit')->assertOk();

        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'status' => BookingStatus::PENDING_APPROVAL->value,
            'supervisor_decision' => null,
            'supervisor_validated_at' => null,
        ]);

        Sanctum::actingAs($admin);
        $this->postJson('/api/v1/bookings/'.$booking->id.'/approve')->assertStatus(409);

        Sanctum::actingAs($supervisor);
        $this->postJson('/api/v1/supervisor/bookings/'.$booking->id.'/validate')->assertOk();

        Sanctum::actingAs($admin);
        $this->postJson('/api/v1/bookings/'.$booking->id.'/approve')->assertOk();
    }

    public function test_user_can_correct_revision_booking_then_resubmit(): void
    {
        $supervisor = User::factory()->supervisor()->create();
        $customer = $this->customer();
        $model = EquipmentModel::factory()->create(['is_active' => true]);
        EquipmentUnit::factory()->count(2)->create([
            'equipment_model_id' => $model->id,
            'status' => EquipmentStatus::AVAILABLE,
        ]);
        $booking = Booking::factory()->create([
            'user_id' => $customer->id,
            'project_location_id' => ProjectLocation::factory()->create(['user_id' => $customer->id])->id,
            'status' => BookingStatus::PENDING_APPROVAL->value,
        ]);
        $detail = BookingDetail::factory()->create([
            'booking_id' => $booking->id,
            'equipment_model_id' => $model->id,
            'quantity' => 1,
            'start_date' => now()->addDays(2)->toDateString(),
            'end_date' => now()->addDays(5)->toDateString(),
        ]);

        Sanctum::actingAs($customer);
        $this->putJson('/api/v1/bookings/'.$booking->id, [
            'details' => [[
                'id' => $detail->id,
                'start_date' => now()->addDays(10)->toDateString(),
                'end_date' => now()->addDays(13)->toDateString(),
            ]],
        ])->assertForbidden();

        Sanctum::actingAs($supervisor);
        $this->postJson('/api/v1/supervisor/bookings/'.$booking->id.'/return', [
            'notes' => 'Tanggal pelaksanaan perlu diubah.',
        ])->assertOk();

        $newLocation = ProjectLocation::factory()->create(['user_id' => $customer->id]);
        Sanctum::actingAs($customer);
        $this->putJson('/api/v1/bookings/'.$booking->id, [
            'project_location_id' => $newLocation->id,
            'details' => [[
                'id' => $detail->id,
                'start_date' => now()->addDays(10)->toDateString(),
                'end_date' => now()->addDays(13)->toDateString(),
            ]],
        ])->assertOk();

        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'project_location_id' => $newLocation->id,
        ]);
        $this->assertEquals(now()->addDays(10)->toDateString(), $detail->fresh()->start_date->toDateString());

        $this->postJson('/api/v1/bookings/'.$booking->id.'/submit')->assertOk();
        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'status' => BookingStatus::PENDING_APPROVAL->value,
            'supervisor_decision' => null,
        ]);
    }

    public function test_supervisor_cannot_approve_or_view_approved_booking(): void
    {
        $supervisor = User::factory()->supervisor()->create();
        $pending = $this->booking($this->customer());
        $approved = $this->booking($this->customer(), BookingStatus::APPROVED);

        Sanctum::actingAs($supervisor);
        $this->postJson('/api/v1/bookings/'.$pending->id.'/approve')->assertForbidden();
        $this->getJson('/api/v1/bookings/'.$approved->id)->assertForbidden();
        $this->getJson('/api/v1/supervisor/bookings/'.$pending->id)->assertOk();

        Sanctum::actingAs(User::factory()->admin()->create());
        $this->getJson('/api/v1/supervisor/bookings')->assertForbidden();
    }

    public function test_operator_only_sees_own_assignments_and_locations(): void
    {
        [$rental, $rentalDetail] = $this->paidRental();
        $admin = User::factory()->admin()->create();
        $operatorA = User::factory()->operator()->create();
        $operatorB = User::factory()->operator()->create();

        Sanctum::actingAs($admin);
        $created = $this->postJson('/api/v1/admin/rentals/'.$rental->id.'/operator-assignments', [
            'rental_detail_id' => $rentalDetail->id,
            'operator_user_id' => $operatorA->id,
        ])->assertCreated();

        $assignmentId = (int) $created->json('data.id');

        Sanctum::actingAs($operatorA);
        $this->getJson('/api/v1/operator/dashboard')
            ->assertOk()
            ->assertJsonPath('data.active_assignments', 1);
        $this->getJson('/api/v1/operator/assignments/'.$assignmentId)
            ->assertOk()
            ->assertJsonPath('data.project_location.id', $rental->booking->project_location_id);

        Sanctum::actingAs($operatorB);
        $this->getJson('/api/v1/operator/assignments/'.$assignmentId)->assertForbidden();
        $this->getJson('/api/v1/operator/assignments')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_one_rental_unit_can_only_have_one_active_operator(): void
    {
        [$rental, $rentalDetail] = $this->paidRental();
        $admin = User::factory()->admin()->create();
        $operatorA = User::factory()->operator()->create();
        $operatorB = User::factory()->operator()->create();

        Sanctum::actingAs($admin);
        $this->postJson('/api/v1/admin/rentals/'.$rental->id.'/operator-assignments', [
            'rental_detail_id' => $rentalDetail->id,
            'operator_user_id' => $operatorA->id,
        ])->assertCreated();

        $this->postJson('/api/v1/admin/rentals/'.$rental->id.'/operator-assignments', [
            'rental_detail_id' => $rentalDetail->id,
            'operator_user_id' => $operatorB->id,
        ])->assertStatus(409)->assertJsonPath('code', 'BUSINESS_RULE_VIOLATION');

        $this->assertSame(1, RentalOperatorAssignment::where('rental_detail_id', $rentalDetail->id)
            ->where('status', RentalOperatorAssignment::ACTIVE)->count());
    }

    public function test_operator_replacement_keeps_history_and_updates_access(): void
    {
        [$rental, $rentalDetail] = $this->paidRental();
        $admin = User::factory()->admin()->create();
        $operatorA = User::factory()->operator()->create();
        $operatorB = User::factory()->operator()->create();

        Sanctum::actingAs($admin);
        $created = $this->postJson('/api/v1/admin/rentals/'.$rental->id.'/operator-assignments', [
            'rental_detail_id' => $rentalDetail->id,
            'operator_user_id' => $operatorA->id,
        ])->assertCreated();
        $oldId = (int) $created->json('data.id');

        $replaced = $this->postJson('/api/v1/admin/operator-assignments/'.$oldId.'/replace', [
            'operator_user_id' => $operatorB->id,
            'reason' => 'Operator A sakit, diganti Operator B.',
        ])->assertOk();

        $newId = (int) $replaced->json('data.id');
        $this->assertNotSame($oldId, $newId);
        $this->assertDatabaseHas('rental_operator_assignments', [
            'id' => $oldId,
            'status' => RentalOperatorAssignment::ENDED,
            'ended_by' => $admin->id,
        ]);
        $this->assertDatabaseHas('rental_operator_assignments', [
            'id' => $newId,
            'status' => RentalOperatorAssignment::ACTIVE,
            'operator_user_id' => $operatorB->id,
        ]);

        Sanctum::actingAs($operatorA);
        $this->getJson('/api/v1/operator/assignments/'.$oldId)->assertForbidden();
        $this->getJson('/api/v1/operator/assignments/'.$newId)->assertForbidden();

        Sanctum::actingAs($operatorB);
        $this->getJson('/api/v1/operator/assignments/'.$newId)->assertOk();
    }

    public function test_operator_assignment_requires_verified_payment(): void
    {
        [$rental, $rentalDetail, $booking] = $this->paidRental();
        $booking->update(['payment_met_at' => null]);
        $admin = User::factory()->admin()->create();
        $operator = User::factory()->operator()->create();

        Sanctum::actingAs($admin);
        $this->postJson('/api/v1/admin/rentals/'.$rental->id.'/operator-assignments', [
            'rental_detail_id' => $rentalDetail->id,
            'operator_user_id' => $operator->id,
        ])->assertStatus(409)->assertJsonPath('code', 'BUSINESS_RULE_VIOLATION');
    }

    public function test_operator_cannot_create_or_validate_timesheets(): void
    {
        [$rental, $rentalDetail] = $this->paidRental();
        $operator = User::factory()->operator()->create();
        $timesheet = Timesheet::factory()->create(['rental_detail_id' => $rentalDetail->id]);

        Sanctum::actingAs($operator);
        $this->createTimesheetAsOperator( [
            'rental_detail_id' => $rentalDetail->id,
            'report_date' => now()->toDateString(),
            'start_time' => '08:00',
            'end_time' => '17:00',
        ])->assertForbidden();
        $this->postJson('/api/v1/timesheets/'.$timesheet->id.'/approve')->assertForbidden();
        $this->postJson('/api/v1/timesheets/'.$timesheet->id.'/reject', ['reason' => 'Tidak valid'])->assertForbidden();
    }

    public function test_admin_can_manage_user_roles_without_touching_admin_or_owner(): void
    {
        $admin = User::factory()->admin()->create();
        $owner = User::factory()->owner()->create();
        $customer = User::factory()->create(['role' => UserRole::USER]);

        Sanctum::actingAs($admin);
        $this->patchJson('/api/v1/admin/users/'.$customer->id.'/role', ['role' => 'OPERATOR'])
            ->assertOk()
            ->assertJsonPath('data.role', UserRole::OPERATOR->value);
        $this->assertDatabaseHas('users', ['id' => $customer->id, 'role' => UserRole::OPERATOR->value]);

        $this->patchJson('/api/v1/admin/users/'.$owner->id.'/role', ['role' => 'OPERATOR'])
            ->assertStatus(409);
        $this->patchJson('/api/v1/admin/users/'.$admin->id.'/role', ['role' => 'SUPERVISOR'])
            ->assertStatus(409);

        Sanctum::actingAs($owner);
        $this->patchJson('/api/v1/admin/users/'.$customer->id.'/role', ['role' => 'USER'])
            ->assertForbidden();

        Sanctum::actingAs($customer);
        $this->patchJson('/api/v1/admin/users/'.$customer->id.'/role', ['role' => 'USER'])
            ->assertForbidden();
    }
}

