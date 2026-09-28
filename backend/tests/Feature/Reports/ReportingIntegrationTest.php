<?php

namespace Tests\Feature\Reports;

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
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
use App\Services\Reports\FinancialReportService;
use App\Services\Reports\OperationalReportService;
use App\Services\Reports\ReportingQueryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReportingIntegrationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Realistic seed: two customers, rentals, timesheets, invoices, payments, refund.
     */
    private function seedWorld(): array
    {
        $alice = User::factory()->create(['name' => 'PT Mitra Sejahtera']);
        $bob = User::factory()->create(['name' => 'PT Konstruksi Nusantara']);

        $projectA = ProjectLocation::factory()->create(['user_id' => $alice->id, 'project_name' => 'Tol Cisauk']);
        $projectB = ProjectLocation::factory()->create(['user_id' => $bob->id, 'project_name' => 'Jalan Akses Terminal A']);

        // Bookings
        Booking::factory()->create(['user_id' => $alice->id, 'status' => BookingStatus::CONFIRMED->value, 'project_location_id' => $projectA->id, 'created_at' => '2026-09-02']);
        Booking::factory()->create(['user_id' => $alice->id, 'status' => BookingStatus::DRAFT->value, 'project_location_id' => $projectA->id, 'created_at' => '2026-09-03']);
        Booking::factory()->create(['user_id' => $bob->id, 'status' => BookingStatus::PENDING_APPROVAL->value, 'project_location_id' => $projectB->id, 'created_at' => '2026-09-04']);

        // Rental A (completed, hours)
        $model = EquipmentModel::factory()->create(['brand' => 'Komatsu', 'model_name' => 'PC200', 'is_active' => true]);
        $unitA = EquipmentUnit::factory()->create(['equipment_model_id' => $model->id, 'status' => 'AVAILABLE']);
        $rentalA = $this->rentalWithHours($alice, $projectA, $unitA, $model, 42.5, RentalStatus::COMPLETED->value);

        // Rental B (ongoing, hours)
        $unitB = EquipmentUnit::factory()->create(['equipment_model_id' => $model->id, 'status' => 'ON_SITE']);
        $rentalB = $this->rentalWithHours($bob, $projectB, $unitB, $model, 12.75, RentalStatus::ONGOING->value);

        // Invoices: Alice partial (paid 300k/Qty via approved) + Bob outstanding
        $invoiceA = Invoice::factory()->create(['booking_id' => $rentalA['booking']->id, 'status' => 'PARTIALLY_PAID', 'grand_total' => 1000000, 'paid_amount' => 300000]);
        Payment::factory()->create(['invoice_id' => $invoiceA->id, 'status' => PaymentStatus::APPROVED, 'amount' => 300000]);
        Payment::factory()->create(['invoice_id' => $invoiceA->id, 'status' => PaymentStatus::REJECTED, 'amount' => 50000]);

        $invoiceB = Invoice::factory()->create(['booking_id' => $rentalB['booking']->id, 'status' => 'OVERDUE', 'grand_total' => 500000, 'paid_amount' => 0]);
        $invoiceOver = Invoice::factory()->create(['booking_id' => $rentalA['booking']->id, 'status' => 'OVERPAID', 'grand_total' => 800000, 'paid_amount' => 800000, 'overpayment_amount' => 200000]);
        Payment::factory()->create(['invoice_id' => $invoiceOver->id, 'status' => PaymentStatus::APPROVED, 'amount' => 1000000]);
        Refund::factory()->create(['invoice_id' => $invoiceOver->id, 'source' => 'OVERPAYMENT', 'status' => 'COMPLETED', 'amount' => 200000]);

        return [$alice, $bob];
    }

    private function rentalWithHours(User $user, ProjectLocation $project, EquipmentUnit $unit, EquipmentModel $model, float $hours, string $rentalStatus): array
    {
        $booking = Booking::factory()->create(['user_id' => $user->id, 'status' => BookingStatus::CONFIRMED->value, 'project_location_id' => $project->id]);
        $bd = BookingDetail::factory()->create(['booking_id' => $booking->id, 'equipment_model_id' => $model->id, 'quantity' => 1]);
        $assignment = BookingUnitAssignment::factory()->create(['booking_detail_id' => $bd->id, 'equipment_unit_id' => $unit->id, 'status' => 'ASSIGNED', 'is_current' => true, 'assigned_by' => $user->id]);
        $rental = Rental::factory()->create(['booking_id' => $booking->id, 'status' => $rentalStatus]);
        $rd = RentalDetail::factory()->create(['rental_id' => $rental->id, 'assignment_id' => $assignment->id]);
        Timesheet::factory()->create(['rental_detail_id' => $rd->id, 'status' => TimesheetStatus::APPROVED->value, 'total_work_hours' => $hours, 'report_date' => now()->subDays(3)->toDateString()]);

        return ['booking' => $booking, 'rental' => $rental, 'rd' => $rd, 'unit' => $unit];
    }

    public function test_dashboard_kpis_match_seeded_database(): void
    {
        [$alice, $bob] = $this->seedWorld();

        $dash = app(ReportingQueryService::class)->dashboard(['from' => '2026-09-01', 'to' => '2026-09-30']);

        $this->assertEquals(5, $dash['bookings']['total'], '3 seed + 2 dari helper rental.');
        $this->assertEquals(1, $dash['rentals']['active'], 'Hanya ONGOING yang aktif.');
        $this->assertEqualsWithDelta(55.25, $dash['timesheet']['total_hours'], 0.001); // 42.5 + 12.75
        $this->assertEquals(1, $dash['equipment']['available']);
        $this->assertEquals(1, $dash['equipment']['in_use']);
        $this->assertEquals(3, collect($dash['financial']['invoices'])->sum('count'));
        $this->assertEqualsWithDelta(300000.0, collect($dash['financial']['invoices'])->where('status', 'PARTIALLY_PAID')->first()['paid_total'], 0.01);
        $this->assertEquals(2, $dash['outstanding']['customer_count']);
    }

    public function test_operational_reports_rows_filters_sort_pagination(): void
    {
        [$alice, $bob] = $this->seedWorld();

        // Timesheet rows: 2 approved
        $page = app(OperationalReportService::class)
            ->timesheetReport(['from' => '2026-09-01', 'to' => '2026-09-30', 'per_page' => 10]);
        $this->assertEquals(2, $page->total());
        $this->assertEquals(1, $page->lastPage());

        // Sort ascending by hours
        $sorted = app(OperationalReportService::class)
            ->timesheetReport(['sort_by' => 'total_work_hours', 'sort_dir' => 'asc']);
        $this->assertEquals(12.75, $sorted->items()[0]['total_work_hours']);

        // Rental utilization: activity rows count
        $activity = app(OperationalReportService::class)->projectCustomerActivity();
        $this->assertEquals(2, $activity->total());
    }

    public function test_financial_reports_approved_basis_and_filters(): void
    {
        [$alice, $bob] = $this->seedWorld();

        $service = app(FinancialReportService::class);

        // Invoices: approved basis — REJECTED 50k excluded from ALICE paid
        $invoices = $service->invoiceReport(['customer_id' => (string) $alice->id]);
        $this->assertEquals(2, $invoices->total()); // PARTIALLY_PAID + OVERPAID
        $partial = collect($invoices->items())->firstWhere('status', 'PARTIALLY_PAID');
        $this->assertEqualsWithDelta(300000.0, $partial['paid_total'], 0.01);
        $this->assertEqualsWithDelta(700000.0, $partial['balance'], 0.01);

        // Outstanding open statuses only (bob OVERDUE); paid/overpaid excluded
        $out = $service->outstandingReport(['customer_id' => (string) $bob->id]);
        $this->assertEquals(1, $out->total());
        $this->assertEquals('OVERDUE', $out->items()[0]['status']);
        $this->assertTrue($out->items()[0]['overdue_flag']);

        // Overpayment: excess + refunded snapshot
        $over = $service->overpaymentReport(['customer_id' => (string) $alice->id]);
        $this->assertEquals(1, $over->total());
        $this->assertEqualsWithDelta(200000.0, $over->items()[0]['overpayment_amount'], 0.01);
        $this->assertEqualsWithDelta(200000.0, $over->items()[0]['refunded_total'], 0.01);
    }

    public function test_export_follows_filter_and_authorization(): void
    {
        [$alice, $bob] = $this->seedWorld();

        Sanctum::actingAs($alice);
        $res = $this->get('/api/v1/reports/export/bookings');
        $res->assertOk();
        $body = $res->streamedContent();
        $this->assertStringNotContainsString('PT Konstruksi Nusantara', $body, 'Export user discoped ke miliknya.');
        $this->assertStringContainsString('PT Mitra Sejahtera', $body);

        // Equipment export blocked for USER
        $this->get('/api/v1/reports/export/equipment')->assertStatus(403);

        // Admin export payments includes all approved rows
        Sanctum::actingAs(User::factory()->admin()->create());
        $payments = $this->get('/api/v1/reports/export/payments');
        $payments->assertOk();
        $this->assertStringContainsString('payment_date', $payments->streamedContent());
    }

    public function test_no_n1_and_no_transaction_mutation(): void
    {
        [, $bob] = $this->seedWorld();

        $snapshot = [
            'invoice_overpaid' => (float) Invoice::where('status', 'OVERPAID')->value('overpayment_amount'),
            'timesheet_approved' => (int) Timesheet::where('status', 'APPROVED')->count(),
            'units' => EquipmentUnit::count(),
        ];

        $queries = 0;
        DB::listen(function () use (&$queries) {
            $queries++;
        });
        $service = app(FinancialReportService::class);
        $service->invoiceReport();
        $service->partialReport();
        $service->outstandingReport(['customer_id' => (string) $bob->id]);

        $this->assertLessThan(12, $queries, 'Laporan finansial harus agregat, tanpa N+1 untuk dataset kecil.');

        // No mutation of transactions
        $this->assertEquals($snapshot['invoice_overpaid'], (float) Invoice::where('status', 'OVERPAID')->value('overpayment_amount'));
        $this->assertEquals($snapshot['timesheet_approved'], (int) Timesheet::where('status', 'APPROVED')->count());
        $this->assertEquals($snapshot['units'], EquipmentUnit::count());
        $this->assertEquals('AVAILABLE', EquipmentUnit::where('status', 'AVAILABLE')->first()->status->value);
    }
}
