<?php

namespace App\Actions\Timesheet;

use App\Enums\TimesheetStatus;
use App\Exceptions\InvalidStateTransitionException;
use App\Models\Timesheet;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Support\Facades\DB;

class SubmitTimesheetAction
{
    /**
     * DRAFT -> SUBMITTED (operator/PIC marks entry ready for Admin validation).
     *
     * @throws InvalidStateTransitionException
     */
    public function execute(User $actor, Timesheet $timesheet): Timesheet
    {
        return DB::transaction(function () use ($actor, $timesheet) {
            if ($timesheet->status !== TimesheetStatus::DRAFT) {
                throw new InvalidStateTransitionException(
                    'Transisi tidak valid: hanya timesheet DRAFT yang dapat disubmit.'
                );
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
            ]);
        });
    }
}
