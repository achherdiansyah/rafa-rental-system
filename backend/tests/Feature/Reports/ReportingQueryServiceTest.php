<?php

namespace Tests\Feature\Reports;

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
use App\Enums\RentalStatus;
use App\Enums\TimesheetStatus;
use App\Models\Booking;
use App\Models\BookingDetail;
use App\Models\BookingUnitAssignment;
use App\Models\EquipmentModel;
use App\Models\EquipmentUnit;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\ProjectLocation;
use App\Models\Refund;
use App\Models\Rental;
use App\Models\RentalDetail;
use App\Models\Timesheet;
use App\Models\User;
use App\Services\Reports\ReportingQueryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReportingQueryServiceTest extends TestCase
{
    use RefreshDatabase;

    private function bookingFor(User $user, string $status, string $createdAt): Booking
    {
        return Booking::factory()->create([
            'user_id' => $user->id,
            'status' => $status,
            'created_at' => $createdAt,
        ]);
    }

    private function approvedHours(User $user, ProjectLocation $project, float $hours): void
    {
        $model = EquipmentModel::factory()->create(['is_active' => true]);
        $unit = EquipmentUnit::factory()->create(['equipment_model_id' => $model->id, 'status' => 'ON_SITE']);

        $booking = Booking::factory()->create(['user_id' => $user->id, 'status' => BookingStatus::CONFIRMED->value, 'project_location_id' => $project->id]);
        $bookingDetail = BookingDetail::factory()->create(['booking_id' => $booking->id, 'equipment_model_id' => $model->id, 'quantity' => 1]);
        $assignment = BookingUnitAssignment::factory()->create([
            'booking_detail_id' => $bookingDetail->id,
            'equipment_unit_id' => $unit->id,
            'status' => 'ASSIGNED',
            'is_current' => true,
            'assigned_by' => $user->id,
        ]);

        $rental = Rental::factory()->create(['booking_id' => $booking->id, 'status' => RentalStatus::COMPLETED->value]);
        $detail = RentalDetail::factory()->create(['rental_id' => $rental->id, 'assignment_id' => $assignment->id]);
        Timesheet::factory()->create([
            'rental_detail_id' => $detail->id,
            'status' => TimesheetStatus::APPROVED->value,
            'total_work_hours' => $hours,
            'report_date' => now()->subDays(3)->toDateString(),
        ]);
    }

    public function test_booking_summary_groups_by_status_and_period(): void
    {
        $user = User::factory()->create();
        $this->bookingFor($user, BookingStatus::CONFIRMED->value, '2026-09-01');
        $this->bookingFor($user, BookingStatus::CONFIRMED->value, '2026-09-05');
        $this->bookingFor($user, BookingStatus::DRAFT->value, '2026-09-02');

        $service = app(ReportingQueryService::class);

        $summary = $service->bookingSummary(['from' => '2026-09-01', 'to' => '2026-09-30']);
        $this->assertEquals(3, $summary['total']);
        $this->assertEquals(2, $summary['by_status']['CONFIRMED']);
        $this->assertEquals(1, $summary['by_status']['DRAFT']);

        $onlyConfirmed = $service->bookingSummary(['status' => BookingStatus::CONFIRMED->value]);
        $this->assertEquals(2, $onlyConfirmed['total']);

        $scoped = $service->bookingSummary([], (int) $user->id);
        $this->assertEquals(3, $scoped['total']);
    }

    public function test_rental_summary_by_status_and_project(): void
    {
        $user = User::factory()->create();
        $p1 = ProjectLocation::factory()->create(['user_id' => $user->id, 'project_name' => 'Proyek A']);
        $p2 = ProjectLocation::factory()->create(['user_id' => $user->id, 'project_name' => 'Proyek B']);

        $b1 = Booking::factory()->create(['user_id' => $user->id, 'status' => BookingStatus::CONFIRMED->value, 'project_location_id' => $p1->id]);
        $b2 = Booking::factory()->create(['user_id' => $user->id, 'status' => BookingStatus::CONFIRMED->value, 'project_location_id' => $p2->id]);
        Rental::factory()->create(['booking_id' => $b1->id, 'status' => RentalStatus::COMPLETED->value]);
        Rental::factory()->create(['booking_id' => $b2->id, 'status' => RentalStatus::ONGOING->value]);

        $summary = app(ReportingQueryService::class)->rentalSummary();

        $this->assertEquals(2, $summary['total']);
        $this->assertEquals(1, $summary['by_status'][RentalStatus::COMPLETED->value]);
        $this->assertEquals(1, $summary['by_status'][RentalStatus::ONGOING->value]);
        $this->assertEquals('Proyek A', $summary['by_project'][0]['project_name']);

        $filtered = app(ReportingQueryService::class)->rentalSummary(['project_id' => $p2->id]);
        $this->assertEquals(1, $filtered['total']);
    }

    public function test_timesheet_and_utilization_aggregate_approved_hours_only(): void
    {
        $user = User::factory()->create();
        $project = ProjectLocation::factory()->create(['user_id' => $user->id, 'project_name' => 'Tol Cisauk']);
        $this->approvedHours($user, $project, 8.5);

        // A DRAFT timesheet must not count
        $booking = Booking::factory()->create(['user_id' => $user->id, 'status' => BookingStatus::CONFIRMED->value, 'project_location_id' => $project->id]);
        $rental = Rental::factory()->create(['booking_id' => $booking->id]);
        $detail = RentalDetail::factory()->create(['rental_id' => $rental->id]);
        Timesheet::factory()->create([
            'rental_detail_id' => $detail->id,
            'status' => TimesheetStatus::DRAFT->value,
            'total_work_hours' => 40,
            'report_date' => now()->toDateString(),
        ]);

        $service = app(ReportingQueryService::class);

        $ts = $service->timesheetSummary();
        $this->assertEqualsWithDelta(8.5, $ts['total_hours'], 0.001);
        $this->assertEquals('Tol Cisauk', $ts['by_project'][0]['project_name']);

        $util = $service->equipmentUtilization();
        $this->assertEqualsWithDelta(8.5, $util['total_hours'], 0.001);
    }

    public function test_financial_summary_and_scoping(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $b1 = Booking::factory()->create(['user_id' => $user->id, 'status' => BookingStatus::CONFIRMED->value]);
        $invoice = Invoice::factory()->create(['booking_id' => $b1->id, 'status' => 'PARTIALLY_PAID', 'grand_total' => 800000, 'paid_amount' => 300000]);
        Payment::factory()->create(['invoice_id' => $invoice->id, 'status' => PaymentStatus::APPROVED, 'amount' => 300000]);
        $refund = Refund::factory()->create(['invoice_id' => $invoice->id, 'status' => RefundStatus::COMPLETED->value, 'amount' => 100000]);

        // Other user's invoice must not leak into scoped summary
        $b2 = Booking::factory()->create(['user_id' => $other->id, 'status' => BookingStatus::CONFIRMED->value]);
        Invoice::factory()->create(['booking_id' => $b2->id, 'status' => 'UNPAID', 'grand_total' => 999000]);

        $service = app(ReportingQueryService::class);

        $scoped = $service->financialSummary([], (int) $user->id);
        $this->assertCount(1, $scoped['invoices']);
        $this->assertEqualsWithDelta(800000.0, (float) $scoped['invoices'][0]['grand_total'], 0.01);
        $this->assertEquals(1, $scoped['payments']['approved_count']);
        $this->assertCount(1, $scoped['refunds']);
        $this->assertEquals(1, $scoped['outstanding']['customer_count']);

        // Scoped is isolated from other customers
        $invoicesOther = collect($scoped['invoices'])->sum('grand_total');
        $this->assertNotEquals(999000, (float) $invoicesOther);

        // Nothing mutated
        $this->assertEquals(800000.0, (float) $invoice->fresh()->grand_total);
        $this->assertEquals(300000.0, (float) $invoice->fresh()->paid_amount);
    }

    public function test_no_n1_on_summary_queries(): void
    {
        $user = User::factory()->create();
        for ($i = 0; $i < 5; $i++) {
            $this->bookingFor($user, BookingStatus::CONFIRMED->value, '2026-09-0'.($i + 1));
        }

        $queries = 0;
        DB::listen(function () use (&$queries) {
            $queries++;
        });

        app(ReportingQueryService::class)->bookingSummary();

        $this->assertLessThan(5, $queries, 'Ringkasan harusnya agregat SQL tanpa N+1.');
    }

    public function test_api_authorization_scopes_user_and_blocks_utilization(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $this->bookingFor($user, BookingStatus::CONFIRMED->value, '2026-09-01');
        $this->bookingFor($other, BookingStatus::CONFIRMED->value, '2026-09-01');

        Sanctum::actingAs($user);
        $res = $this->getJson('/api/v1/reports/bookings');
        $res->assertOk()->assertJsonPath('data.total', 1);

        $this->getJson('/api/v1/reports/equipment-utilization')->assertStatus(403);
        $this->getJson('/api/v1/reports/financial')->assertOk()->assertJsonPath('data.outstanding.customer_count', 0);

        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);
        $this->getJson('/api/v1/reports/bookings')->assertOk()->assertJsonPath('data.total', 2);
        $this->getJson('/api/v1/reports/equipment-utilization')->assertOk();
    }
}
