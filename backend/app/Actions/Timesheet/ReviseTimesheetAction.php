<?php

namespace App\Actions\Timesheet;

use App\Actions\Invoice\CreateDailyInvoiceAction;
use App\Enums\TimesheetStatus;
use App\Exceptions\BusinessRuleException;
use App\Exceptions\InvalidStateTransitionException;
use App\Models\Timesheet;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Support\Facades\DB;

class ReviseTimesheetAction
{
    public function __construct(
        private readonly CreateDailyInvoiceAction $dailyInvoiceAction
    ) {}

    /**
     * Admin correction on an APPROVED timesheet. The OLD values are snapshotted
     * into timesheet_revisions (append-only) BEFORE the update; working hours are
     * recomputed server-side; the timesheet returns to SUBMITTED for re-validation.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws BusinessRuleException|InvalidStateTransitionException
     */
    public function execute(User $admin, Timesheet $timesheet, array $data, string $reason): Timesheet
    {
        return DB::transaction(function () use ($admin, $timesheet, $data, $reason) {
            if ($timesheet->status !== TimesheetStatus::APPROVED) {
                throw new InvalidStateTransitionException(
                    'Koreksi hanya diperbolehkan pada timesheet berstatus APPROVED.'
                );
            }

            $startTime = $data['start_time'] ?? $timesheet->start_time;
            $endTime = $data['end_time'] ?? $timesheet->end_time;
            $breakMinutes = (int) ($data['break_minutes'] ?? $timesheet->break_minutes);
            $standby = (float) ($data['standby_hours'] ?? $timesheet->standby_hours);
            $breakdown = (float) ($data['breakdown_hours'] ?? $timesheet->breakdown_hours);

            if ($startTime && $endTime) {
                $startCarbon = \Carbon\Carbon::createFromFormat('H:i', $startTime);
                $endCarbon = \Carbon\Carbon::createFromFormat('H:i', $endTime);
                if ($endCarbon->lessThanOrEqualTo($startCarbon)) {
                    throw new BusinessRuleException('Durasi tidak valid: jam selesai harus lebih besar dari jam mulai.');
                }
                $elapsed = $startCarbon->diffInMinutes($endCarbon) / 60;
                $startHm = isset($data['start_hm']) ? (float) $data['start_hm'] : $timesheet->start_hm;
                $endHm = isset($data['end_hm']) ? (float) $data['end_hm'] : $timesheet->end_hm;
            } else {
                $startHm = (float) ($data['start_hm'] ?? $timesheet->start_hm);
                $endHm = (float) ($data['end_hm'] ?? $timesheet->end_hm);
                if ($endHm <= $startHm) {
                    throw new BusinessRuleException('Durasi tidak valid: end_hm harus lebih besar dari start_hm.');
                }
                $elapsed = $endHm - $startHm;
            }

            // Dihitung apa adanya (tanpa pembulatan buatan); presisi 2 desimal ditangani kolom DECIMAL(8,2).
            $workHours = $elapsed - ($breakMinutes / 60);
            if ($workHours < 0 || $workHours < ($breakdown + $standby)) {
                throw new BusinessRuleException('Jam kerja aktual tidak konsisten dengan total breakdown + standby.');
            }

            // Append-only history: snapshot the current (old) values + change actor + reason + timestamp
            $timesheet->revisions()->create([
                'version' => $timesheet->revisions()->count() + 1,
                'old_start_time' => $timesheet->start_time,
                'old_end_time' => $timesheet->end_time,
                'old_start_hm' => $timesheet->start_hm,
                'old_end_hm' => $timesheet->end_hm,
                'revision_reason' => $reason,
                'revised_by' => $admin->id,
                'created_at' => now(),
            ]);

            $timesheet->update([
                'start_time' => $startTime,
                'end_time' => $endTime,
                'start_hm' => $startHm,
                'end_hm' => $endHm,
                'break_minutes' => $breakMinutes,
                'standby_hours' => $standby,
                'breakdown_hours' => $breakdown,
                'total_work_hours' => $workHours,
                'notes' => $data['notes'] ?? $timesheet->notes,
                'status' => TimesheetStatus::APPROVED, // Stays APPROVED under new rules
            ]);

            $this->dailyInvoiceAction->updateForTimesheet($admin, $timesheet);

            AuditLogger::log('TIMESHEET_REVISED', $timesheet, [
                'old_start_hm' => $timesheet->start_hm,
                'old_end_hm' => $timesheet->end_hm,
            ], [
                'new_start_hm' => $startHm,
                'new_end_hm' => $endHm,
                'new_total_work_hours' => $workHours,
                'revision_reason' => $reason,
                'revised_by' => $admin->id,
            ]);

            return $timesheet->fresh()->load([
                'rentalDetail.assignment.unit',
                'rentalDetail.rental.booking.projectLocation',
                'attachments',
            ]);
        });
    }
}
