<?php

namespace App\Actions\Timesheet;

use App\Enums\TimesheetStatus;
use App\Exceptions\InvalidStateTransitionException;
use App\Models\Timesheet;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Support\Facades\DB;

class ValidateTimesheetAction
{
    /**
     * SUBMITTED -> APPROVED (Admin validation, audited).
     *
     * @throws InvalidStateTransitionException
     */
    public function approve(User $admin, Timesheet $timesheet): Timesheet
    {
        return DB::transaction(function () use ($admin, $timesheet) {
            if ($timesheet->status === TimesheetStatus::APPROVED) {
                return $timesheet;
            }

            if ($timesheet->status !== TimesheetStatus::SUBMITTED) {
                throw new InvalidStateTransitionException(
                    'Transisi tidak valid: hanya timesheet SUBMITTED yang dapat disetujui.'
                );
            }

            $oldStatus = $timesheet->status->value;

            $timesheet->update([
                'status' => TimesheetStatus::APPROVED,
                'approved_by' => $admin->id,
            ]);

            AuditLogger::log('TIMESHEET_APPROVED', $timesheet, [
                'old_status' => $oldStatus,
            ], [
                'new_status' => TimesheetStatus::APPROVED->value,
                'approved_by' => $admin->id,
            ]);

            return $timesheet->fresh()->load([
                'rentalDetail.assignment.unit',
                'rentalDetail.rental.booking.projectLocation',
                'attachments',
            ]);
        });
    }

    /**
     * SUBMITTED -> REJECTED + note (Admin, audited).
     *
     * @throws InvalidStateTransitionException
     */
    public function reject(User $admin, Timesheet $timesheet, string $reason): Timesheet
    {
        return DB::transaction(function () use ($admin, $timesheet, $reason) {
            if (! in_array($timesheet->status, [TimesheetStatus::SUBMITTED, TimesheetStatus::APPROVED], true)) {
                throw new InvalidStateTransitionException(
                    'Transisi tidak valid: hanya timesheet SUBMITTED atau APPROVED yang dapat ditolak.'
                );
            }

            $oldStatus = $timesheet->status->value;

            $timesheet->update([
                'status' => TimesheetStatus::REJECTED,
            ]);

            // Persist the rejection note as an immutable revision entry (BR-018)
            $timesheet->revisions()->create([
                'version' => $timesheet->revisions()->count() + 1,
                'old_start_hm' => $timesheet->start_hm,
                'old_end_hm' => $timesheet->end_hm,
                'revision_reason' => 'REJECTED: '.$reason,
                'revised_by' => $admin->id,
                'created_at' => now(),
            ]);

            AuditLogger::log('TIMESHEET_REJECTED', $timesheet, [
                'old_status' => $oldStatus,
            ], [
                'new_status' => TimesheetStatus::REJECTED->value,
                'rejection_reason' => $reason,
                'rejected_by' => $admin->id,
            ]);

            return $timesheet->fresh()->load([
                'rentalDetail.assignment.unit',
                'rentalDetail.rental.booking.projectLocation',
                'attachments',
            ]);
        });
    }
}
