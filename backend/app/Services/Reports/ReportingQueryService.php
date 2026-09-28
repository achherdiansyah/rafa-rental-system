<?php

namespace App\Services\Reports;

use App\Enums\PaymentStatus;
use App\Enums\RentalStatus;
use App\Enums\TimesheetStatus;
use App\Models\Booking;
use App\Models\EquipmentUnit;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\Rental;
use App\Models\Timesheet;
use App\Services\Finance\CustomerOutstandingService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Read-only reporting queries. Every method aggregates directly in SQL
 * (GROUP BY / SUM) — no lazy per-row fetching, no N+1. Report code never
 * mutates rows. Authorization is enforced by callers (controller).
 */
class ReportingQueryService
{
    public function __construct(
        private readonly CustomerOutstandingService $outstanding
    ) {}

    /**
     * Booking summary by status over a period.
     *
     * @param  array<string, mixed>  $filters  from|to|status|customer_id
     */
    public function bookingSummary(array $filters = [], ?int $scopedUser = null): array
    {
        $query = Booking::query();

        $this->applyPeriod($query, $filters, 'created_at');
        $this->applyEquals($query, $filters, ['status', 'customer_id' => 'user_id']);
        if ($scopedUser) {
            $query->where('user_id', $scopedUser);
        }

        $byStatus = (clone $query)
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->orderBy('status')
            ->get()
            ->mapWithKeys(fn ($row) => [$this->statusValue($row->status) => (int) $row->total])
            ->all();

        $totals = (clone $query)
            ->select(DB::raw('COUNT(*) as total'), DB::raw('COUNT(DISTINCT user_id) as customers'))
            ->first();

        return [
            'period' => $this->period($filters),
            'total' => (int) ($totals->total ?? 0),
            'customer_count' => (int) ($totals->customers ?? 0),
            'by_status' => $byStatus,
        ];
    }

    /**
     * Equipment utilization: approved working hours grouped by model.
     *
     * @param  array<string, mixed>  $filters  from|to|model_id
     */
    public function equipmentUtilization(array $filters = []): array
    {
        $query = Timesheet::query()
            ->where('timesheets.status', TimesheetStatus::APPROVED)
            ->join('rental_details', 'rental_details.id', '=', 'timesheets.rental_detail_id')
            ->join('booking_unit_assignments', 'booking_unit_assignments.id', '=', 'rental_details.assignment_id')
            ->join('equipment_units', 'equipment_units.id', '=', 'booking_unit_assignments.equipment_unit_id')
            ->join('equipment_models', 'equipment_models.id', '=', 'equipment_units.equipment_model_id');

        $this->applyPeriod($query, $filters, 'timesheets.report_date');
        $this->applyEquals($query, $filters, ['model_id' => 'equipment_models.id']);

        $byModel = (clone $query)
            ->select(
                'equipment_models.id as model_id',
                'equipment_models.brand',
                'equipment_models.model_name',
                DB::raw('COALESCE(SUM(timesheets.total_work_hours),0) as total_hours'),
                DB::raw('COUNT(DISTINCT equipment_units.id) as unit_count'),
                DB::raw('COUNT(DISTINCT timesheets.rental_detail_id) as rental_lines')
            )
            ->groupBy('equipment_models.id', 'equipment_models.brand', 'equipment_models.model_name')
            ->orderByDesc('total_hours')
            ->get()
            ->map(function ($row) {
                return [
                    'model_id' => (int) $row->model_id,
                    'model' => trim(($row->brand ?? '').' '.($row->model_name ?? '')),
                    'total_hours' => (float) $row->total_hours,
                    'unit_count' => (int) $row->unit_count,
                    'rental_lines' => (int) $row->rental_lines,
                ];
            })
            ->all();

        $grand = (clone $query)
            ->select(DB::raw('COALESCE(SUM(timesheets.total_work_hours),0) as hours'))
            ->first();

        return [
            'period' => $this->period($filters),
            'total_hours' => (float) ($grand->hours ?? 0),
            'by_model' => $byModel,
        ];
    }

    /**
     * Rental summary by status and project over a period.
     *
     * @param  array<string, mixed>  $filters  from|to|status|project_id
     */
    public function rentalSummary(array $filters = [], ?int $scopedUser = null): array
    {
        $query = Rental::query()
            ->join('bookings', 'bookings.id', '=', 'rentals.booking_id')
            ->join('project_locations', 'project_locations.id', '=', 'bookings.project_location_id');

        $this->applyPeriod($query, $filters, 'rentals.created_at');
        $this->applyEquals($query, $filters, ['status' => 'rentals.status', 'project_id' => 'project_locations.id']);
        if ($scopedUser) {
            $query->where('bookings.user_id', $scopedUser);
        }

        $byStatus = (clone $query)
            ->select('rentals.status', DB::raw('COUNT(*) as total'))
            ->groupBy('rentals.status')->orderBy('rentals.status')
            ->get()
            ->mapWithKeys(fn ($row) => [$this->statusValue($row->status) => (int) $row->total])
            ->all();

        $byProject = (clone $query)
            ->select('project_locations.id as project_id', 'project_locations.project_name', DB::raw('COUNT(*) as total'))
            ->groupBy('project_locations.id', 'project_locations.project_name')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => [
                'project_id' => (int) $row->project_id,
                'project_name' => $row->project_name,
                'total' => (int) $row->total,
            ])
            ->all();

        return [
            'period' => $this->period($filters),
            'total' => array_sum($byStatus),
            'by_status' => $byStatus,
            'by_project' => $byProject,
        ];
    }

    /**
     * Approved timesheet hours summarized by month and project.
     *
     * @param  array<string, mixed>  $filters  from|to|project_id|rental_id
     */
    public function timesheetSummary(array $filters = [], ?int $scopedUser = null): array
    {
        $query = Timesheet::query()
            ->where('timesheets.status', TimesheetStatus::APPROVED)
            ->join('rental_details', 'rental_details.id', '=', 'timesheets.rental_detail_id')
            ->join('rentals', 'rentals.id', '=', 'rental_details.rental_id')
            ->join('bookings', 'bookings.id', '=', 'rentals.booking_id')
            ->join('project_locations', 'project_locations.id', '=', 'bookings.project_location_id');

        $this->applyPeriod($query, $filters, 'timesheets.report_date');
        $this->applyEquals($query, $filters, ['project_id' => 'project_locations.id', 'rental_id' => 'rentals.id']);
        if ($scopedUser) {
            $query->where('bookings.user_id', $scopedUser);
        }

        $byMonth = (clone $query)
            ->select(DB::raw("DATE_FORMAT(timesheets.report_date, '%Y-%m') as month"), DB::raw('COALESCE(SUM(timesheets.total_work_hours),0) as hours'))
            ->groupBy(DB::raw("DATE_FORMAT(timesheets.report_date, '%Y-%m')"))
            ->orderBy('month')
            ->get()
            ->map(fn ($row) => ['month' => $row->month, 'total_hours' => (float) $row->hours])
            ->all();

        $byProject = (clone $query)
            ->select('project_locations.project_name', DB::raw('COALESCE(SUM(timesheets.total_work_hours),0) as hours'))
            ->groupBy('project_locations.project_name')
            ->orderByDesc('hours')
            ->get()
            ->map(fn ($row) => ['project_name' => $row->project_name, 'total_hours' => (float) $row->hours])
            ->all();

        $sum = (clone $query)
            ->select(DB::raw('COALESCE(SUM(timesheets.total_work_hours),0) as hours'))
            ->first();

        return [
            'period' => $this->period($filters),
            'total_hours' => (float) ($sum->hours ?? 0),
            'by_month' => $byMonth,
            'by_project' => $byProject,
        ];
    }

    /**
     * Financial summary: invoice, payment, refund, outstanding.
     *
     * @param  array<string, mixed>  $filters  from|to
     */
    public function financialSummary(array $filters = [], ?int $scopedUser = null): array
    {
        $invoiceQuery = Invoice::query();
        $this->applyPeriod($invoiceQuery, $filters, 'created_at');
        if ($scopedUser) {
            $invoiceQuery->whereHas('booking', fn ($q) => $q->where('user_id', $scopedUser));
        }

        $invoiceTotals = (clone $invoiceQuery)
            ->select(
                'status',
                DB::raw('COUNT(*) as count'),
                DB::raw('COALESCE(SUM(grand_total),0) as grand_total'),
                DB::raw('COALESCE(SUM(paid_amount),0) as paid_total')
            )
            ->groupBy('status')->orderBy('status')
            ->get()
            ->map(fn ($row) => [
                'status' => $this->statusValue($row->status),
                'count' => (int) $row->count,
                'grand_total' => (float) $row->grand_total,
                'paid_total' => (float) $row->paid_total,
                'balance' => (float) $row->grand_total - (float) $row->paid_total,
            ])
            ->all();

        $paymentsQuery = Payment::query()->where('status', PaymentStatus::APPROVED);
        $this->applyPeriod($paymentsQuery, $filters, 'created_at');
        if ($scopedUser) {
            $paymentsQuery->whereHas('invoice.booking', fn ($q) => $q->where('user_id', $scopedUser));
        }
        $paymentTotal = (clone $paymentsQuery)
            ->select(DB::raw('COUNT(*) as count'), DB::raw('COALESCE(SUM(amount),0) as amount'))
            ->first();

        $refundQuery = Refund::query();
        $this->applyPeriod($refundQuery, $filters, 'created_at');
        if ($scopedUser) {
            $refundQuery->whereHas('invoice.booking', fn ($q) => $q->where('user_id', $scopedUser));
        }
        $refundTotal = (clone $refundQuery)
            ->select('status', DB::raw('COUNT(*) as count'), DB::raw('COALESCE(SUM(amount),0) as amount'))
            ->groupBy('status')->orderBy('status')
            ->get()
            ->map(fn ($row) => ['status' => $this->statusValue($row->status), 'count' => (int) $row->count, 'amount' => (float) $row->amount])
            ->all();

        // Outstanding reuse (read-only aggregation service)
        $outstanding = [];
        if ($scopedUser) {
            $customer = $this->outstanding->forCustomer($scopedUser);
            if ($customer['open_invoice_count'] > 0 || $customer['total_outstanding'] > 0) {
                $outstanding[] = $customer;
            }
        } else {
            $outstanding = $this->outstanding->allCustomers();
        }

        return [
            'period' => $this->period($filters),
            'invoices' => $invoiceTotals,
            'payments' => [
                'approved_count' => (int) ($paymentTotal->count ?? 0),
                'approved_amount' => (float) ($paymentTotal->amount ?? 0),
            ],
            'refunds' => $refundTotal,
            'outstanding' => [
                'customer_count' => count($outstanding),
                'total_outstanding' => array_sum(array_column($outstanding, 'total_outstanding')),
                'customers' => $outstanding,
            ],
        ];
    }

    /**
     * Physical equipment fleet status counts (admin/owner).
     *
     * @param  array<string, mixed>  $filters  unused (fleet snapshot)
     */
    public function equipmentStatusCounts(array $filters = []): array
    {
        $rows = EquipmentUnit::query()
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->orderBy('status')
            ->get();

        $counts = [];
        $fleet = 0;
        foreach ($rows as $row) {
            $key = $this->statusValue($row->status);
            $counts[$key] = (int) $row->total;
            $fleet += (int) $row->total;
        }

        return [
            'fleet_total' => $fleet,
            'available' => $counts['AVAILABLE'] ?? 0,
            'in_use' => ($counts['ON_SITE'] ?? 0) + ($counts['MOBILIZING'] ?? 0) + ($counts['DEMOBILIZING'] ?? 0) + ($counts['RETURN_INSPECTION'] ?? 0),
            'maintenance' => $counts['MAINTENANCE'] ?? 0,
            'by_status' => $counts,
        ];
    }

    /**
     * Single-payload admin/owner dashboard KPI bundle (one API request).
     *
     * @param  array<string, mixed>  $filters
     */
    public function dashboard(array $filters = []): array
    {
        $bookings = $this->bookingSummary($filters);
        $rentals = $this->rentalSummary($filters);
        $timesheet = $this->timesheetSummary($filters);
        $utilization = $this->equipmentUtilization($filters);
        $fleet = $this->equipmentStatusCounts();
        $financial = $this->financialSummary($filters);

        return [
            'period' => $bookings['period'],
            'bookings' => [
                'total' => $bookings['total'],
                'by_status' => $bookings['by_status'],
            ],
            'rentals' => [
                'total' => $rentals['total'],
                'active' => $rentals['by_status'][RentalStatus::ONGOING->value] ?? 0,
                'by_status' => $rentals['by_status'],
                'by_project' => $rentals['by_project'],
            ],
            'timesheet' => [
                'total_hours' => $timesheet['total_hours'],
                'by_month' => $timesheet['by_month'],
            ],
            'equipment' => [
                'fleet_total' => $fleet['fleet_total'],
                'available' => $fleet['available'],
                'in_use' => $fleet['in_use'],
                'maintenance' => $fleet['maintenance'],
                'utilization_hours' => $utilization['total_hours'],
                'top_models' => array_slice($utilization['by_model'], 0, 5),
            ],
            'financial' => [
                'invoices' => $financial['invoices'],
                'payments' => $financial['payments'],
                'refunds' => $financial['refunds'],
            ],
            'outstanding' => [
                'customer_count' => $financial['outstanding']['customer_count'],
                'total' => $financial['outstanding']['total_outstanding'],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function period(array $filters): array
    {
        return [
            'from' => isset($filters['from']) ? Carbon::parse($filters['from'])->toDateString() : null,
            'to' => isset($filters['to']) ? Carbon::parse($filters['to'])->toDateString() : null,
        ];
    }

    private function statusValue(mixed $status): string
    {
        return $status instanceof \BackedEnum ? $status->value : (string) $status;
    }

    private function applyPeriod($query, array $filters, string $column): void
    {
        if (! empty($filters['from'])) {
            $query->whereDate($column, '>=', Carbon::parse($filters['from'])->toDateString());
        }
        if (! empty($filters['to'])) {
            $query->whereDate($column, '<=', Carbon::parse($filters['to'])->toDateString());
        }
    }

    /**
     * @param  array<string, mixed>  $filters
     * @param  array<int|string, string>  $map  [filterKey => column]
     */
    private function applyEquals($query, array $filters, array $map): void
    {
        foreach ($map as $key => $column) {
            $filterKey = is_int($key) ? $column : $key;
            if (! empty($filters[$filterKey])) {
                $query->where($column, $filters[$filterKey]);
            }
        }
    }
}
