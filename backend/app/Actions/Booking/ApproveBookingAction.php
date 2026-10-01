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

            $invoice = $this->createMobDemobInvoice($booking, $approvedAt, $deadline);

            AuditLogger::log('BOOKING_APPROVED', $booking, [
                'old_status' => $oldStatus,
                'approved_by' => $admin->id,
            ], [
                'new_status' => BookingStatus::APPROVED->value,
                'payment_deadline_at' => $deadline->toIso8601String(),
                'invoice_id' => $invoice?->id,
                'invoice_number' => $invoice?->invoice_number,
            ]);

            $booking->load('user');

            $msg = "Booking {$booking->booking_code} disetujui.";
            if ($invoice) {
                $msg .= " Tagihan MOB/DEMOB telah diterbitkan.";
            }

            $this->notifications->send(
                $booking->user,
                'BOOKING_APPROVED',
                $booking,
                $msg
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

    private function createMobDemobInvoice(Booking $booking, $issuedAt, $deadline): ?Invoice
    {
        $booking->loadMissing('details.model');

        $subtotal = 0.00;
        $lines = [];

        foreach ($booking->details as $d) {
            $modelName = $d->model?->model_name ?? 'Unit Alat Berat';
            $mobUnit = (float) $d->mob_cost_snapshot;
            $demobUnit = (float) $d->demob_cost_snapshot;

            if ($mobUnit > 0) {
                $mobTotal = $mobUnit * $d->quantity;
                $subtotal += $mobTotal;
                $lines[] = [
                    'description' => sprintf('Mobilisasi %s (%s unit)', $modelName, $d->quantity),
                    'unit_price' => $mobUnit,
                    'quantity' => $d->quantity,
                    'subtotal' => $mobTotal,
                ];
            }

            if ($demobUnit > 0) {
                $demobTotal = $demobUnit * $d->quantity;
                $subtotal += $demobTotal;
                $lines[] = [
                    'description' => sprintf('Demobilisasi %s (%s unit)', $modelName, $d->quantity),
                    'unit_price' => $demobUnit,
                    'quantity' => $d->quantity,
                    'subtotal' => $demobTotal,
                ];
            }
        }

        if ($subtotal <= 0 || empty($lines)) {
            return null;
        }

        $invoice = Invoice::create([
            'invoice_number' => $this->numberGenerator->generate(),
            'booking_id' => $booking->id,
            'invoice_type' => InvoiceType::MOB_DEMOB,
            'status' => InvoiceStatus::ISSUED,
            'issued_at' => $issuedAt,
            'due_at' => $deadline,
            'subtotal' => $subtotal,
            'tax_total' => 0.00,
            'grand_total' => $subtotal,
            'paid_amount' => 0.00,
            'overpayment_amount' => 0.00,
        ]);

        foreach ($lines as $line) {
            $invoice->details()->create($line);
        }

        return $invoice;
    }
}
