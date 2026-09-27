<?php

namespace App\Services\Refund;

use App\Enums\PaymentStatus;
use App\Enums\RefundSource;
use App\Enums\RefundStatus;
use App\Models\Booking;
use App\Models\Invoice;
use App\Models\Refund;

/**
 * Phase 11 refund registration: sources are booking cancellation after
 * payment and invoice overpayment. Refunds are created PENDING and settled
 * manually by staff via bank transfer (no gateway).
 */
class RefundRegistrationService implements RefundBoundary
{
    public function registerPendingRefund(Booking $booking, string $reason): void
    {
        foreach ($booking->invoices()->get() as $invoice) {
            $paid = (float) $invoice->payments()
                ->where('status', PaymentStatus::APPROVED)
                ->sum('amount');

            if ($paid <= 0) {
                continue;
            }

            if ($this->existingFor($invoice, RefundSource::CANCELLATION)) {
                continue;
            }

            Refund::create([
                'invoice_id' => $invoice->id,
                'source' => RefundSource::CANCELLATION,
                'amount' => $paid,
                'reason' => $reason,
                'status' => RefundStatus::PENDING,
            ]);
        }
    }

    public function noteOverpayment(Invoice $invoice, float $excess): void
    {
        if ($excess <= 0) {
            return;
        }

        if ($this->existingFor($invoice, RefundSource::OVERPAYMENT)) {
            return;
        }

        Refund::create([
            'invoice_id' => $invoice->id,
            'source' => RefundSource::OVERPAYMENT,
            'amount' => $excess,
            'reason' => 'Kelebihan pembayaran invoice #'.$invoice->invoice_number,
            'status' => RefundStatus::PENDING,
        ]);
    }

    private function existingFor(Invoice $invoice, RefundSource $source): bool
    {
        return Refund::where('invoice_id', $invoice->id)
            ->where('source', $source->value)
            ->where('status', '!=', RefundStatus::FAILED->value)
            ->exists();
    }
}
