<?php

namespace App\Actions\Booking;

use App\Enums\BookingStatus;
use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Exceptions\InvalidStateTransitionException;
use App\Models\Booking;
use App\Models\Invoice;
use App\Models\User;
use App\Services\Billing\InvoiceNumberGenerator;
use App\Support\AuditLogger;
use App\Support\NotificationService;
use Illuminate\Support\Facades\DB;

class ApproveBookingAction
{
    public function __construct(
        private readonly NotificationService $notifications,
        private readonly InvoiceNumberGenerator $numberGenerator
    ) {}

    /**
     * Transition PENDING_APPROVAL -> APPROVED and automatically generate
     * the initial rental prepayment invoice for the customer.
     *
     * @throws InvalidStateTransitionException
     */
    public function execute(User $admin, Booking $booking): Booking
    {
        return DB::transaction(function () use ($admin, $booking) {
            if ($booking->status !== BookingStatus::PENDING_APPROVAL) {
                throw new InvalidStateTransitionException(
                    'Transisi tidak valid: hanya booking berstatus PENDING_APPROVAL yang dapat disetujui.'
                );
            }

            $oldStatus = $booking->status->value;

            $approvedAt = now();
            $deadline = $approvedAt->copy()->addHours((int) config('availability.payment_grace_hours', 24));

            $booking->update([
                'status' => BookingStatus::APPROVED,
                'approved_at' => $approvedAt,
                'payment_deadline_at' => $deadline,
            ]);

            // Auto-create initial prepayment invoice so customer can pay on Tagihan & Bayar
            $invoiceNumber = $this->numberGenerator->generate();

            $invoice = Invoice::create([
                'invoice_number' => $invoiceNumber,
                'booking_id' => $booking->id,
                'invoice_type' => InvoiceType::RENTAL_PREPAYMENT,
                'status' => InvoiceStatus::ISSUED,
                'issued_at' => $approvedAt,
                'due_at' => $deadline,
                'subtotal' => $booking->total_amount,
                'tax_total' => 0.00,
                'grand_total' => $booking->total_amount,
                'paid_amount' => 0.00,
                'overpayment_amount' => 0.00,
            ]);

            $booking->loadMissing('details.model');
            foreach ($booking->details as $d) {
                $modelName = $d->model?->model_name ?? 'Unit Alat Berat';
                $rateFmt = number_format((float) $d->rental_rate_snapshot, 0, ',', '.');
                $invoice->details()->create([
                    'description' => sprintf('Sewa %s (%s unit) @ Rp %s', $modelName, $d->quantity, $rateFmt),
                    'unit_price' => $d->subtotal,
                    'quantity' => 1,
                    'subtotal' => $d->subtotal,
                ]);

                $mobTotal = (float) $d->mob_cost_snapshot * $d->quantity;
                if ($mobTotal > 0) {
                    $invoice->details()->create([
                        'description' => sprintf('Mobilisasi %s (%s unit)', $modelName, $d->quantity),
                        'unit_price' => $d->mob_cost_snapshot,
                        'quantity' => $d->quantity,
                        'subtotal' => $mobTotal,
                    ]);
                }

                $demobTotal = (float) $d->demob_cost_snapshot * $d->quantity;
                if ($demobTotal > 0) {
                    $invoice->details()->create([
                        'description' => sprintf('Demobilisasi %s (%s unit)', $modelName, $d->quantity),
                        'unit_price' => $d->demob_cost_snapshot,
                        'quantity' => $d->quantity,
                        'subtotal' => $demobTotal,
                    ]);
                }
            }

            AuditLogger::log('BOOKING_APPROVED', $booking, [
                'old_status' => $oldStatus,
                'approved_by' => $admin->id,
            ], [
                'new_status' => BookingStatus::APPROVED->value,
                'payment_deadline_at' => $deadline->toIso8601String(),
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
            ]);

            $booking->load('user');
            $this->notifications->send(
                $booking->user,
                'BOOKING_APPROVED',
                $booking,
                "Booking {$booking->booking_code} disetujui. Tagihan telah diterbitkan."
            );

            $booking->load([
                'projectLocation',
                'details.model' => function ($q) {
                    $q->with(['type', 'prices', 'attachments']);
                },
            ]);

            return $booking;
        });
    }
}
