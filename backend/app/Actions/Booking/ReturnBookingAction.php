<?php

namespace App\Actions\Booking;

use App\Enums\BookingStatus;
use App\Exceptions\BusinessRuleException;
use App\Exceptions\InvalidStateTransitionException;
use App\Models\Booking;
use App\Models\User;
use App\Support\AuditLogger;
use App\Support\NotificationService;
use Illuminate\Support\Facades\DB;

class ReturnBookingAction
{
    public function __construct(
        private readonly NotificationService $notifications
    ) {}

    public function execute(User $supervisor, Booking $booking, string $notes): Booking
    {
        if (trim($notes) === '') {
            throw new BusinessRuleException('Catatan pengembalian wajib diisi.');
        }

        return DB::transaction(function () use ($supervisor, $booking, $notes) {
            if ($booking->status !== BookingStatus::PENDING_APPROVAL) {
                throw new InvalidStateTransitionException(
                    'Pengembalian perbaikan hanya untuk booking yang menunggu validasi Supervisor.'
                );
            }

            if ($booking->supervisor_decision === 'VALIDATED') {
                throw new InvalidStateTransitionException('Booking yang sudah divalidasi tidak dapat dikembalikan.');
            }

            $booking->update([
                'supervisor_user_id' => $supervisor->id,
                'supervisor_validated_at' => null,
                'supervisor_decision' => 'REVISION',
                'supervisor_notes' => $notes,
            ]);

            AuditLogger::log('BOOKING_SUPERVISOR_REVISION', $booking, [], [
                'supervisor_id' => $supervisor->id,
                'supervisor_notes' => $notes,
            ]);

            $booking->load('user');
            $this->notifications->send(
                $booking->user,
                'BOOKING_SUPERVISOR_REVISION',
                $booking,
                "Booking {$booking->booking_code} dikembalikan Supervisor untuk perbaikan: {$notes}"
            );

            return $booking->fresh()->load([
                'projectLocation',
                'details.model' => fn ($q) => $q->with(['type', 'prices', 'attachments']),
            ]);
        });
    }
}