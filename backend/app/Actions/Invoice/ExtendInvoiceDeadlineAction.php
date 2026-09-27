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
 * Manual payment-deadline extension by Admin (when policy permits).
 *
 * The ONLY sanctioned mutation of due_at; issued_at stays untouched as the
 * original source. Every extension is audited. The 24h default remains locked
 * to issued_at until an explicit extension re-anchors it.
 *
 * ponytail: no max-extension policy guard yet — the requirement is "bila policy
 * mengizinkan"; add a cap (e.g. global max) when management sets one.
 */
class ExtendInvoiceDeadlineAction
{
    public function execute(User $admin, Invoice $invoice, int $hours): Invoice
    {
        Gate::authorize('manage', $invoice);

        return DB::transaction(function () use ($admin, $invoice, $hours) {
            if (! in_array($invoice->status, [
                InvoiceStatus::ISSUED,
                InvoiceStatus::UNPAID,
                InvoiceStatus::OVERDUE,
            ], true)) {
                throw new InvalidStateTransitionException(
                    'Perpanjangan deadline hanya untuk invoice ISSUED/UNPAID/OVERDUE.'
                );
            }

            $oldDueAt = $invoice->due_at;
            $newDueAt = $hours === 0
                ? now()->copy()->addHours(24)
                : ($invoice->due_at ? $invoice->due_at->copy() : now())->copy()->addHours($hours);

            $invoice->update(['due_at' => $newDueAt]);

            AuditLogger::log('INVOICE_DEADLINE_EXTENDED', $invoice, [
                'old_due_at' => $oldDueAt?->toIso8601String(),
            ], [
                'new_due_at' => $newDueAt->toIso8601String(),
                'extended_hours' => $hours,
                'extended_by' => $admin->id,
            ]);

            return $invoice->fresh()->load(['booking.projectLocation', 'details']);
        });
    }
}
