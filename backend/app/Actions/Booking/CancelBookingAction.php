<?php

namespace App\Actions\Booking;

use App\Enums\AssignmentStatus;
use App\Enums\BookingStatus;
use App\Enums\EquipmentStatus;
use App\Exceptions\InvalidStateTransitionException;
use App\Models\Booking;
use App\Models\User;
use App\Services\Payment\PaymentStatusProvider;
use App\Services\Refund\RefundBoundary;
use App\Support\AuditLogger;
use App\Support\NotificationService;
use Illuminate\Support\Facades\DB;

class CancelBookingAction
{
    /** Status where cancellation is entirely forbidden (in-operation / dispatched). */
    protected const BLOCKED_STATUSES = [
        BookingStatus::DISPATCHED->value,
        BookingStatus::ARRIVED->value,
        BookingStatus::ONGOING->value,
    ];

    /** Pre-payment statuses a USER may cancel directly. */
    protected const USER_CANCELLABLE = [
        BookingStatus::DRAFT->value,
        BookingStatus::SUBMITTED->value,
        BookingStatus::PENDING_APPROVAL->value,
        BookingStatus::APPROVED->value,
        BookingStatus::PAYMENT_PENDING->value,
    ];

    public function __construct(
        protected PaymentStatusProvider $paymentProvider,
        protected RefundBoundary $refundBoundary,
        protected NotificationService $notifications
    ) {}

    /**
     * Cancel a booking with full business-rule guarding (T-B06).
     *
     * @throws InvalidStateTransitionException
     */
    public function execute(User $actor, Booking $booking, string $reason): Booking
    {
        return DB::transaction(function () use ($actor, $booking, $reason) {
            $current = $booking->status;

            if (in_array($current->value, self::BLOCKED_STATUSES, true)) {
                throw new InvalidStateTransitionException(
                    'Booking yang sudah DISPATCHED / ARRIVED / ONGOING tidak dapat dibatalkan.'
                );
            }

            if (in_array($current->value, [BookingStatus::CANCELLED->value, BookingStatus::COMPLETED->value, BookingStatus::EXPIRED->value, BookingStatus::REJECTED->value], true)) {
                throw new InvalidStateTransitionException(
                    'Booking sudah tidak dapat dibatalkan pada status ini.'
                );
            }

            $isOwner = (int) $booking->user_id === (int) $actor->id;
            $isStaff = $actor->isAdmin() || $actor->isOwner();
            $hasPaid = $this->paymentProvider->isSatisfied($booking);

            // Post-payment (CONFIRMED or payment satisfied) requires Admin/OWNER.
            if (! $isStaff && ($hasPaid || $current === BookingStatus::CONFIRMED)) {
                throw new InvalidStateTransitionException(
                    'Pembatalan setelah pembayaran hanya dapat dilakukan Admin/Owner sesuai policy.'
                );
            }

            // Pre-payment direct cancellation required ownership for USER.
            if (! $isStaff && ! $isOwner) {
                throw new InvalidStateTransitionException('Booking bukan milik akun Anda.');
            }

            if (! $isStaff && ! in_array($current->value, self::USER_CANCELLABLE, true)) {
                throw new InvalidStateTransitionException(
                    'Status booking tidak mendukung pembatalan langsung oleh pelanggan.'
                );
            }

            // Release assigned slots (history preserved)
            $currentAssignments = $booking->unitAssignments()
                ->where('is_current', true)
                ->with('unit')
                ->get();

            foreach ($currentAssignments as $assignment) {
                $assignment->update([
                    'is_current' => false,
                    'status' => AssignmentStatus::CANCELLED,
                    'replaced_reason' => 'Booking dibatalkan: slot dilepas',
                ]);

                if ($assignment->unit && $assignment->unit->status === EquipmentStatus::ASSIGNED) {
                    $assignment->unit->update(['status' => EquipmentStatus::AVAILABLE]);
                }
            }

            $oldStatus = $booking->status->value;

            $booking->update([
                'status' => BookingStatus::CANCELLED,
                'cancelled_at' => now(),
                'cancellation_reason' => $reason,
                'payment_deadline_at' => null,
            ]);

            // Post-payment cancellation triggers the refund boundary (deferred to Phase 10)
            if ($hasPaid) {
                $this->refundBoundary->registerPendingRefund($booking, $reason);
            }

            AuditLogger::log('BOOKING_CANCELLED', $booking, [
                'old_status' => $oldStatus,
                'cancelled_by' => $actor->id,
                'is_staff' => $isStaff,
                'refund_pending' => $hasPaid,
            ], [
                'new_status' => BookingStatus::CANCELLED->value,
                'cancellation_reason' => $reason,
                'released_assignments' => $currentAssignments->count(),
            ]);

            $booking->load('user');
            $this->notifications->send(
                $booking->user,
                'BOOKING_CANCELLED',
                $booking,
                "Booking {$booking->booking_code} dibatalkan: {$reason}."
            );

            return $booking->fresh();
        });
    }
}
