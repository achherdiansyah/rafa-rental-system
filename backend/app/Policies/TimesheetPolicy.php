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

        return (int) $user->id === (int) $timesheet->rentalDetail?->rental?->booking?->user_id;
    }

    /**
     * Operator / owner-of-rental (USER) or ADMIN can create a timesheet entry.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Submit timesheet: own rental (USER) or staff.
     */
    public function submit(User $user, Timesheet $timesheet): bool
    {
        if ($user->isAdmin() || $user->isOwner()) {
            return true;
        }

        return (int) $user->id === (int) $timesheet->rentalDetail?->rental?->booking?->user_id;
    }
}
