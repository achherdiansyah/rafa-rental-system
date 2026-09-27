<?php

namespace Tests\Feature\Rental;

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
use App\Models\EquipmentUnit;
use App\Models\ProjectLocation;
use App\Models\Rental;
use App\Models\Timesheet;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * End-to-end Phase 9 journey:
 * CONFIRMED -> DISPATCHED -> ARRIVED -> ONGOING -> timesheet -> signature ->
 * admin validation (+revision) -> RETURNING -> INSPECTION -> decision.
 */
class RentalTimesheetIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Log::spy();
        Storage::fake('local');
        Storage::fake('public');
    }

    private function seedBooking(int $units = 2): array
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
        $bookingDetail = BookingDetail::factory()->create([
            'booking_id' => $booking->id,
            'equipment_model_id' => $model->id,
            'quantity' => $units,
            'start_date' => now()->addDays(2)->toDateString(),
            'end_date' => now()->addDays(8)->toDateString(),
        ]);

        $assignments = [];
        foreach ($unitList as $unit) {
            $assignments[] = BookingUnitAssignment::factory()->create([
                'booking_detail_id' => $bookingDetail->id,
                'equipment_unit_id' => $unit->id,
                'status' => AssignmentStatus::ASSIGNED,
                'is_current' => true,
                'assigned_by' => $admin->id,
            ]);
        }

        return [$user, $admin, $booking, $unitList, $assignments];
    }

    public function test_full_rental_timesheet_lifecycle_end_to_end(): void
    {
        [$user, $admin, $booking, $unitList, $assignments] = $this->seedBooking(2);

        /** 1) Admin creates rental from CONFIRMED booking */
        Sanctum::actingAs($admin);
        $rentalRes = $this->postJson('/api/v1/rentals', ['booking_id' => $booking->id]);
        $rentalRes->assertCreated();
        $rentalId = $rentalRes->json('data.id');

        $rental = Rental::findOrFail($rentalId);
        $this->assertEquals($booking->id, $rental->booking_id, 'Rental harus terikat ke booking sumber.');
        $this->assertEquals(RentalStatus::ASSIGNED, $rental->status);
        $this->assertCount(2, $rental->details);
        $assignmentIds = array_map(fn ($a) => $a->id, $assignments);
        $this->assertEqualsCanonicalizing(
            $assignmentIds,
            $rental->details->pluck('assignment_id')->all(),
            'Rental detail harus merujuk ke assignment unit yang dipilih.'
        );
        $this->assertEquals(EquipmentStatus::ASSIGNED, $unitList[0]->fresh()->status);

        /** Invalid transition: START while ASSIGNED is rejected */
        $this->postJson("/api/v1/rentals/{$rentalId}/start")
            ->assertStatus(409)
            ->assertJson(['code' => 'INVALID_STATE_TRANSITION']);

        /** 2) DISPATCHED */
        $this->postJson("/api/v1/rentals/{$rentalId}/dispatch")
            ->assertOk()->assertJsonPath('data.status', RentalStatus::DISPATCHED->value);
        $this->assertEquals(EquipmentStatus::MOBILIZING, $unitList[0]->fresh()->status);
        $this->assertEquals(EquipmentStatus::MOBILIZING, $unitList[1]->fresh()->status);

        /** Unauthorized action: user cannot operate rental */
        Sanctum::actingAs($user);
        $this->postJson("/api/v1/rentals/{$rentalId}/arrive")->assertStatus(403);
        $this->postJson("/api/v1/rentals/{$rentalId}/return")->assertStatus(403);

        /** 3) ARRIVED */
        Sanctum::actingAs($admin);
        $this->postJson("/api/v1/rentals/{$rentalId}/arrive")->assertOk();
        $this->assertEquals(EquipmentStatus::ON_SITE, $unitList[0]->fresh()->status);

        /** 4) ONGOING (BAST check-in) */
        $ongoingRes = $this->postJson("/api/v1/rentals/{$rentalId}/start");
        $ongoingRes->assertOk()->assertJsonPath('data.status', RentalStatus::ONGOING->value);
        $this->assertNotNull($ongoingRes->json('data.started_at'));
        $this->assertEquals(EquipmentStatus::ON_SITE, $unitList[0]->fresh()->status);

        /** 5) Timesheet: owner records daily hours (8->16 minus 60min break = 7h) */
        $detail = $rental->details()->first();
        Sanctum::actingAs($user);
        $tsRes = $this->postJson('/api/v1/timesheets', [
            'rental_detail_id' => $detail->id,
            'report_date' => now()->toDateString(),
            'start_hm' => 8.00,
            'end_hm' => 16.00,
            'break_minutes' => 60,
            'standby_hours' => 0.5,
            'breakdown_hours' => 0.0,
            'operator_name' => 'Bejo Operator',
        ]);
        $tsRes->assertCreated();
        $tsId = $tsRes->json('data.id');
        $this->assertEqualsWithDelta(7.0, (float) $tsRes->json('data.total_work_hours'), 0.01);
        $this->assertEquals(TimesheetStatus::DRAFT->value, $tsRes->json('data.status'));

        // Timesheet only allowed while rental is ONGOING
        $second = $this->postJson('/api/v1/timesheets', [
            'rental_detail_id' => $rental->details()->get()[1]->id,
            'report_date' => now()->toDateString(),
            'start_hm' => 8.00,
            'end_hm' => 12.00,
        ]);
        $second->assertCreated();

        /** 6) User/PIC signature (private storage) */
        $sign = $this->postJson("/api/v1/timesheets/{$tsId}/signature", [
            'signature' => UploadedFile::fake()->image('ttd.png', 400, 200),
        ]);
        $sign->assertOk()->assertJsonPath('data.document_type', 'TIMESHEET_SIGNATURE');
        $this->assertNull($sign->json('data.url'), 'Signature pada private storage tidak boleh terekspos URL.');
        $timesheet = Timesheet::findOrFail($tsId);
        $this->assertNotEmpty($timesheet->signature_reference);
        $this->assertDatabaseHas('attachments', ['id' => $sign->json('data.id'), 'attachable_id' => $tsId]);
        Storage::disk('local')->assertExists($timesheet->fresh()->attachments()->first()->file_path);

        /** 7) Submit for validation */
        Sanctum::actingAs($user);
        $this->postJson("/api/v1/timesheets/{$tsId}/submit")
            ->assertOk()->assertJsonPath('data.status', TimesheetStatus::SUBMITTED->value);

        /** 8) Admin approves */
        Sanctum::actingAs($admin);
        $this->postJson("/api/v1/timesheets/{$tsId}/approve")
            ->assertOk()->assertJsonPath('data.status', TimesheetStatus::APPROVED->value);
        $this->assertEquals($admin->id, Timesheet::find($tsId)->approved_by);

        /** 9) Admin revision (append-only) then re-approval */
        $this->putJson("/api/v1/timesheets/{$tsId}/revise", [
            'end_hm' => 16.50,
            'break_minutes' => 30,
            'reason' => 'Cek ulang HM akhir dari catatan site.',
        ])->assertOk()->assertJsonPath('data.status', TimesheetStatus::SUBMITTED->value);

        $revisions = Timesheet::find($tsId)->revisions()->orderBy('version')->get();
        $this->assertCount(1, $revisions);
        $this->assertEquals(1, $revisions->first()->version);
        $this->assertEquals(8.0, (float) $revisions->first()->old_start_hm);
        $this->assertEquals(16.0, (float) $revisions->first()->old_end_hm);
        $this->assertEqualsWithDelta(8.0, (float) Timesheet::find($tsId)->total_work_hours, 0.01); // 8.5 - 0.5

        $this->postJson("/api/v1/timesheets/{$tsId}/approve")
            ->assertOk()->assertJsonPath('data.status', TimesheetStatus::APPROVED->value);

        /** Revision history intact (no data loss), oldest entry still present */
        $this->assertCount(1, Timesheet::find($tsId)->revisions()->get());

        /** 10) RETURNING (ONGOING -> DEMOBILIZING), units NOT available */
        $this->postJson("/api/v1/rentals/{$rentalId}/return")
            ->assertOk()->assertJsonPath('data.status', RentalStatus::DEMOBILIZING->value);
        $this->assertEquals(EquipmentStatus::DEMOBILIZING, $unitList[0]->fresh()->status);
        $this->assertEquals(EquipmentStatus::DEMOBILIZING, $unitList[1]->fresh()->status);
        $this->assertNotEquals(EquipmentStatus::AVAILABLE, $unitList[0]->fresh()->status);

        /** Ready must only happen after inspection */
        $this->postJson("/api/v1/rentals/{$rentalId}/ready", ['result' => 'READY'])
            ->assertStatus(409);

        /** 11) INSPECTION */
        $this->postJson("/api/v1/rentals/{$rentalId}/inspect")
            ->assertOk()->assertJsonPath('data.status', RentalStatus::RETURN_INSPECTED->value);
        $this->assertEquals(EquipmentStatus::RETURN_INSPECTION, $unitList[0]->fresh()->status);

        /** 12) Decision READY -> AVAILABLE + inspection history persisted */
        $this->postJson("/api/v1/rentals/{$rentalId}/ready", [
            'result' => 'READY',
            'condition_notes' => 'Unit layak kembali dipakai.',
        ])->assertOk()->assertJsonPath('data.status', RentalStatus::COMPLETED->value);

        $finished = Rental::findOrFail($rentalId);
        $this->assertNotNull($finished->completed_at);
        foreach ($finished->details as $d) {
            $this->assertEquals('READY', $d->inspection_result);
            $this->assertNotNull($d->checked_out_at);
            $this->assertEquals('Unit layak kembali dipakai.', $d->condition_notes);
        }
        $this->assertEquals(EquipmentStatus::AVAILABLE, $unitList[0]->fresh()->status);
        $this->assertEquals(EquipmentStatus::AVAILABLE, $unitList[1]->fresh()->status);
    }

    public function test_maintenance_and_damaged_results_keep_units_unavailable(): void
    {
        [, $admin, $booking, $unitList] = $this->seedBooking(1);
        [, , $booking2, $unitList2] = $this->seedBooking(1);
        $unit = $unitList[0];
        $unit2 = $unitList2[0];

        // Maintenance path
        Sanctum::actingAs($admin);
        $rentalId = $this->postJson('/api/v1/rentals', ['booking_id' => $booking->id])->json('data.id');
        foreach (['dispatch', 'arrive', 'start', 'return', 'inspect'] as $target) {
            $this->postJson("/api/v1/rentals/{$rentalId}/{$target}")->assertOk();
        }
        $this->postJson("/api/v1/rentals/{$rentalId}/ready", ['result' => 'MAINTENANCE'])
            ->assertOk()->assertJsonPath('data.status', RentalStatus::COMPLETED->value);
        $this->assertEquals(EquipmentStatus::MAINTENANCE, $unit->fresh()->status);
        $this->assertEquals('MAINTENANCE', Rental::find($rentalId)->details()->first()->inspection_result);

        // Damaged path: kept in MAINTENANCE, no automatic charge record
        $rentalId2 = $this->postJson('/api/v1/rentals', ['booking_id' => $booking2->id])->json('data.id');
        foreach (['dispatch', 'arrive', 'start', 'return', 'inspect'] as $target) {
            $this->postJson("/api/v1/rentals/{$rentalId2}/{$target}")->assertOk();
        }
        $this->postJson("/api/v1/rentals/{$rentalId2}/ready", [
            'result' => 'DAMAGED',
            'condition_notes' => 'Bucket retak, butuh penilaian biaya.',
        ])->assertOk();
        $this->assertEquals(EquipmentStatus::MAINTENANCE, $unit2->fresh()->status);
        $this->assertEquals('DAMAGED', Rental::find($rentalId2)->details()->first()->inspection_result);
    }

    public function test_audit_persistence_across_lifecycle(): void
    {
        [$user, $admin, $booking] = $this->seedBooking(1);

        Sanctum::actingAs($admin);
        $rentalId = $this->postJson('/api/v1/rentals', ['booking_id' => $booking->id])->json('data.id');

        foreach (['dispatch', 'arrive', 'start'] as $target) {
            $this->postJson("/api/v1/rentals/{$rentalId}/{$target}")->assertOk();
        }

        // Timesheet while rental is ONGOING, then submit
        $detail = Rental::find($rentalId)->details()->first()->id;

        Sanctum::actingAs($user);
        $tsId = $this->postJson('/api/v1/timesheets', [
            'rental_detail_id' => $detail,
            'report_date' => now()->toDateString(),
            'start_hm' => 8,
            'end_hm' => 13,
        ])->json('data.id');
        $this->postJson("/api/v1/timesheets/{$tsId}/submit")->assertOk();

        Sanctum::actingAs($admin);
        $this->postJson("/api/v1/timesheets/{$tsId}/approve")->assertOk();

        // Return -> inspect -> ready
        foreach (['return', 'inspect'] as $target) {
            $this->postJson("/api/v1/rentals/{$rentalId}/{$target}")->assertOk();
        }
        $this->postJson("/api/v1/rentals/{$rentalId}/ready", ['result' => 'READY'])->assertOk();

        // Every lifecycle action produced an audit record.
        foreach ([
            'RENTAL_DISPATCHED',
            'RENTAL_ARRIVED',
            'RENTAL_ONGOING',
            'TIMESHEET_CREATED',
            'TIMESHEET_SUBMITTED',
            'TIMESHEET_APPROVED',
            'RENTAL_DEMOBILIZING',
            'RENTAL_RETURN_INSPECTED',
            'RENTAL_INSPECTION',
        ] as $event) {
            Log::shouldHaveReceived('info')
                ->withArgs(fn ($msg) => is_string($msg) && str_contains($msg, "[{$event}]"));
        }
    }
}
