<?php

namespace App\Policies;

use App\Models\Rental;
use App\Models\User;

class RentalPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Rental $rental): bool
    {
        if ($user->isAdmin() || $user->isOwner()) {
            return true;
        }

        return (int) $user->id === (int) $rental->booking?->user_id;
    }

    /**
     * Rental creation & lifecycle transitions are operational (admin-only).
     */
    public function operate(User $user): bool
    {
        return $user->isAdmin();
    }
}
