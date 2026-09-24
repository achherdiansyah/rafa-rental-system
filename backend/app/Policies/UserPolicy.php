<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy extends BasePolicy
{
    /**
     * Determine whether the user can view the list of all users.
     */
    public function viewAny(User $user): bool
    {
        return $this->isAdmin($user); // ADMIN or OWNER
    }

    /**
     * Determine whether the user can view the specific user.
     */
    public function view(User $user, User $model): bool
    {
        return $user->id === $model->id || $this->isAdmin($user);
    }

    /**
     * Determine whether the user can update the specific user.
     */
    public function update(User $user, User $model): bool
    {
        // Each role can only update their own personal user data
        return $user->id === $model->id;
    }

    /**
     * Determine whether the user can deactivate a user account.
     */
    public function deactivate(User $user, User $model): bool
    {
        // Only OWNER can deactivate accounts, and cannot deactivate themselves
        return $this->isOwner($user) && $user->id !== $model->id;
    }
}
