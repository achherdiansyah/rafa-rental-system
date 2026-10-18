<?php

namespace App\Actions\Timesheet;

use App\Enums\TimesheetStatus;
use App\Exceptions\BusinessRuleException;
use App\Exceptions\InvalidStateTransitionException;
use App\Models\Timesheet;
use App\Models\User;
use App\Services\Billing\PaymentHoldService;
use App\Support\AuditLogger;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

class SubmitTimesheetAction
{
    public function __construct(
        private readonly PaymentHoldService $paymentHolds
    ) {}

    /**
     * DRAFT -> SUBMITTED and REJECTED -> SUBMITTED (operator/PIC marks entry
     * ready for Admin validation; resubmission after rejection is allowed).
     *
     * @throws InvalidStateTransitionException
     */
    public function execute(User $actor, Timesheet $timesheet): Timesheet
    {
        return DB::transaction(function () use ($actor, $timesheet) {
            if ($timesheet->status === TimesheetStatus::APPROVED) {
                return $timesheet;
            }

            if (! in_array($timesheet->status, [TimesheetStatus::DRAFT, TimesheetStatus::REJECTED], true)) {
                throw new InvalidStateTransitionException(
                    'Transisi tidak valid: hanya timesheet DRAFT/REJECTED yang dapat disubmit.'
                );
            }

            $timesheet->loadMissing(['rentalDetail.rental', 'attachments']);

            if ($actor->isOperator()) {
                $assigned = $timesheet->rentalDetail?->operatorAssignments()
                    ->where('operator_user_id', $actor->id)
                    ->where('status', 'ACTIVE')
                    ->exists();

                if (! $assigned) {
                    throw new AuthorizationException('Anda hanya dapat submit Timesheet untuk penugasan aktif Anda.');
                }

                $hasPaper = $timesheet->attachments
                    ->where('document_type', 'TIMESHEET_PAPER')
                    ->isNotEmpty();

                if (! $hasPaper) {
                    throw new BusinessRuleException('Foto formulir Timesheet kertas wajib diunggah sebelum submit.');
                }
            }

            if ($timesheet->rentalDetail?->rental && $this->paymentHolds->isHeld($timesheet->rentalDetail->rental)) {
                throw new BusinessRuleException('Rental sedang dalam PAYMENT HOLD. Timesheet tidak dapat disubmit.');
            }

            $oldStatus = $timesheet->status->value;

            $timesheet->update(['status' => TimesheetStatus::SUBMITTED]);

            AuditLogger::log('TIMESHEET_SUBMITTED', $timesheet, [
                'old_status' => $oldStatus,
            ], [
                'new_status' => TimesheetStatus::SUBMITTED->value,
                'submitted_by' => $actor->id,
            ]);

            return $timesheet->fresh()->load([
                'rentalDetail.assignment.unit',
                'rentalDetail.rental.booking.projectLocation',
                'attachments',
            ]);
        });
    }
}
