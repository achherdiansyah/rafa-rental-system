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
 * Marks an issued invoice as UNPAID (payment window start). Moves through
 * UNPAID -> PARTIALLY_PAID -> PAID in the payment subphase (10D+).
 */
class MarkUnpaidInvoiceAction
{
    public function execute(User $actor, Invoice $invoice): Invoice
    {
        Gate::authorize('manage', $invoice);

        return DB::transaction(function () use ($actor, $invoice) {
            if ($invoice->status !== InvoiceStatus::ISSUED) {
                throw new InvalidStateTransitionException(
                    'Transisi ke UNPAID hanya berlaku untuk invoice berstatus ISSUED.'
                );
            }

            $invoice->update(['status' => InvoiceStatus::UNPAID]);

            AuditLogger::log('INVOICE_MARKED_UNPAID', $invoice, [
                'old_status' => InvoiceStatus::ISSUED->value,
            ], [
                'new_status' => InvoiceStatus::UNPAID->value,
                'updated_by' => $actor->id,
            ]);

            return $invoice->fresh()->load(['booking.projectLocation', 'details']);
        });
    }
}
