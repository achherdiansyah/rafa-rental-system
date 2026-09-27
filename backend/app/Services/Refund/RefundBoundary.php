<?php

namespace App\Services\Refund;

use App\Models\Booking;
use App\Models\Invoice;

/**
 * Integration boundary for refund execution.
 *
 * Refund engine lands in Phase 11. Cancellation that occurred after payment
 * registers a pending refund through this seam; overpayment parks the excess
 * through the same seam. Default implementation is a no-op (execution
 * deferred) so no fake refund records are created.
 */
interface RefundBoundary
{
    /**
     * Register a pending refund for a cancelled, already-paid booking.
     */
    public function registerPendingRefund(Booking $booking, string $reason): void;

    /**
     * Register an overpayment excess that awaits manual settlement in Phase 11.
     */
    public function noteOverpayment(Invoice $invoice, float $excess): void;
}
