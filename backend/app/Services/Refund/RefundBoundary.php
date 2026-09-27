<?php

namespace App\Services\Refund;

use App\Models\Booking;

/**
 * Integration boundary for refund execution.
 *
 * Refund engine lands in Phase 10. Cancellation that occurred after payment
 * registers a pending refund through this seam. Default implementation is a
 * no-op (execution deferred) so no fake refund records are created.
 */
interface RefundBoundary
{
    /**
     * Register a pending refund for a cancelled, already-paid booking.
     */
    public function registerPendingRefund(Booking $booking, string $reason): void;
}
