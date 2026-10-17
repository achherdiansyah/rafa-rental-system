<?php

namespace App\Actions\Booking;

use App\Enums\BookingStatus;
use App\Exceptions\InvalidStateTransitionException;
use App\Models\Booking;
use App\Models\User;
use App\Support\AuditLogger;
use App\Support\NotificationService;
use Illuminate\Support\Facades\DB;

class ValidateBookingAction
{
    public function __construct(
        private readonly NotificationService $notifications
    ) {}

    public function execute(User $supervisor, Booking $booking, ?string $notes = null): Booking
    {
        return DB::transaction(function () use ($supervisor, $booking, $notes) {
            if ($booking->status !== BookingStatus::PENDING_APPROVAL) {
                throw new InvalidStateTransitionException(
                    'Validasi hanya untuk booking yang menunggu validasi Supervisor.'
                );
            }

            if ($booking->supervisor_decision === 'VALIDATED') {
                throw new InvalidStateTransitionException('Booking sudah divalidasi Supervisor.');
            }

            $booking->update([
                'supervisor_user_id' => $supervisor->id,
                'supervisor_validated_at' => now(),
                'supervisor_decision' => 'VALIDATED',
                'supervisor_notes' => $notes,
            ]);

            AuditLogger::log('BOOKING_SUPERVISOR_VALIDATED', $booking, [], [
                'supervisor_id' => $supervisor->id,
                'supervisor_notes' => $notes,
            ]);

            $booking->load('user');
            $this->notifications->send(
                $booking->user,
                'BOOKING_SUPERVISOR_VALIDATED',
                $booking,
                "Booking {$booking->booking_code} lolos validasi Supervisor dan diteruskan ke Admin."
            );

            return $booking->fresh()->load([
                'projectLocation',
                'details.model' => fn ($q) => $q->with(['type', 'prices', 'attachments']),
            ]);
        });
    }
}