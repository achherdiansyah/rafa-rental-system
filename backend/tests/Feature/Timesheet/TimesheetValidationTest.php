<?php

namespace Tests\Feature\Timesheet;

use App\Enums\BookingStatus;
use App\Enums\EquipmentStatus;
use App\Enums\RentalStatus;
use App\Enums\TimesheetStatus;
use App\Enums\UserRole;
use App\Models\Attachment;
use App\Models\Booking;
use App\Models\BookingDetail;
use App\Models\BookingUnitAssignment;
use App\Models\EquipmentModel;
use App\Models\EquipmentUnit;
use App\Models\ProjectLocation;
use App\Models\Rental;
use App\Models\RentalDetail;
use App\Models\Timesheet;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TimesheetValidationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Log::spy();
        Storage::fake('local');
        Storage::fake('public');
    }

    private function submittedTimesheet(): array
    {
        $admin = User::factory()->admin()->create();
        $owner = User::factory()->create(['role' => UserRole::USER]);

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
        $rental = Rental::factory()->create(['booking_id' => $booking->id, 'status' => RentalStatus::ONGOING]);
        $rentalDetail = RentalDetail::factory()->create(['rental_id' => $rental->id, 'assignment_id' => $assignment->id]);

        Sanctum::actingAs($owner);
        $timesheetId = $this->postJson('/api/v1/timesheets', [
            'rental_detail_id' => $rentalDetail->id,
            'report_date' => now()->toDateString(),
            'start_hm' => 100.00,
            'end_hm' => 112.00,
            'break_minutes' => 60,
            'operator_name' => 'Bambang',
        ])->json('data.id');

        $this->postJson("/api/v1/timesheets/{$timesheetId}/submit")->assertStatus(200);

        return [$admin, $owner, Timesheet::find($timesheetId)];
    }

    public function test_pic_can_upload_signature_to_private_storage(): void
    {
        [$admin, $owner, $timesheet] = $this->submittedTimesheet();

        Sanctum::actingAs($owner);

        $response = $this->postJson("/api/v1/timesheets/{$timesheet->id}/signature", [
            'signature' => UploadedFile::fake()->image('ttd-pic.png', 200, 100),
        ]);

        $response->assertStatus(200);
        $this->assertNotEmpty($response->json('data.id'));
        $this->assertEquals('TIMESHEET_SIGNATURE', $response->json('data.document_type'));

        // Reference persisted on timesheet
        $this->assertEquals((string) $response->json('data.id'), $timesheet->fresh()->signature_reference);

        // Attachment stored on private local disk (not public)
        $attachment = Attachment::find($response->json('data.id'));
        Storage::disk('local')->assertExists($attachment->file_path);
        $this->assertNull($response->json('data.url'));
    }

    public function test_signature_rejects_invalid_mime(): void
    {
        [$admin, $owner, $timesheet] = $this->submittedTimesheet();

        Sanctum::actingAs($owner);

        $this->postJson("/api/v1/timesheets/{$timesheet->id}/signature", [
            'signature' => UploadedFile::fake()->create('script.php', 100, 'application/x-php'),
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['signature']);
    }

    public function test_admin_validates_submitted_timesheet(): void
    {
        [$admin, $owner, $timesheet] = $this->submittedTimesheet();

        Sanctum::actingAs($admin);

        $res = $this->postJson("/api/v1/timesheets/{$timesheet->id}/approve");
        $res->assertStatus(200)
            ->assertJsonPath('data.status', TimesheetStatus::APPROVED->value);

        $this->assertEquals($admin->id, $timesheet->fresh()->approved_by);
    }

    public function test_user_cannot_validate_or_revise(): void
    {
        [$admin, $owner, $timesheet] = $this->submittedTimesheet();

        Sanctum::actingAs($owner);

        $this->postJson("/api/v1/timesheets/{$timesheet->id}/approve")->assertStatus(403);
        $this->postJson("/api/v1/timesheets/{$timesheet->id}/reject", ['reason' => 'Data tidak sesuai bukti lapangan.'])
            ->assertStatus(403);
        $this->putJson("/api/v1/timesheets/{$timesheet->id}/revise", [
            'start_hm' => 100,
            'end_hm' => 111,
            'reason' => 'Koreksi manual',
        ])->assertStatus(403);
    }

    public function test_admin_rejects_timesheet_with_revision_note(): void
    {
        [$admin, $owner, $timesheet] = $this->submittedTimesheet();

        Sanctum::actingAs($admin);

        $res = $this->postJson("/api/v1/timesheets/{$timesheet->id}/reject", [
            'reason' => 'Ketidaksesuaian antara HM dan bukti operator.',
        ]);
        $res->assertStatus(200)
            ->assertJsonPath('data.status', TimesheetStatus::REJECTED->value);

        // Rejection note persisted as immutable revision (BR-018)
        $revision = $timesheet->revisions()->first();
        $this->assertNotNull($revision);
        $this->assertEquals(1, $revision->version);
        $this->assertStringContainsString('REJECTED', $revision->revision_reason);
        $this->assertEquals($admin->id, $revision->revised_by);
    }

    public function test_admin_correction_snapshots_old_values_and_recalculates(): void
    {
        [$admin, $owner, $timesheet] = $this->submittedTimesheet();

        // Approve first
        Sanctum::actingAs($admin);
        $this->postJson("/api/v1/timesheets/{$timesheet->id}/approve")->assertStatus(200);

        $res = $this->putJson("/api/v1/timesheets/{$timesheet->id}/revise", [
            'start_hm' => 100.00,
            'end_hm' => 111.00,
            'break_minutes' => 60,
            'reason' => 'Koreksi HM akhir hasil cek ulang bengkel.',
        ]);
        $res->assertStatus(200)
            ->assertJsonPath('data.status', TimesheetStatus::SUBMITTED->value);

        $fresh = $timesheet->fresh();
        $this->assertEquals(111.00, (float) $fresh->end_hm);
        $this->assertEqualsWithDelta(10.0, (float) $fresh->total_work_hours, 0.01); // 11h - 1h break

        // Old values preserved (append-only): 100 -> 112 before correction
        $revision = $fresh->revisions()->latest('version')->first();
        $this->assertNotNull($revision);
        $this->assertEquals(100.00, (float) $revision->old_start_hm);
        $this->assertEquals(112.00, (float) $revision->old_end_hm);
        $this->assertEquals($admin->id, $revision->revised_by);
    }

    public function test_revision_history_lists_append_only_entries(): void
    {
        [$admin, $owner, $timesheet] = $this->submittedTimesheet();

        Sanctum::actingAs($admin);
        $this->postJson("/api/v1/timesheets/{$timesheet->id}/approve")->assertStatus(200);
        $this->putJson("/api/v1/timesheets/{$timesheet->id}/revise", [
            'end_hm' => 111.00,
            'reason' => 'Koreksi pertama.',
        ])->assertStatus(200);
        $this->postJson("/api/v1/timesheets/{$timesheet->id}/approve")->assertStatus(200);
        $this->putJson("/api/v1/timesheets/{$timesheet->id}/revise", [
            'end_hm' => 110.00,
            'reason' => 'Koreksi kedua.',
        ])->assertStatus(200);

        $res = $this->getJson("/api/v1/timesheets/{$timesheet->id}/revisions");
        $res->assertStatus(200);
        $entries = $res->json('data');
        $this->assertCount(2, $entries);
        $this->assertEquals(2, $entries[0]['version']);
        $this->assertEquals(1, $entries[1]['version']);
        $this->assertEquals($admin->id, $entries[0]['revised_by']);
    }

    public function test_revision_is_audited(): void
    {
        [$admin, $owner, $timesheet] = $this->submittedTimesheet();

        Sanctum::actingAs($admin);
        $this->postJson("/api/v1/timesheets/{$timesheet->id}/approve")->assertStatus(200);

        Log::shouldHaveReceived('info')
            ->with(\Mockery::pattern('/TIMESHEET_APPROVED/'), \Mockery::type('array'));
    }
}
