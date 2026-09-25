<?php

namespace App\Policies;

use App\Models\RecommendationRequest;
use App\Models\User;

class RecommendationRequestPolicy
{
    /**
     * Determine whether the user can view any recommendation requests.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the specific recommendation request.
     */
    public function view(User $user, RecommendationRequest $request): bool
    {
        // Admin & Owner can view all recommendation requests
        if ($user->isAdmin() || $user->isOwner()) {
            return true;
        }

        // Regular users can only view their own recommendation requests
        return (int) $user->id === (int) $request->user_id;
    }

    /**
     * Determine whether the user can create a recommendation request.
     */
    public function create(User $user): bool
    {
        return true;
    }
}
