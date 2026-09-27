<?php

namespace App\Actions\Invoice;

use App\DTOs\Billing\RentalBill;
use App\Enums\BookingStatus;
use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Enums\RentalStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Booking;
use App\Models\Invoice;
use App\Models\Rental;
use App\Models\User;
use App\Services\Billing\InvoiceNumberGenerator;
use App\Services\Billing\RentalBillingService;
use App\Support\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Creates an invoice from a completed rental through the billing engine.
 *
 * Phase 1 prerequisites honoured: booking is confirmed, physical units have
 * been assigned and the rental has completed its full lifecycle, and each
 * equipment line carries its pricing snapshot. NO payment/refund/outstanding.
 */
class CreateInvoiceAction
{
    public function __construct(
        private readonly RentalBillingService $billing,
        private readonly InvoiceNumberGenerator $numberGenerator,
    ) {}

    public function execute(User $actor, Booking $booking, InvoiceType $type): Invoice
    {
        Gate::authorize('manage', Invoice::class);

        return DB::transaction(function () use ($actor, $booking, $type) {
            if ($booking->status !== BookingStatus::CONFIRMED) {
                throw new BusinessRuleException(
                    'Invoice hanya dapat dibuat untuk booking berstatus CONFIRMED.'
                );
            }

            /** @var Rental|null $rental */
            $rental = Rental::where('booking_id', $booking->id)->first();
            if (! $rental || $rental->status !== RentalStatus::COMPLETED) {
                throw new BusinessRuleException(
                    'Prerequisite unit belum terpenuhi: rental belum berstatus COMPLETED.'
                );
            }

            $rental->loadMissing('details.assignment.detail', 'details.assignment.unit');
            if ($rental->details->isEmpty()) {
                throw new BusinessRuleException(
                    'Prerequisite unit belum terpenuhi: tidak ada unit fisik yang ditugaskan.'
                );
            }

            // Pricing prerequisite: setiap line wajib punya snapshot tarif.
            foreach ($rental->details as $rd) {
                $bookingDetail = $rd->assignment?->detail;
                if (! $bookingDetail || $bookingDetail->rental_rate_snapshot === null) {
                    throw new BusinessRuleException(
                        'Prerequisite pricing belum terpenuhi: snapshot tarif per line tidak lengkap.'
                    );
                }
            }

            $bill = $this->billing->generate($rental);

            $invoiceNumber = $this->numberGenerator->generate();

            $invoice = Invoice::create([
                'invoice_number' => $invoiceNumber,
                'booking_id' => $booking->id,
                'invoice_type' => $type,
                'status' => InvoiceStatus::DRAFT,
                'subtotal' => 0.00,
                'tax_total' => 0.00,
                'grand_total' => 0.00,
                'paid_amount' => 0.00,
                'overpayment_amount' => 0.00,
            ]);

            $subtotal = $this->persistDetails($invoice, $type, $bill);
            $invoice->update([
                'subtotal' => $subtotal,
                'grand_total' => $subtotal,
            ]);

            AuditLogger::log('INVOICE_CREATED', $invoice, [], [
                'booking_id' => $booking->id,
                'invoice_type' => $type->value,
                'grand_total' => $subtotal,
                'created_by' => $actor->id,
            ]);

            return $invoice->fresh()->load(['booking.projectLocation', 'details']);
        });
    }

    private function persistDetails(Invoice $invoice, InvoiceType $type, RentalBill $bill): float
    {
        $subtotal = 0.00;

        foreach ($bill->lines as $line) {
            if ($type === InvoiceType::DAILY_WORK && $line->workHours > 0) {
                $subtotal += $line->workAmount;
                $invoice->details()->create([
                    'description' => sprintf(
                        'Sewa Harian %s — %s jam @ %s (actual hours)',
                        $line->unitSerial,
                        number_format($line->workHours, 2),
                        number_format($line->hourlyRate, 0, ',', '.')
                    ),
                    'unit_price' => $line->hourlyRate,
                    'quantity' => $line->workHours,
                    'subtotal' => $line->workAmount,
                ]);
            }

            if ($type === InvoiceType::MOB_DEMOB) {
                $subtotal += $line->mobCost + $line->demobCost;
                $invoice->details()->create([
                    'description' => 'Mobilisasi '.$line->unitSerial.' (per unit fisik)',
                    'unit_price' => $line->mobCost,
                    'quantity' => 1,
                    'subtotal' => $line->mobCost,
                ]);
                $invoice->details()->create([
                    'description' => 'Demobilisasi '.$line->unitSerial.' (per unit fisik)',
                    'unit_price' => $line->demobCost,
                    'quantity' => 1,
                    'subtotal' => $line->demobCost,
                ]);
            }
        }

        return round($subtotal, 2);
    }
}
