<?php

namespace App\Services\Finance;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Models\Invoice;
use Illuminate\Database\Eloquent\Collection;

/**
 * Customer outstanding / credit control.
 *
 * - Outstanding is computed per customer from OPEN invoices only
 *   (ISSUED/UNPAID/PARTIALLY_PAID/OVERDUE — "belum lunas"). PAID, OVERPAID,
 *   DRAFT and CANCELLED are excluded. It is fully decoupled from booking status.
 * - The paid component always reflects APPROVED payments only.
 * - No credit-threshold or overdue-day policy is invented here (pending
 *   business decision); eligibility is a factual flag: open balance == 0.
 * - Queries are batched (no N+1): invoices + approved payments eager-loaded.
 *
 * ponytail: add credit limits / age-threshold tiers when management finalises
 * the policy (PBD); today this service only reports reality.
 */
class CustomerOutstandingService
{
    public const OPEN_STATUSES = [
        InvoiceStatus::ISSUED,
        InvoiceStatus::UNPAID,
        InvoiceStatus::PARTIALLY_PAID,
        InvoiceStatus::OVERDUE,
    ];

    /**
     * Outstanding detail for a single customer.
     *
     * @return array<string, mixed>
     */
    public function forCustomer(int $userId): array
    {
        $invoices = $this->openInvoices($userId);

        return $this->summarize($userId, $invoices);
    }

    /**
     * Aggregate outstanding across all customers (admin view).
     *
     * @return array<int, array<string, mixed>>
     */
    public function allCustomers(): array
    {
        return Invoice::whereIn('status', array_map(fn ($s) => $s->value, self::OPEN_STATUSES))
            ->get(['booking_id'])
            ->map(fn ($i) => (int) $i->booking?->user_id)
            ->unique()
            ->values()
            ->map(fn (int $userId) => $this->forCustomer($userId))
            ->values()
            ->all();
    }

    public function eligibility(int $userId): bool
    {
        return $this->forCustomer($userId)['total_outstanding'] === 0.0;
    }

    /**
     * @return Collection<int, Invoice>
     */
    private function openInvoices(int $userId): Collection
    {
        return Invoice::with([
            'booking.user:id,name',
            'booking.projectLocation:id,project_name,city',
            'payments' => fn ($q) => $q->where('status', PaymentStatus::APPROVED)->select('invoice_id', 'amount', 'status'),
        ])
            ->whereIn('status', array_map(fn ($s) => $s->value, self::OPEN_STATUSES))
            ->whereHas('booking', fn ($q) => $q->where('user_id', $userId))
            ->get();
    }

    /**
     * @param  Collection<int, Invoice>  $invoices
     * @return array<string, mixed>
     */
    private function summarize(int $userId, $invoices): array
    {
        $totalOutstanding = 0.0;
        $openCount = 0;
        $overdueCount = 0;
        $lines = [];

        foreach ($invoices as $invoice) {
            $paid = (float) $invoice->payments->sum('amount');
            $balance = max(0.0, (float) $invoice->grand_total - $paid);

            $totalOutstanding += $balance;
            $openCount++;
            if ($invoice->status === InvoiceStatus::OVERDUE) {
                $overdueCount++;
            }

            $lines[] = [
                'id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'status' => $invoice->status->value,
                'grand_total' => (float) $invoice->grand_total,
                'paid_amount' => $paid,
                'balance_amount' => $balance,
            ];
        }

        $booking = $invoices->first()?->booking;

        return [
            'user_id' => $userId,
            'customer_name' => $booking?->user?->name,
            'total_outstanding' => $totalOutstanding,
            'open_invoice_count' => $openCount,
            'overdue_invoice_count' => $overdueCount,
            'eligible' => $totalOutstanding === 0.0,
            'invoices' => $lines,
        ];
    }
}
