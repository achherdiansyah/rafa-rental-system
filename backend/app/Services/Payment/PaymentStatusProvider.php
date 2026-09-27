<?php

namespace App\Services\Payment;

use App\Models\Booking;
use Carbon\CarbonInterface;

/**
 * Integration boundary for payment status.
 *
 * Invoice/payment engine is implemented in Phase 10. This interface defines the
 * seam the expiry scheduler (Phase 8G) depends on, so no fake invoice/payment
 * tables are introduced. Phase 10 will provide a concrete implementation backed
 * by the real `invoices`/`payments` records.
 */
interface PaymentStatusProvider
{
    /**
     * Whether the booking's payment obligations have been satisfied.
     */
    public function isSatisfied(Booking $booking): bool;

    /**
     * Absolute payment deadline for the booking.
     */
    public function paymentDeadline(Booking $booking): ?CarbonInterface;
}
