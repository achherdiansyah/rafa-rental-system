<?php

namespace Tests\Feature\Reports;

use App\Enums\BookingStatus;
use App\Enums\RentalStatus;
use App\Enums\TimesheetStatus;
use App\Models\Booking;
use App\Models\BookingDetail;
use App\Models\BookingUnitAssignment;
use App\Models\EquipmentModel;
use App\Models\EquipmentUnit;
use App\Models\Invoice;
use App\Models\ProjectLocation;
use App\Models\Rental;
use App\Models\RentalDetail;
use App\Models\Timesheet;
use App\Models\User;
use App\Services\Reports\OperationalReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OperationalReportTest extends TestCase
{
    use RefreshDatabase;

    private function seededRental(User $user, ProjectLocation $project, float $hours): array
    {
        $model = EquipmentModel::factory()->create(['is_active' => true]);
        $unit = EquipmentUnit::factory()->create(['equipment_model_id' => $model->id, 'status' => 'ON_SITE']);
        $booking = Booking::factory()->create(['user_id' => $user->id, 'status' => BookingStatus::CONFIRMED->value, 'project_location_id' => $project->id]);
        $detail = BookingDetail::factory()->create(['booking_id' => $booking->id, 'equipment_model_id' => $model->id, 'quantity' => 1]);
        $assignment = BookingUnitAssignment::factory()->create([
            'booking_detail_id' => $detail->id, 'equipment_unit_id' => $unit->id,
            'status' => 'ASSIGNED', 'is_current' => true, 'assigned_by' => $user->id,
        ]);
        $rental = Rental::factory()->create(['booking_id' => $booking->id, 'status' => RentalStatus::COMPLETED->value]);
        $rd = RentalDetail::factory()->create(['rental_id' => $rental->id, 'assignment_id' => $assignment->id]);
        Timesheet::factory()->create([
            'rental_detail_id' => $rd->id,
            'status' => TimesheetStatus::APPROVED->value,
            'total_work_hours' => $hours,
            'report_date' => now()->subDays(2)->toDateString(),
        ]);
        // Invoice paid for activity report
        Invoice::factory()->create(['booking_id' => $booking->id, 'status' => 'PAID', 'grand_total' => 500000, 'paid_amount' => 500000]);

        return [$booking, $rental, $rd, $unit, $model];
    }

    public function test_booking_report_rows_filters_pagination_and_drill_ids(): void
    {
        $user = User::factory()->create();
        $project = ProjectLocation::factory()->create(['user_id' => $user->id, 'project_name' => 'Proyek A']);
        $b1 = Booking::factory()->create(['user_id' => $user->id, 'status' => BookingStatus::CONFIRMED->value, 'project_location_id' => $project->id, 'created_at' => '2026-09-01']);
        Booking::factory()->create(['user_id' => $user->id, 'status' => BookingStatus::DRAFT->value, 'project_location_id' => $project->id, 'created_at' => '2026-09-02']);

        $service = app(OperationalReportService::class);

        $all = $service->bookingReport(['from' => '2026-09-01', 'to' => '2026-09-30']);
        $this->assertEquals(2, $all->total());

        $page = $service->bookingReport(['status' => BookingStatus::CONFIRMED->value, 'per_page' => 5]);
        $this->assertEquals(1, $page->total());
        $row = $page->items()[0];
        $this->assertSame($b1->id, $row['id']);
        $this->assertEquals('Proyek A', $row['project_name']);
        $this->assertEquals((int) $user->id, $row['customer_id']);
        $this->assertEquals((int) $project->id, $row['project_location_id']); // drillable

        $scoped = $service->bookingReport([], (int) $user->id);
        $this->assertEquals(2, $scoped->total());
    }

    public function test_timesheet_report_rows_and_sort(): void
    {
        $user = User::factory()->create();
        $project = ProjectLocation::factory()->create(['user_id' => $user->id, 'project_name' => 'Tol Cisauk']);
        [, , $rd1, , $model] = $this->seededRental($user, $project, 7.5);
        Timesheet::factory()->create([
            'rental_detail_id' => $rd1->id,
            'status' => TimesheetStatus::APPROVED->value,
            'total_work_hours' => 5.0,
            'report_date' => now()->subDay()->toDateString(),
        ]);

        $page = app(OperationalReportService::class)->timesheetReport(['model_id' => $model->id, 'sort_by' => 'total_work_hours', 'sort_dir' => 'asc']);
        $this->assertEquals(2, $page->total());
        $this->assertEquals(5.0, $page->items()[0]['total_work_hours']);
        $this->assertArrayHasKey('unit_serial', $page->items()[0]);
    }

    public function test_rental_utilization_and_activity_report(): void
    {
        $user = User::factory()->create();
        $project = ProjectLocation::factory()->create(['user_id' => $user->id, 'project_name' => 'Proyek B']);
        [, $rental, , $unit, $model] = $this->seededRental($user, $project, 8.0);

        $util = app(OperationalReportService::class)->rentalUtilization(['unit_id' => $unit->id]);
        $this->assertEquals(1, $util->total());
        $this->assertEqualsWithDelta(8.0, $util->items()[0]['total_work_hours'], 0.001);

        $activity = app(OperationalReportService::class)->projectCustomerActivity(['project_id' => $project->id]);
        $this->assertEquals(1, $activity->total());
        $row = $activity->items()[0];
        $this->assertEqualsWithDelta(8.0, $row['total_hours'], 0.001);
        $this->assertEqualsWithDelta(500000.0, $row['paid_total'], 0.01);
        $this->assertSame($user->name, $row['customer_name']);

        // Scoped user sees own activity only
        $other = User::factory()->create();
        $otherProject = ProjectLocation::factory()->create(['user_id' => $other->id]);
        $this->seededRental($other, $otherProject, 12.0);
        $scoped = app(OperationalReportService::class)->projectCustomerActivity([], (int) $user->id);
        $this->assertEquals(1, $scoped->total());
    }

    public function test_equipment_report_admin_only_with_fleet_fields(): void
    {
        $user = User::factory()->create();
        $model = EquipmentModel::factory()->create(['is_active' => true]);
        EquipmentUnit::factory()->create(['equipment_model_id' => $model->id, 'status' => 'AVAILABLE']);

        Sanctum::actingAs($user);
        $this->getJson('/api/v1/reports/operational/equipment')->assertStatus(403);

        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);
        $res = $this->getJson('/api/v1/reports/operational/equipment?model_id='.$model->id);
        $res->assertOk();
        $this->assertEquals(1, $res->json('meta.total'));
        $this->assertArrayHasKey('serial_number', $res->json('data.0'));
        $this->assertEquals('AVAILABLE', $res->json('data.0.status'));

        // Read-only guarantee: unit still exists untouched
        $this->assertDatabaseCount('equipment_units', 1);
    }
}
