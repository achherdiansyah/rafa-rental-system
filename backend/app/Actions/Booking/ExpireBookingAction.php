<?php

namespace App\Actions\Booking;

use App\Enums\AssignmentStatus;
use App\Enums\BookingStatus;
use App\Enums\EquipmentStatus;
use App\Exceptions\InvalidStateTransitionException;
use App\Models\Booking;
use App\Models\BookingUnitAssignment;
use App\Services\Payment\PaymentStatusProvider;
use App\Support\AuditLogger;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Expire a booking whose payment deadline has passed without payment.
 * Releases assigned slots (history preserved) and returns units to the pool.
 */
class ExpireBookingAction
{
    public function __construct(
        protected PaymentStatusProvider $paymentProvider
    ) {}

    /**
     * @throws InvalidStateTransitionException
     */
    public function execute(Booking $booking): Booking
    {
        return DB::transaction(function () use ($booking) {
            if (! in_array($booking->status->value, config('availability.payment_pending_statuses', []), true)) {
                throw new InvalidStateTransitionException(
                    'Transisi tidak valid: hanya booking APPROVED / PAYMENT_PENDING yang dapat expire.'
                );
            }

            // Never expire a booking whose payment has been satisfied (boundary)
            if ($this->paymentProvider->isSatisfied($booking)) {
                throw new InvalidStateTransitionException('Pembayaran booking sudah terpenuhi, tidak dapat expire.');
            }

            $deadline = $this->paymentProvider->paymentDeadline($booking);
            if (! $deadline || ! $deadline->lessThan(now())) {
                throw new InvalidStateTransitionException('Tenggat pembayaran belum terlewati.');
            }

            $oldStatus = $booking->status->value;

            // Release assigned slots preserving history (is_current=false)
            /** @var Collection<int, BookingUnitAssignment> $currentAssignments */
            $currentAssignments = $booking->unitAssignments()
                ->where('is_current', true)
                ->with('unit')
                ->get();

            foreach ($currentAssignments as $assignment) {
                $assignment->update([
                    'is_current' => false,
                    'status' => AssignmentStatus::CANCELLED,
                    'replaced_reason' => 'Booking expired otomatis: slot dilepas',
                ]);

                // Release unit back to the public pool
                if ($assignment->unit && $assignment->unit->status === EquipmentStatus::ASSIGNED) {
                    $assignment->unit->update(['status' => EquipmentStatus::AVAILABLE]);
                }
            }

            $booking->update([
                'status' => BookingStatus::EXPIRED,
            ]);

            AuditLogger::log('BOOKING_AUTO_EXPIRED', $booking, [
                'old_status' => $oldStatus,
                'released_assignments' => $currentAssignments->count(),
            ], [
                'new_status' => BookingStatus::EXPIRED->value,
            ]);

            return $booking->fresh();
        });
    }
}
