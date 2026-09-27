<?php

namespace App\Actions\Payment;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Exceptions\BusinessRuleException;
use App\Exceptions\InvalidStateTransitionException;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use App\Services\Refund\RefundBoundary;
use App\Support\AuditLogger;
use App\Support\NotificationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Admin verification of SUBMITTED payments.
 *
 * - APPROVED: verifies the submitted amount against the invoice balance. The
 *   submitted nominal is NEVER mutated; the invoice books what was transferred.
 *   Exact payment -> PAID; partial -> PARTIALLY_PAID; overpayment -> OVERPAID
 *   (excess parked in overpayment_amount; refund stays manual via RefundBoundary).
 * - REJECTED: requires a reason; the payment row is kept for history.
 *
 * The invoice deadline (due_at) is never recomputed by verification.
 */
class VerifyPaymentAction
{
    public function __construct(
        private readonly RefundBoundary $refundBoundary,
        private readonly NotificationService $notifications
    ) {}

    public function approve(User $admin, Payment $payment): Payment
    {
        Gate::authorize('manage', $payment);

        return DB::transaction(function () use ($admin, $payment) {
            // Pessimistic lock guards against concurrent verification (race).
            /** @var Payment $fresh */
            $fresh = Payment::whereKey($payment->id)->lockForUpdate()->first() ?? $payment;

            if ($fresh->status !== PaymentStatus::SUBMITTED) {
                throw new InvalidStateTransitionException(
                    'Hanya payment SUBMITTED yang dapat diverifikasi.'
                );
            }

            $invoice = $fresh->invoice;

            if ($invoice->status === InvoiceStatus::PAID || $invoice->status === InvoiceStatus::OVERPAID) {
                throw new BusinessRuleException(
                    'Invoice sudah lunas/tertutup; tidak dapat menerima verifikasi tambahan.'
                );
            }

            // Settlement: balance = sum approved payments (single source of
            // truth) => no double counting even across multiple approvals.
            $submitted = (float) $fresh->amount;
            $approvedTotal = (float) $invoice->payments()
                ->where('status', PaymentStatus::APPROVED)
                ->sum('amount');
            $grandTotal = (float) $invoice->grand_total;

            $totalBooked = $approvedTotal + $submitted;
            $excess = max(0.0, $totalBooked - $grandTotal);
            $newPaid = min($grandTotal, $totalBooked);

            $fresh->update([
                'status' => PaymentStatus::APPROVED,
                'verified_by' => $admin->id,
            ]);

            $nextStatus = match (true) {
                $excess > 0 => InvoiceStatus::OVERPAID,
                $newPaid >= $grandTotal => InvoiceStatus::PAID,
                default => InvoiceStatus::PARTIALLY_PAID,
            };

            $invoice->update([
                'paid_amount' => $newPaid,
                'overpayment_amount' => (float) $invoice->overpayment_amount + $excess,
                'status' => $nextStatus,
            ]);

            if ($excess > 0) {
                // Seam for Phase 11 refund engine (no-op until then).
                $this->refundBoundary->noteOverpayment($invoice, $excess);
            }

            AuditLogger::log('PAYMENT_APPROVED', $fresh, [
                'old_status' => PaymentStatus::SUBMITTED->value,
            ], [
                'new_status' => PaymentStatus::APPROVED->value,
                'verified_by' => $admin->id,
                'submitted_amount' => $submitted,
                'invoice_status' => $nextStatus->value,
            ]);

            $invoice->load('booking.user');
            $this->notifications->send(
                $invoice->booking?->user,
                'PAYMENT_APPROVED',
                $fresh,
                'Pembayaran '.number_format($submitted, 0, ',', '.').' terverifikasi untuk invoice #'.$invoice->invoice_number.'.'
            );

            return $fresh->fresh()->load([
                'attachments',
                'invoice.booking.projectLocation',
            ]);
        });
    }

    public function reject(User $admin, Payment $payment, string $reason): Payment
    {
        Gate::authorize('manage', $payment);

        return DB::transaction(function () use ($admin, $payment, $reason) {
            // Pessimistic lock guards against concurrent verification (race).
            /** @var Payment $fresh */
            $fresh = Payment::whereKey($payment->id)->lockForUpdate()->first() ?? $payment;

            if ($fresh->status !== PaymentStatus::SUBMITTED) {
                throw new InvalidStateTransitionException(
                    'Hanya payment SUBMITTED yang dapat ditolak.'
                );
            }

            if (mb_strlen(trim($reason)) < 5) {
                throw new BusinessRuleException('Alasan penolakan wajib diisi minimal 5 karakter.');
            }

            $fresh->update([
                'status' => PaymentStatus::REJECTED,
                'rejection_reason' => trim($reason),
                'verified_by' => $admin->id,
            ]);

            AuditLogger::log('PAYMENT_REJECTED', $fresh, [
                'old_status' => PaymentStatus::SUBMITTED->value,
            ], [
                'new_status' => PaymentStatus::REJECTED->value,
                'rejection_reason' => trim($reason),
                'verified_by' => $admin->id,
            ]);

            $fresh->load(['invoice.booking.user']);
            $this->notifications->send(
                $fresh->invoice?->booking?->user,
                'PAYMENT_REJECTED',
                $fresh,
                'Pembayaran untuk invoice #'.$fresh->invoice?->invoice_number.' ditolak: '.trim($reason).'.'
            );

            return $fresh->fresh()->load([
                'attachments',
                'invoice.booking.projectLocation',
            ]);
        });
    }
}
