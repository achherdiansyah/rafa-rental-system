<?php

namespace App\Services\Refund;

use App\Models\Booking;
use App\Models\Invoice;

/**
 * Default no-op refund boundary until the Phase 11 refund engine lands.
 */
class DeferredRefundBoundary implements RefundBoundary
{
    public function registerPendingRefund(Booking $booking, string $reason): void
    {
        // Refund execution intentionally deferred to Phase 11.
    }

    public function noteOverpayment(Invoice $invoice, float $excess): void
    {
        // Excess already parked in invoice.overpayment_amount; manual settlement
        // (Phase 11 refund engine) is intentionally deferred.
    }
}
