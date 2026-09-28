<?php

namespace App\Services\Reports;

use App\Enums\PaymentStatus;
use App\Enums\RefundSource;
use App\Services\Reports\Concerns\FiltersAndPagination;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Row-level financial reports. Approved payments are the ONLY basis for paid
 * figures (leftJoinSub over APPROVED payments). No new financial rules are
 * invented here — totals/outstanding/overpayment/refund are reported as
 * recorded. Read-only; history stays immutable.
 */
class FinancialReportService
{
    use FiltersAndPagination;

    public const OPEN_STATUSES = ['ISSUED', 'UNPAID', 'PARTIALLY_PAID', 'OVERDUE'];

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function invoiceReport(array $filters = [], ?int $scopedUser = null): LengthAwarePaginator
    {
        $paid = $this->approvedPayments();

        $query = $this->invoiceBase($paid);

        $this->applyPlain($query, $filters, [
            'from' => ['i.created_at', 'date_min'],
            'to' => ['i.created_at', 'date_max'],
            'status' => ['i.status', 'eq'],
            'customer_id' => ['b.user_id', 'eq'],
            'project_id' => ['pl.id', 'eq'],
        ]);
        if ($scopedUser) {
            $query->where('b.user_id', $scopedUser);
        }

        return $this->paginate($query, $filters, ['id desc' => 'i.id', 'invoice_number' => 'i.invoice_number', 'grand_total' => 'i.grand_total', 'status' => 'i.status'])
            ->through(fn ($row) => $this->invoiceRow($row));
    }

    /**
     * Payments listing; paid basis naturally approved; other statuses shown.
     *
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function paymentReport(array $filters = [], ?int $scopedUser = null): LengthAwarePaginator
    {
        $query = DB::table('payments as pm')
            ->join('invoices as i', 'i.id', '=', 'pm.invoice_id')
            ->join('bookings as b', 'b.id', '=', 'i.booking_id')
            ->join('users as u', 'u.id', '=', 'b.user_id')
            ->join('project_locations as pl', 'pl.id', '=', 'b.project_location_id')
            ->select(
                'pm.id', 'pm.status', 'pm.amount', 'pm.payment_date', 'pm.sender_name', 'pm.reference',
                'pm.rejection_reason', 'pm.created_at', 'pm.invoice_id', 'i.invoice_number', 'b.booking_code',
                'u.name as customer_name', 'u.id as customer_id', 'pl.project_name'
            );

        $this->applyPlain($query, $filters, [
            'from' => ['pm.payment_date', 'date_min'],
            'to' => ['pm.payment_date', 'date_max'],
            'status' => ['pm.status', 'eq'],
            'customer_id' => ['b.user_id', 'eq'],
            'invoice_id' => ['pm.invoice_id', 'eq'],
        ]);
        if ($scopedUser) {
            $query->where('b.user_id', $scopedUser);
        }

        return $this->paginate($query, $filters, ['id desc' => 'pm.id', 'payment_date' => 'pm.payment_date', 'amount' => 'pm.amount'])
            ->through(fn ($row) => [
                'id' => (int) $row->id,
                'status' => $this->enumValue($row->status),
                'amount' => (float) $row->amount,
                'payment_date' => (string) ($row->payment_date ?? ''),
                'sender_name' => $row->sender_name,
                'reference' => $row->reference,
                'rejection_reason' => $row->rejection_reason,
                'created_at' => $row->created_at,
                'invoice_id' => (int) $row->invoice_id,
                'invoice_number' => $row->invoice_number,
                'booking_code' => $row->booking_code,
                'customer_name' => $row->customer_name,
                'customer_id' => (int) ($row->customer_id ?? 0),
                'project_name' => $row->project_name,
            ]);
    }

    /**
     * Partially paid invoices with approved-payment detail.
     *
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function partialReport(array $filters = [], ?int $scopedUser = null): LengthAwarePaginator
    {
        $paid = $this->approvedPayments();
        $meta = DB::table('payments')
            ->select('invoice_id', DB::raw('COUNT(*) as approved_count'), DB::raw('MAX(payment_date) as last_payment_at'))
            ->where('status', PaymentStatus::APPROVED->value)
            ->groupBy('invoice_id');

        $query = $this->invoiceBase($paid)
            ->leftJoinSub($meta, 'm', 'm.invoice_id', '=', 'i.id')
            ->where('i.status', 'PARTIALLY_PAID')
            ->addSelect('m.approved_count', 'm.last_payment_at');

        $this->applyPlain($query, $filters, [
            'from' => ['i.created_at', 'date_min'],
            'to' => ['i.created_at', 'date_max'],
            'customer_id' => ['b.user_id', 'eq'],
            'project_id' => ['pl.id', 'eq'],
        ]);
        if ($scopedUser) {
            $query->where('b.user_id', $scopedUser);
        }

        return $this->paginate($query, $filters, ['id desc' => 'i.id', 'grand_total' => 'i.grand_total'])
            ->through(fn ($row) => array_merge($this->invoiceRow($row), [
                'approved_count' => (int) ($row->approved_count ?? 0),
                'last_payment_at' => $row->last_payment_at,
            ]));
    }

    /**
     * Open (outstanding) invoices per customer.
     *
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function outstandingReport(array $filters = [], ?int $scopedUser = null): LengthAwarePaginator
    {
        $paid = $this->approvedPayments();

        $query = $this->invoiceBase($paid)
            ->whereIn('i.status', self::OPEN_STATUSES);

        $this->applyPlain($query, $filters, [
            'from' => ['i.due_at', 'date_min'],
            'to' => ['i.due_at', 'date_max'],
            'status' => ['i.status', 'eq'],
            'customer_id' => ['b.user_id', 'eq'],
            'project_id' => ['pl.id', 'eq'],
        ]);
        if ($scopedUser) {
            $query->where('b.user_id', $scopedUser);
        }

        return $this->paginate($query, $filters, ['id desc' => 'i.id', 'grand_total' => 'i.grand_total'])
            ->through(fn ($row) => array_merge($this->invoiceRow($row), [
                'overdue_flag' => $this->enumValue($row->status) === 'OVERDUE',
            ]));
    }

    /**
     * Overpaid invoices: parked excess + refunded snapshot (approved basis).
     *
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function overpaymentReport(array $filters = [], ?int $scopedUser = null): LengthAwarePaginator
    {
        $paid = $this->approvedPayments();
        $refunded = DB::table('refunds')
            ->select('invoice_id', DB::raw('COALESCE(SUM(amount),0) as refunded_total'))
            ->where('source', RefundSource::OVERPAYMENT->value)
            ->where('status', '!=', 'FAILED')
            ->groupBy('invoice_id');

        $query = $this->invoiceBase($paid)
            ->leftJoinSub($refunded, 'rf', 'rf.invoice_id', '=', 'i.id')
            ->where('i.status', 'OVERPAID')
            ->addSelect('rf.refunded_total');

        $this->applyPlain($query, $filters, [
            'from' => ['i.created_at', 'date_min'],
            'to' => ['i.created_at', 'date_max'],
            'customer_id' => ['b.user_id', 'eq'],
            'project_id' => ['pl.id', 'eq'],
        ]);
        if ($scopedUser) {
            $query->where('b.user_id', $scopedUser);
        }

        return $this->paginate($query, $filters, ['id desc' => 'i.id', 'overpayment_amount' => 'i.overpayment_amount'])
            ->through(fn ($row) => array_merge($this->invoiceRow($row), [
                'overpayment_amount' => (float) ($row->overpayment_amount ?? 0),
                'refunded_total' => (float) ($row->refunded_total ?? 0),
            ]));
    }

    /**
     * Refund listing.
     *
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function refundReport(array $filters = [], ?int $scopedUser = null): LengthAwarePaginator
    {
        $query = DB::table('refunds as rf')
            ->join('invoices as i', 'i.id', '=', 'rf.invoice_id')
            ->join('bookings as b', 'b.id', '=', 'i.booking_id')
            ->join('users as u', 'u.id', '=', 'b.user_id')
            ->join('project_locations as pl', 'pl.id', '=', 'b.project_location_id')
            ->select(
                'rf.id', 'rf.source', 'rf.amount', 'rf.status', 'rf.reason', 'rf.transfer_reference',
                'rf.approved_at', 'rf.processed_at', 'rf.completed_at', 'rf.created_at', 'i.invoice_number',
                'i.id as invoice_id', 'u.name as customer_name', 'u.id as customer_id', 'pl.project_name'
            );

        $this->applyPlain($query, $filters, [
            'from' => ['rf.created_at', 'date_min'],
            'to' => ['rf.created_at', 'date_max'],
            'status' => ['rf.status', 'eq'],
            'source' => ['rf.source', 'eq'],
            'customer_id' => ['b.user_id', 'eq'],
        ]);
        if ($scopedUser) {
            $query->where('b.user_id', $scopedUser);
        }

        return $this->paginate($query, $filters, ['id desc' => 'rf.id', 'amount' => 'rf.amount', 'status' => 'rf.status'])
            ->through(fn ($row) => [
                'id' => (int) $row->id,
                'source' => $this->enumValue($row->source),
                'amount' => (float) $row->amount,
                'status' => $this->enumValue($row->status),
                'reason' => $row->reason,
                'transfer_reference' => $row->transfer_reference,
                'approved_at' => $row->approved_at,
                'processed_at' => $row->processed_at,
                'completed_at' => $row->completed_at,
                'created_at' => $row->created_at,
                'invoice_id' => (int) $row->invoice_id,
                'invoice_number' => $row->invoice_number,
                'customer_name' => $row->customer_name,
                'customer_id' => (int) ($row->customer_id ?? 0),
                'project_name' => $row->project_name,
            ]);
    }

    private function approvedPayments(): Builder
    {
        return DB::table('payments')
            ->select('invoice_id', DB::raw('COALESCE(SUM(amount),0) as paid_total'))
            ->where('status', PaymentStatus::APPROVED->value)
            ->groupBy('invoice_id');
    }

    private function invoiceBase(Builder $paid): Builder
    {
        return DB::table('invoices as i')
            ->join('bookings as b', 'b.id', '=', 'i.booking_id')
            ->join('users as u', 'u.id', '=', 'b.user_id')
            ->join('project_locations as pl', 'pl.id', '=', 'b.project_location_id')
            ->leftJoinSub($paid, 'p', 'p.invoice_id', '=', 'i.id')
            ->select(
                'i.id', 'i.invoice_number', 'i.invoice_type', 'i.status', 'i.issued_at', 'i.due_at',
                'i.grand_total', 'i.overpayment_amount', 'b.booking_code', 'b.user_id as customer_id',
                'u.name as customer_name', 'pl.project_name', 'pl.city', 'i.created_at', 'p.paid_total'
            );
    }

    /**
     * @return array<string, mixed>
     */
    private function invoiceRow($row): array
    {
        $paid = (float) ($row->paid_total ?? 0);

        return [
            'id' => (int) $row->id,
            'invoice_number' => $row->invoice_number,
            'invoice_type' => $this->enumValue($row->invoice_type),
            'status' => $this->enumValue($row->status),
            'issued_at' => $row->issued_at,
            'due_at' => $row->due_at,
            'grand_total' => (float) $row->grand_total,
            'paid_total' => $paid,
            'balance' => (float) $row->grand_total - $paid,
            'booking_code' => $row->booking_code,
            'customer_name' => $row->customer_name,
            'customer_id' => (int) ($row->customer_id ?? 0),
            'project_name' => $row->project_name,
            'city' => $row->city,
            'created_at' => $row->created_at,
        ];
    }
}
