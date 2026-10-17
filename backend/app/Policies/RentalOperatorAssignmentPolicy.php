<?php

namespace App\Policies;

use App\Models\RentalOperatorAssignment;
use App\Models\User;

class RentalOperatorAssignmentPolicy
{
    public function view(User $user, RentalOperatorAssignment $assignment): bool
    {
        if ($user->isAdmin() || $user->isOwner()) {
            return true;
        }

        return $user->isOperator()
            && (int) $assignment->operator_user_id === (int) $user->id
            && $assignment->status === \App\Models\RentalOperatorAssignment::ACTIVE;
    }

    public function manage(User $user): bool
    {
        return $user->isAdmin();
    }
}