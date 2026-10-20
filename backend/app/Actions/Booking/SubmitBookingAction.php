<?php

namespace App\Actions\Booking;

use App\Enums\BookingStatus;
use App\Exceptions\BusinessRuleException;
use App\Exceptions\InvalidStateTransitionException;
use App\Models\Booking;
use App\Models\User;
use App\Services\Booking\BookingAvailabilityPrecheckService;
use App\Support\AuditLogger;
use App\Support\RentalDateWindow;
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

            // DRAFT (first submit) or PENDING_APPROVAL returned by Supervisor (revision)
            $isRevision = $booking->status === BookingStatus::PENDING_APPROVAL
                && $booking->supervisor_decision === 'REVISION';

            if ($booking->status !== BookingStatus::DRAFT && ! $isRevision) {
                throw new InvalidStateTransitionException(
                    'Transisi tidak valid: hanya booking DRAFT atau hasil revisi Supervisor yang dapat di-submit.'
                );
            }

// Account verification gate per API Contract 1.4 (403 FORBIDDEN_ACTION if not verified)
              $profile = $user->customerProfile;
              if (! $profile || $profile->verification_status !== 'VERIFIED') {
                  throw new BusinessRuleException(
                      'Akun Anda belum terverifikasi. Tim kami akan menghubungi Anda untuk menyelesaikan verifikasi akun sebelum mengajukan booking.'
                  );
              }

            // Start-date re-check: a draft created on a previous day must
            // still satisfy the minimum start date at submission time.
            $booking->loadMissing('details');
            foreach ($booking->details as $detail) {
                RentalDateWindow::assertAllowed(
                    $detail->start_date->toDateString(),
                    $detail->end_date->toDateString()
                );
            }

            // Availability final pre-check before submission (no reservation)
            $this->precheck->assertBookingAvailable($booking);

            $oldStatus = $booking->status->value;

            $booking->update([
                'status' => BookingStatus::PENDING_APPROVAL,
                'supervisor_validated_at' => null,
                'supervisor_decision' => null,
            ]);

            AuditLogger::log('BOOKING_SUBMITTED', $booking, [
                'old_status' => $oldStatus,
            ], [
                'new_status' => BookingStatus::PENDING_APPROVAL->value,
                'supervisor_revision_resubmitted' => $isRevision,
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
