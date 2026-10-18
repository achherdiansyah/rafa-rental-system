<?php

namespace App\Policies;

use App\Models\Timesheet;
use App\Models\User;

class TimesheetPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Timesheet $timesheet): bool
    {
        if ($user->isAdmin() || $user->isOwner()) {
            return true;
        }

        if ($user->isOperator()) {
            return (bool) $timesheet->rentalDetail?->operatorAssignments()
                ->where('operator_user_id', $user->id)
                ->exists();
        }

        return (int) $user->id === (int) $timesheet->rentalDetail?->rental?->booking?->user_id;
    }

    /**
     * Timesheet input is an ADMIN operational task (based on field/operator
     * daily report). User/PIC confirms + signs; Owner is read-only.
     */
    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->isOperator();
    }

    /**
     * Submit (DRAFT/REJECTED → SUBMITTED = menunggu konfirmasi user) is an
     * ADMIN action after inputting the record.
     */
    public function submit(User $user, Timesheet $timesheet): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $user->isOperator()
            && $timesheet->rentalDetail?->operatorAssignments()
                ->where('operator_user_id', $user->id)
                ->exists() ?? false;
    }

    public function uploadPaper(User $user, Timesheet $timesheet): bool
    {
        return $this->submit($user, $timesheet);
    }

    /**
     * PIC/operator signature: owner of the rental or staff.
     */
    public function sign(User $user, Timesheet $timesheet): bool
    {
        if ($user->isAdmin() || $user->isOwner()) {
            return true;
        }

        return (int) $user->id === (int) $timesheet->rentalDetail?->rental?->booking?->user_id;
    }

    /**
     * Admin validation (approve/reject) and correction (revise).
     */
    public function validate(User $user): bool
    {
        return $user->isAdmin();
    }
}
