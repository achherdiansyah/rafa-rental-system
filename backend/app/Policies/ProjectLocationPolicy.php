<?php

namespace App\Policies;

use App\Models\ProjectLocation;
use App\Models\User;

class ProjectLocationPolicy
{
    /**
     * Determine whether the user can view any project locations.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the specific project location.
     */
    public function view(User $user, ProjectLocation $projectLocation): bool
    {
        if ($user->isAdmin() || $user->isOwner()) {
            return true;
        }

        return (int) $user->id === (int) $projectLocation->user_id;
    }

    /**
     * Determine whether the user can create project locations.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the project location.
     */
    public function update(User $user, ProjectLocation $projectLocation): bool
    {
        if ($user->isAdmin() || $user->isOwner()) {
            return true;
        }

        return (int) $user->id === (int) $projectLocation->user_id;
    }

    /**
     * Determine whether the user can delete the project location.
     */
    public function delete(User $user, ProjectLocation $projectLocation): bool
    {
        if ($user->isAdmin() || $user->isOwner()) {
            return true;
        }

        return (int) $user->id === (int) $projectLocation->user_id;
    }
}
