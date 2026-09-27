<?php

namespace App\Services\Payment;

use App\Models\Booking;
use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Default deployment of the payment-status boundary used until the Phase 10
 * invoice/payment engine lands. It reads the booking-level deadline columns
 * (`payment_deadline_at`, `payment_met_at`) which are written at approval time.
 *
 * Phase 10 will replace this provider with one backed by the real invoice
 * records without changing the expiry scheduler.
 */
class DeferredPaymentStatusProvider implements PaymentStatusProvider
{
    public function isSatisfied(Booking $booking): bool
    {
        return $booking->payment_met_at !== null;
    }

    public function paymentDeadline(Booking $booking): ?CarbonInterface
    {
        if ($booking->payment_deadline_at) {
            return Carbon::parse($booking->payment_deadline_at);
        }

        if ($booking->approved_at) {
            return Carbon::parse($booking->approved_at)
                ->copy()
                ->addHours((int) config('availability.payment_grace_hours', 24));
        }

        return null;
    }
}
