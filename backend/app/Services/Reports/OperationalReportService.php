<?php

namespace App\Services\Reports;

use App\Enums\TimesheetStatus;
use App\Services\Reports\Concerns\FiltersAndPagination;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Row-level (drillable) operational reports. Read-only; filters + pagination +
 * sorting on aggregated SQL (aliased subqueries). Every row carries source IDs
 * so the UI can link back to the transaction. USER scope enforced upstream.
 */
class OperationalReportService
{
    use FiltersAndPagination;

    /**
     * Booking listing.
     *
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function bookingReport(array $filters = [], ?int $scopedUser = null): LengthAwarePaginator
    {
        $query = DB::table('bookings as b')
            ->leftJoin('users as u', 'u.id', '=', 'b.user_id')
            ->leftJoin('project_locations as pl', 'pl.id', '=', 'b.project_location_id')
            ->select(
                'b.id', 'b.booking_code', 'b.status', 'b.user_id', 'b.project_location_id',
                'b.total_amount', 'b.created_at', 'u.name as customer_name', 'pl.project_name', 'pl.city'
            );

        $this->applyPlain($query, $filters, [
            'from' => ['b.created_at', 'date_min'],
            'to' => ['b.created_at', 'date_max'],
            'status' => ['b.status', 'eq'],
            'customer_id' => ['b.user_id', 'eq'],
            'project_id' => ['b.project_location_id', 'eq'],
        ]);
        if ($scopedUser) {
            $query->where('b.user_id', $scopedUser);
        }

        return $this->paginate($query, $filters, ['id desc' => 'b.id', 'booking_code' => 'b.booking_code', 'created_at' => 'b.created_at', 'status' => 'b.status'])
            ->through(fn ($row) => [
                'id' => (int) $row->id,
                'booking_code' => $row->booking_code,
                'status' => $this->enumValue($row->status),
                'customer_name' => $row->customer_name,
                'project_name' => $row->project_name,
                'city' => $row->city,
                'total_amount' => (float) $row->total_amount,
                'created_at' => $row->created_at,
                'customer_id' => (int) $row->user_id,
                'project_location_id' => (int) ($row->project_location_id ?? 0),
            ]);
    }

    /**
     * Timesheet (actual working hours) listing.
     *
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function timesheetReport(array $filters = [], ?int $scopedUser = null): LengthAwarePaginator
    {
        $query = DB::table('timesheets as t')
            ->join('rental_details as rd', 'rd.id', '=', 't.rental_detail_id')
            ->join('rentals as rl', 'rl.id', '=', 'rd.rental_id')
            ->join('bookings as b', 'b.id', '=', 'rl.booking_id')
            ->join('project_locations as pl', 'pl.id', '=', 'b.project_location_id')
            ->leftJoin('booking_unit_assignments as bua', 'bua.id', '=', 'rd.assignment_id')
            ->leftJoin('equipment_units as eu', 'eu.id', '=', 'bua.equipment_unit_id')
            ->leftJoin('equipment_models as em', 'em.id', '=', 'eu.equipment_model_id')
            ->select(
                't.id', 't.report_date', 't.status', 't.start_hm', 't.end_hm', 't.break_minutes',
                't.total_work_hours', 't.standby_hours', 't.breakdown_hours', 't.operator_name',
                't.rental_detail_id', 'eu.id as unit_id', 'eu.serial_number', 'em.brand', 'em.model_name',
                'pl.project_name', 'b.booking_code'
            );

        $this->applyPlain($query, $filters, [
            'from' => ['t.report_date', 'date_min'],
            'to' => ['t.report_date', 'date_max'],
            'status' => ['t.status', 'eq'],
            'project_id' => ['pl.id', 'eq'],
            'model_id' => ['em.id', 'eq'],
            'unit_id' => ['eu.id', 'eq'],
            'rental_id' => ['rl.id', 'eq'],
        ]);
        if ($scopedUser) {
            $query->where('b.user_id', $scopedUser);
        }

        return $this->paginate($query, $filters, ['report_date desc' => 't.report_date', 'total_work_hours' => 't.total_work_hours'])
            ->through(fn ($row) => [
                'id' => (int) $row->id,
                'report_date' => (string) $row->report_date,
                'status' => $this->enumValue($row->status),
                'start_hm' => (float) $row->start_hm,
                'end_hm' => (float) $row->end_hm,
                'break_minutes' => (int) $row->break_minutes,
                'total_work_hours' => (float) $row->total_work_hours,
                'standby_hours' => (float) $row->standby_hours,
                'breakdown_hours' => (float) $row->breakdown_hours,
                'operator_name' => $row->operator_name,
                'unit_serial' => $row->serial_number,
                'model' => trim(($row->brand ?? '').' '.($row->model_name ?? '')),
                'project_name' => $row->project_name,
                'booking_code' => $row->booking_code,
                'rental_detail_id' => (int) $row->rental_detail_id,
                'unit_id' => (int) ($row->unit_id ?? 0),
            ]);
    }

    /**
     * Rental/equipment utilization at rental-detail level.
     *
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function rentalUtilization(array $filters = [], ?int $scopedUser = null): LengthAwarePaginator
    {
        $hours = DB::table('timesheets')
            ->select('rental_detail_id', DB::raw('COALESCE(SUM(total_work_hours),0) as total_hours'))
            ->where('status', TimesheetStatus::APPROVED->value)
            ->groupBy('rental_detail_id');

        $query = DB::table('rental_details as rd')
            ->join('rentals as rl', 'rl.id', '=', 'rd.rental_id')
            ->join('bookings as b', 'b.id', '=', 'rl.booking_id')
            ->join('project_locations as pl', 'pl.id', '=', 'b.project_location_id')
            ->leftJoin('booking_unit_assignments as bua', 'bua.id', '=', 'rd.assignment_id')
            ->leftJoin('equipment_units as eu', 'eu.id', '=', 'bua.equipment_unit_id')
            ->leftJoin('equipment_models as em', 'em.id', '=', 'eu.equipment_model_id')
            ->leftJoinSub($hours, 'h', 'h.rental_detail_id', '=', 'rd.id')
            ->select(
                'rd.id as rental_detail_id', 'rl.id as rental_id', 'b.booking_code', 'rl.status as rental_status',
                'rl.started_at', 'rl.completed_at', 'pl.project_name', 'pl.city',
                'eu.id as unit_id', 'eu.serial_number', 'em.brand', 'em.model_name', 'h.total_hours'
            );

        $this->applyPlain($query, $filters, [
            'from' => ['rl.created_at', 'date_min'],
            'to' => ['rl.created_at', 'date_max'],
            'status' => ['rl.status', 'eq'],
            'project_id' => ['pl.id', 'eq'],
            'model_id' => ['em.id', 'eq'],
            'unit_id' => ['eu.id', 'eq'],
            'rental_id' => ['rl.id', 'eq'],
        ]);
        if ($scopedUser) {
            $query->where('b.user_id', $scopedUser);
        }

        return $this->paginate($query, $filters, ['rental_id desc' => 'rl.id', 'total_hours' => 'h.total_hours', 'status' => 'rl.status'])
            ->through(fn ($row) => [
                'rental_id' => (int) $row->rental_id,
                'booking_code' => $row->booking_code,
                'rental_status' => $this->enumValue($row->rental_status),
                'started_at' => $row->started_at,
                'completed_at' => $row->completed_at,
                'project_name' => $row->project_name,
                'city' => $row->city,
                'unit_serial' => $row->serial_number,
                'model' => trim(($row->brand ?? '').' '.($row->model_name ?? '')),
                'total_work_hours' => (float) ($row->total_hours ?? 0),
                'rental_detail_id' => (int) $row->rental_detail_id,
                'unit_id' => (int) ($row->unit_id ?? 0),
            ]);
    }

    /**
     * Equipment current status + cumulative approved hours.
     *
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function equipmentReport(array $filters = []): LengthAwarePaginator
    {
        $hours = DB::table('timesheets')
            ->join('rental_details', 'rental_details.id', '=', 'timesheets.rental_detail_id')
            ->join('booking_unit_assignments', 'booking_unit_assignments.id', '=', 'rental_details.assignment_id')
            ->select('booking_unit_assignments.equipment_unit_id', DB::raw('COALESCE(SUM(timesheets.total_work_hours),0) as total_hours'))
            ->where('timesheets.status', TimesheetStatus::APPROVED->value)
            ->groupBy('booking_unit_assignments.equipment_unit_id');

        $query = DB::table('equipment_units as eu')
            ->join('equipment_models as em', 'em.id', '=', 'eu.equipment_model_id')
            ->leftJoinSub($hours, 'h', 'h.equipment_unit_id', '=', 'eu.id')
            ->select('eu.id', 'eu.serial_number', 'eu.plate_number', 'eu.status', 'eu.equipment_model_id', 'em.brand', 'em.model_name', 'h.total_hours');

        $this->applyPlain($query, $filters, [
            'status' => ['eu.status', 'eq'],
            'model_id' => ['em.id', 'eq'],
            'unit_id' => ['eu.id', 'eq'],
        ]);

        return $this->paginate($query, $filters, ['id asc' => 'eu.id', 'status' => 'eu.status', 'total_hours' => 'h.total_hours'])
            ->through(fn ($row) => [
                'id' => (int) $row->id,
                'serial_number' => $row->serial_number,
                'plate_number' => $row->plate_number,
                'status' => $this->enumValue($row->status),
                'model' => trim(($row->brand ?? '').' '.($row->model_name ?? '')),
                'model_id' => (int) $row->equipment_model_id,
                'total_work_hours' => (float) ($row->total_hours ?? 0),
            ]);
    }

    /**
     * Project / customer rental activity (hours + invoiced paid).
     *
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function projectCustomerActivity(array $filters = [], ?int $scopedUser = null): LengthAwarePaginator
    {
        $hours = DB::table('timesheets')
            ->join('rental_details', 'rental_details.id', '=', 'timesheets.rental_detail_id')
            ->select('rental_details.rental_id', DB::raw('COALESCE(SUM(timesheets.total_work_hours),0) as total_hours'))
            ->where('timesheets.status', TimesheetStatus::APPROVED->value)
            ->groupBy('rental_details.rental_id');

        $paid = DB::table('invoices')
            ->select('booking_id', DB::raw('COALESCE(SUM(paid_amount),0) as paid_total'))
            ->groupBy('booking_id');

        $query = DB::table('rentals as rl')
            ->join('bookings as b', 'b.id', '=', 'rl.booking_id')
            ->join('users as u', 'u.id', '=', 'b.user_id')
            ->join('project_locations as pl', 'pl.id', '=', 'b.project_location_id')
            ->leftJoinSub($hours, 'h', 'h.rental_id', '=', 'rl.id')
            ->leftJoinSub($paid, 'p', 'p.booking_id', '=', 'b.id')
            ->select(
                'rl.id as rental_id', 'b.booking_code', 'rl.status', 'rl.started_at', 'rl.completed_at',
                'u.name as customer_name', 'b.user_id as customer_id', 'b.project_location_id',
                'pl.project_name', 'pl.city', 'h.total_hours', 'p.paid_total'
            );

        $this->applyPlain($query, $filters, [
            'from' => ['rl.created_at', 'date_min'],
            'to' => ['rl.created_at', 'date_max'],
            'status' => ['rl.status', 'eq'],
            'project_id' => ['pl.id', 'eq'],
            'customer_id' => ['b.user_id', 'eq'],
        ]);
        if ($scopedUser) {
            $query->where('b.user_id', $scopedUser);
        }

        return $this->paginate($query, $filters, ['rental_id desc' => 'rl.id', 'total_hours' => 'h.total_hours', 'paid_total' => 'p.paid_total'])
            ->through(fn ($row) => [
                'rental_id' => (int) $row->rental_id,
                'booking_code' => $row->booking_code,
                'status' => $this->enumValue($row->status),
                'customer_name' => $row->customer_name,
                'project_name' => $row->project_name,
                'city' => $row->city,
                'started_at' => $row->started_at,
                'completed_at' => $row->completed_at,
                'total_hours' => (float) ($row->total_hours ?? 0),
                'paid_total' => (float) ($row->paid_total ?? 0),
                'customer_id' => (int) ($row->customer_id ?? 0),
                'project_location_id' => (int) ($row->project_location_id ?? 0),
            ]);
    }
}
