<?php

namespace App\Actions\Booking;

use App\Enums\BookingStatus;
use App\Exceptions\BusinessRuleException;
use App\Exceptions\InvalidStateTransitionException;
use App\Models\Booking;
use App\Models\User;
use App\Services\Booking\BookingAvailabilityPrecheckService;
use App\Support\AuditLogger;
use Illuminate\Support\Facades\DB;

class SubmitBookingAction
{
    public function __construct(
        protected BookingAvailabilityPrecheckService $precheck
    ) {}

    /**
     * Transition booking status DRAFT -> SUBMITTED -> PENDING_APPROVAL.
     * Re-runs availability pre-check before transition (T-B01).
     *
     * @throws BusinessRuleException|InvalidStateTransitionException
     */
    public function execute(User $user, Booking $booking): Booking
    {
        return DB::transaction(function () use ($user, $booking) {
            if ((int) $booking->user_id !== (int) $user->id) {
                throw new InvalidStateTransitionException('Booking bukan milik akun Anda.');
            }

            if ($booking->status !== BookingStatus::DRAFT) {
                throw new InvalidStateTransitionException(
                    'Transisi tidak valid: hanya booking berstatus DRAFT yang dapat di-submit.'
                );
            }

            // KYC gate per API Contract 1.4 (403 FORBIDDEN_ACTION if not verified)
            $profile = $user->customerProfile;
            if (! $profile || $profile->verification_status !== 'VERIFIED') {
                throw new BusinessRuleException(
                    'Profil identitas Anda belum terverifikasi. Selesaikan verifikasi (KYC) sebelum submit booking.'
                );
            }

            // Availability final pre-check before submission (no reservation)
            $this->precheck->assertBookingAvailable($booking);

            $oldStatus = $booking->status->value;

            $booking->update([
                'status' => BookingStatus::PENDING_APPROVAL,
            ]);

            AuditLogger::log('BOOKING_SUBMITTED', $booking, [
                'old_status' => $oldStatus,
            ], [
                'new_status' => BookingStatus::PENDING_APPROVAL->value,
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
