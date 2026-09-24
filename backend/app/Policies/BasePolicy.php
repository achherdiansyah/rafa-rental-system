<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;

abstract class BasePolicy
{
    protected function isAdmin(User $user): bool
    {
        return $user->hasRole(UserRole::ADMIN, UserRole::OWNER);
    }

    protected function isOwner(User $user): bool
    {
        return $user->isOwner();
    }

    protected function isUser(User $user): bool
    {
        return $user->isUser();
    }

    protected function isActiveUser(User $user): bool
    {
        return (bool) $user->is_active;
    }
}
