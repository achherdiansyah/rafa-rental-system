<?php

namespace App\Actions\Booking;

use App\Enums\BookingStatus;
use App\Exceptions\InvalidStateTransitionException;
use App\Models\Booking;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Support\Facades\DB;

class ApproveBookingAction
{
    /**
     * Transition PENDING_APPROVAL -> APPROVED. Invoice/payment generation is
     * intentionally deferred to a later phase (BR-021).
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

            $booking->update([
                'status' => BookingStatus::APPROVED,
            ]);

            AuditLogger::log('BOOKING_APPROVED', $booking, [
                'old_status' => $oldStatus,
                'approved_by' => $admin->id,
            ], [
                'new_status' => BookingStatus::APPROVED->value,
            ]);

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
