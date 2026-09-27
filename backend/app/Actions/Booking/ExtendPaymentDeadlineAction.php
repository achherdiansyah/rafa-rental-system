<?php

namespace App\Actions\Booking;

use App\Exceptions\InvalidStateTransitionException;
use App\Models\Booking;
use App\Models\User;
use App\Services\Payment\PaymentStatusProvider;
use App\Support\AuditLogger;
use Illuminate\Support\Facades\DB;

class ExtendPaymentDeadlineAction
{
    public function __construct(
        protected PaymentStatusProvider $paymentProvider
    ) {}

    /**
     * Manually extend the payment deadline (admin policy-gated). Extension NEVER
     * resets on payment rejection; only this explicit action changes the deadline.
     *
     * @throws InvalidStateTransitionException
     */
    public function execute(User $admin, Booking $booking, int $additionalHours, string $reason): Booking
    {
        return DB::transaction(function () use ($admin, $booking, $additionalHours, $reason) {
            if (! in_array($booking->status->value, config('availability.payment_pending_statuses', []), true)) {
                throw new InvalidStateTransitionException(
                    'Perpanjangan hanya berlaku untuk booking berstatus APPROVED / PAYMENT_PENDING.'
                );
            }

            if ($this->paymentProvider->isSatisfied($booking)) {
                throw new InvalidStateTransitionException('Pembayaran sudah terpenuhi, tidak perlu perpanjangan.');
            }

            $current = $this->paymentProvider->paymentDeadline($booking) ?? now();
            $newDeadline = $current->copy()->addHours(max(1, $additionalHours));

            $oldDeadline = $booking->payment_deadline_at;

            $booking->update(['payment_deadline_at' => $newDeadline]);

            AuditLogger::log('PAYMENT_DEADLINE_EXTENDED', $booking, [
                'old_deadline' => $oldDeadline?->toIso8601String(),
                'extended_by' => $admin->id,
            ], [
                'additional_hours' => $additionalHours,
                'new_deadline' => $newDeadline->toIso8601String(),
                'reason' => $reason,
            ]);

            return $booking->fresh();
        });
    }
}
