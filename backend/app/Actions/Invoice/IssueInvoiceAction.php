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
 * Issues a DRAFT invoice: locks issued_at and computes due_at = issued_at + 24h.
 * The 24h deadline is a timer invariant — never recomputed afterwards.
 */
class IssueInvoiceAction
{
    public function execute(User $actor, Invoice $invoice): Invoice
    {
        Gate::authorize('manage', $invoice);

        return DB::transaction(function () use ($actor, $invoice) {
            if ($invoice->status !== InvoiceStatus::DRAFT) {
                throw new InvalidStateTransitionException(
                    'Penerbitan invoice hanya berlaku untuk invoice berstatus DRAFT.'
                );
            }

            $issuedAt = now();
            $invoice->update([
                'status' => InvoiceStatus::ISSUED,
                'issued_at' => $issuedAt,
                'due_at' => $issuedAt->copy()->addHours(24),
            ]);

            AuditLogger::log('INVOICE_ISSUED', $invoice, [
                'old_status' => InvoiceStatus::DRAFT->value,
            ], [
                'new_status' => InvoiceStatus::ISSUED->value,
                'issued_at' => $issuedAt->toIso8601String(),
                'due_at' => $invoice->due_at->toIso8601String(),
                'issued_by' => $actor->id,
            ]);

            return $invoice->fresh()->load(['booking.projectLocation', 'details']);
        });
    }
}
