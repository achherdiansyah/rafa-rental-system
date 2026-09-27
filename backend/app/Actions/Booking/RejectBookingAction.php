<?php

namespace App\Actions\Booking;

use App\Enums\BookingStatus;
use App\Exceptions\InvalidStateTransitionException;
use App\Models\Booking;
use App\Models\User;
use App\Support\AuditLogger;
use App\Support\NotificationService;
use Illuminate\Support\Facades\DB;

class RejectBookingAction
{
    public function __construct(
        private readonly NotificationService $notifications
    ) {}

    /**
     * Transition PENDING_APPROVAL -> REJECTED with mandatory reason (T-B03).
     *
     * @throws InvalidStateTransitionException
     */
    public function execute(User $admin, Booking $booking, string $reason): Booking
    {
        return DB::transaction(function () use ($admin, $booking, $reason) {
            if ($booking->status !== BookingStatus::PENDING_APPROVAL) {
                throw new InvalidStateTransitionException(
                    'Transisi tidak valid: hanya booking berstatus PENDING_APPROVAL yang dapat ditolak.'
                );
            }

            $oldStatus = $booking->status->value;

            $booking->update([
                'status' => BookingStatus::REJECTED,
                'rejection_reason' => $reason,
            ]);

            AuditLogger::log('BOOKING_REJECTED', $booking, [
                'old_status' => $oldStatus,
                'rejected_by' => $admin->id,
            ], [
                'new_status' => BookingStatus::REJECTED->value,
                'rejection_reason' => $reason,
            ]);

            $booking->load('user');
            $this->notifications->send(
                $booking->user,
                'BOOKING_REJECTED',
                $booking,
                "Booking {$booking->booking_code} ditolak: {$reason}."
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
