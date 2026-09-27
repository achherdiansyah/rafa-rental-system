<?php

namespace App\Actions\Invoice;

use App\Enums\InvoiceStatus;
use App\Exceptions\InvalidStateTransitionException;
use App\Models\Invoice;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Cancels an invoice (void). Financial history is never deleted — the row is
 * kept with status CANCELLED. Allowed from DRAFT, ISSUED, UNPAID and OVERDUE.
 */
class VoidInvoiceAction
{
    private const VOIDABLE = [
        InvoiceStatus::DRAFT,
        InvoiceStatus::ISSUED,
        InvoiceStatus::UNPAID,
        InvoiceStatus::OVERDUE,
    ];

    public function execute(User $actor, Invoice $invoice): Invoice
    {
        Gate::authorize('manage', $invoice);

        return DB::transaction(function () use ($actor, $invoice) {
            if (! in_array($invoice->status, self::VOIDABLE, true)) {
                throw new InvalidStateTransitionException(
                    'Invoice berstatus '.$invoice->status->value.' tidak dapat dibatalkan.'
                );
            }

            $old = $invoice->status->value;
            $invoice->update(['status' => InvoiceStatus::CANCELLED]);

            AuditLogger::log('INVOICE_CANCELLED', $invoice, [
                'old_status' => $old,
            ], [
                'new_status' => InvoiceStatus::CANCELLED->value,
                'cancelled_by' => $actor->id,
            ]);

            return $invoice->fresh()->load(['booking.projectLocation', 'details']);
        });
    }
}
