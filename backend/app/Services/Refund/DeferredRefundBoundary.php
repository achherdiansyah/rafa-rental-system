<?php

namespace App\Services\Refund;

use App\Models\Booking;

/**
 * Default no-op refund boundary until Phase 10 refund engine lands.
 */
class DeferredRefundBoundary implements RefundBoundary
{
    public function registerPendingRefund(Booking $booking, string $reason): void
    {
        // Refund execution intentionally deferred to Phase 10.
    }
}
