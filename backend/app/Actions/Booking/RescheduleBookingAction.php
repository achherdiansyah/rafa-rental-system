<?php

namespace App\Actions\Booking;

use App\Enums\BookingStatus;
use App\Exceptions\BusinessRuleException;
use App\Exceptions\InvalidStateTransitionException;
use App\Models\Booking;
use App\Models\User;
use App\Services\Booking\BookingAvailabilityPrecheckService;
use App\Support\AuditLogger;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class RescheduleBookingAction
{
    /** Status where rescheduling is allowed (pre-dispatch, not in operation). */
    protected const RESCHEDULABLE = [
        BookingStatus::APPROVED->value,
        BookingStatus::PAYMENT_PENDING->value,
        BookingStatus::CONFIRMED->value,
    ];

    public function __construct(
        protected BookingAvailabilityPrecheckService $precheck
    ) {}

    /**
     * Reschedule booking dates. Availability + operational buffer are re-checked
     * for the NEW period; the booking returns to PENDING_APPROVAL for Admin
     * confirmation. Payment status is left untouched.
     *
     * @throws BusinessRuleException|InvalidStateTransitionException
     */
    public function execute(User $user, Booking $booking, string $newStart, string $newEnd, string $reason): Booking
    {
        return DB::transaction(function () use ($user, $booking, $newStart, $newEnd, $reason) {
            if ((int) $booking->user_id !== (int) $user->id && ! ($user->isAdmin() || $user->isOwner())) {
                throw new InvalidStateTransitionException('Booking bukan milik akun Anda.');
            }

            if (! in_array($booking->status->value, self::RESCHEDULABLE, true)) {
                throw new InvalidStateTransitionException(
                    'Reschedule hanya berlaku untuk booking APPROVED / PAYMENT_PENDING / CONFIRMED.'
                );
            }

            $start = Carbon::parse($newStart)->startOfDay();
            $end = Carbon::parse($newEnd)->startOfDay();

            if ($end->lessThan($start)) {
                throw new BusinessRuleException('Tanggal selesai tidak boleh sebelum tanggal mulai.');
            }

            // Availability + buffer pre-check on new window
            $lines = $booking->details->map(fn ($d) => [
                'equipment_model_id' => $d->equipment_model_id,
                'quantity' => $d->quantity,
            ])->all();

            $this->precheck->assertAvailable($lines, $start, $end);

            // Record history of old -> new before mutating details
            $history = $booking->reschedule_history ?? [];
            $history[] = [
                'requested_by' => $user->id,
                'requested_at' => now()->toIso8601String(),
                'old_start' => $booking->details->min('start_date')?->toDateString(),
                'old_end' => $booking->details->max('end_date')?->toDateString(),
                'new_start' => $start->toDateString(),
                'new_end' => $end->toDateString(),
                'reason' => $reason,
            ];

            $oldStatus = $booking->status->value;

            // Update all detail dates to the new period
            foreach ($booking->details as $detail) {
                $detail->update([
                    'start_date' => $start->toDateString(),
                    'end_date' => $end->toDateString(),
                ]);
            }

            $booking->update([
                'status' => BookingStatus::PENDING_APPROVAL,
                'reschedule_requested_at' => now(),
                'reschedule_reason' => $reason,
                'reschedule_history' => $history,
            ]);

            AuditLogger::log('BOOKING_RESCHEDULED', $booking, [
                'old_status' => $oldStatus,
                'old_start' => $history[count($history) - 1]['old_start'],
                'old_end' => $history[count($history) - 1]['old_end'],
            ], [
                'new_status' => BookingStatus::PENDING_APPROVAL->value,
                'new_start' => $start->toDateString(),
                'new_end' => $end->toDateString(),
                'reason' => $reason,
            ]);

            return $booking->fresh()->load('details');
        });
    }
}
