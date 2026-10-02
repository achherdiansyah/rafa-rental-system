<?php

namespace Tests\Feature\Timesheet;

use App\Enums\BookingStatus;
use App\Enums\EquipmentStatus;
use App\Enums\RentalStatus;
use App\Enums\TimesheetStatus;
use App\Enums\UserRole;
use App\Models\Booking;
use App\Models\BookingDetail;
use App\Models\BookingUnitAssignment;
use App\Models\EquipmentModel;
use App\Models\EquipmentUnit;
use App\Models\ProjectLocation;
use App\Models\Rental;
use App\Models\RentalDetail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Timesheet input is an ADMIN operational task (recorded from the field
 * operator report). User/PIC reads + confirms/signs only; Owner read-only.
 */
class TimesheetCoreTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Log::spy();
    }

    private function ongoingRental(User $admin, User $owner): array
    {
        $location = ProjectLocation::factory()->create(['user_id' => $owner->id]);
        $model = EquipmentModel::factory()->create(['is_active' => true]);
        $unit = EquipmentUnit::factory()->create(['equipment_model_id' => $model->id, 'status' => EquipmentStatus::ON_SITE]);

        $booking = Booking::factory()->create([
            'user_id' => $owner->id,
            'project_location_id' => $location->id,
            'status' => BookingStatus::CONFIRMED,
        ]);
        $bookingDetail = BookingDetail::factory()->create(['booking_id' => $booking->id, 'equipment_model_id' => $model->id]);
        $assignment = BookingUnitAssignment::factory()->create([
            'booking_detail_id' => $bookingDetail->id,
            'equipment_unit_id' => $unit->id,
            'is_current' => true,
            'assigned_by' => $admin->id,
        ]);

        $rental = Rental::factory()->create([
            'booking_id' => $booking->id,
            'status' => RentalStatus::ONGOING,
        ]);
        $rentalDetail = RentalDetail::factory()->create([
            'rental_id' => $rental->id,
            'assignment_id' => $assignment->id,
        ]);

        return [$rentalDetail, $booking, $unit];
    }

    private function payload(int $rentalDetailId, array $overrides = []): array
    {
        return array_merge([
            'rental_detail_id' => $rentalDetailId,
            'report_date' => now()->toDateString(),
            'start_hm' => 100.00,
            'end_hm' => 112.00,
            'break_minutes' => 60,
            'operator_name' => 'Bambang Operator',
            'notes' => 'Hari kerja normal',
            'signature_reference' => 'sig-abc123',
        ], $overrides);
    }

    public function test_admin_inputs_timesheet_with_computed_hours(): void
    {
        $admin = User::factory()->admin()->create();
        $owner = User::factory()->create(['role' => UserRole::USER]);
        [$rentalDetail] = $this->ongoingRental($admin, $owner);

        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/v1/timesheets', $this->payload($rentalDetail->id));

        $response->assertStatus(201)
            ->assertJsonPath('data.status', TimesheetStatus::APPROVED->value)
            ->assertJsonPath('data.operator_name', 'Bambang Operator')
            ->assertJsonPath('data.signature_reference', 'sig-abc123')
            ->assertJsonPath('data.rental.unit_serial', EquipmentUnit::first()->serial_number);

        $this->assertEqualsWithDelta(11.0, $response->json('data.total_work_hours'), 0.01); // 12 jam - 1 jam break
    }

    public function test_calculation_no_rounding_without_break(): void
    {
        $admin = User::factory()->admin()->create();
        $owner = User::factory()->create(['role' => UserRole::USER]);
        [$rentalDetail] = $this->ongoingRental($admin, $owner);

        Sanctum::actingAs($admin);

        $res = $this->postJson('/api/v1/timesheets', $this->payload($rentalDetail->id, ['break_minutes' => 0]));
        $res->assertStatus(201);
        $this->assertEquals(12.0, $res->json('data.total_work_hours'));
    }

    public function test_timesheet_requires_ongoing_rental(): void
    {
        $admin = User::factory()->admin()->create();
        $owner = User::factory()->create(['role' => UserRole::USER]);
        [$rentalDetail] = $this->ongoingRental($admin, $owner);
        $rentalDetail->rental()->update(['status' => RentalStatus::ARRIVED]);

        Sanctum::actingAs($admin);

        $this->postJson('/api/v1/timesheets', $this->payload($rentalDetail->id))
            ->assertStatus(409)
            ->assertJson(['code' => 'BUSINESS_RULE_VIOLATION']);
        $this->assertStringContainsString('ONGOING', $this->postJson('/api/v1/timesheets', $this->payload($rentalDetail->id))->json('message'));
    }

    public function test_validation_rejects_invalid_duration(): void
    {
        $admin = User::factory()->admin()->create();
        $owner = User::factory()->create(['role' => UserRole::USER]);
        [$rentalDetail] = $this->ongoingRental($admin, $owner);

        Sanctum::actingAs($admin);

        // end_hm <= start_hm (caught by business rule exception now)
        $this->postJson('/api/v1/timesheets', $this->payload($rentalDetail->id, ['end_hm' => 95]))
            ->assertStatus(409);

        // future date
        $this->postJson('/api/v1/timesheets', $this->payload($rentalDetail->id, ['report_date' => now()->addDays(2)->toDateString()]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['report_date']);

        // negative break
        $this->postJson('/api/v1/timesheets', $this->payload($rentalDetail->id, ['break_minutes' => -30]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['break_minutes']);
    }

    public function test_inconsistent_hours_rejected(): void
    {
        $admin = User::factory()->admin()->create();
        $owner = User::factory()->create(['role' => UserRole::USER]);
        [$rentalDetail] = $this->ongoingRental($admin, $owner);

        Sanctum::actingAs($admin);

        // work (11) < breakdown (8) + standby (4)
        $this->postJson('/api/v1/timesheets', $this->payload($rentalDetail->id, [
            'breakdown_hours' => 8,
            'standby_hours' => 4,
        ]))
            ->assertStatus(409)
            ->assertJson(['code' => 'BUSINESS_RULE_VIOLATION']);
    }

    public function test_duplicate_timesheet_per_unit_per_day_blocked(): void
    {
        $admin = User::factory()->admin()->create();
        $owner = User::factory()->create(['role' => UserRole::USER]);
        [$rentalDetail] = $this->ongoingRental($admin, $owner);

        Sanctum::actingAs($admin);

        $this->postJson('/api/v1/timesheets', $this->payload($rentalDetail->id))->assertStatus(201);

        $this->postJson('/api/v1/timesheets', $this->payload($rentalDetail->id))
            ->assertStatus(409)
            ->assertJson(['code' => 'BUSINESS_RULE_VIOLATION']);
    }

    public function test_user_cannot_create_timesheet(): void
    {
        $admin = User::factory()->admin()->create();
        $ownerA = User::factory()->create(['role' => UserRole::USER]);
        $user = User::factory()->create(['role' => UserRole::USER]);

        [$rentalDetail] = $this->ongoingRental($admin, $ownerA);

        // USER (any) can never create — role model: admin inputs timesheets
        Sanctum::actingAs($user);
        $this->postJson('/api/v1/timesheets', $this->payload($rentalDetail->id))
            ->assertStatus(403);
    }

    public function test_owner_cannot_create_timesheet(): void
    {
        $admin = User::factory()->admin()->create();
        $owner = User::factory()->owner()->create();

        [$rentalDetail] = $this->ongoingRental($admin, $owner);

        Sanctum::actingAs($owner);
        $this->postJson('/api/v1/timesheets', $this->payload($rentalDetail->id))
            ->assertStatus(403);
    }

    public function test_user_cannot_view_foreign_timesheet(): void
    {
        $admin = User::factory()->admin()->create();
        $ownerA = User::factory()->create(['role' => UserRole::USER]);
        $intruder = User::factory()->create(['role' => UserRole::USER]);

        [$rentalDetail] = $this->ongoingRental($admin, $ownerA);

        Sanctum::actingAs($admin);
        $timesheetId = $this->postJson('/api/v1/timesheets', $this->payload($rentalDetail->id))->json('data.id');

        Sanctum::actingAs($intruder);
        $this->getJson("/api/v1/timesheets/{$timesheetId}")->assertStatus(403);
    }

    public function test_submit_already_approved_is_idempotent(): void
    {
        $admin = User::factory()->admin()->create();
        $owner = User::factory()->create(['role' => UserRole::USER]);
        [$rentalDetail] = $this->ongoingRental($admin, $owner);

        Sanctum::actingAs($admin);
        $timesheetId = $this->postJson('/api/v1/timesheets', $this->payload($rentalDetail->id))->json('data.id');

        $submit = $this->postJson("/api/v1/timesheets/{$timesheetId}/submit");
        $submit->assertStatus(200)
            ->assertJsonPath('data.status', TimesheetStatus::APPROVED->value);

        // Double submit idempotent
        $this->postJson("/api/v1/timesheets/{$timesheetId}/submit")
            ->assertStatus(200)
            ->assertJsonPath('data.status', TimesheetStatus::APPROVED->value);
    }

    public function test_user_cannot_submit_timesheet(): void
    {
        $admin = User::factory()->admin()->create();
        $owner = User::factory()->create(['role' => UserRole::USER]);
        [$rentalDetail] = $this->ongoingRental($admin, $owner);

        Sanctum::actingAs($admin);
        $timesheetId = $this->postJson('/api/v1/timesheets', $this->payload($rentalDetail->id))->json('data.id');

        Sanctum::actingAs($owner);
        $this->postJson("/api/v1/timesheets/{$timesheetId}/submit")
            ->assertStatus(403);
    }
}