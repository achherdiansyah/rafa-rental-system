<?php

namespace App\Policies;

use App\Models\CustomerProfile;
use App\Models\User;

class CustomerProfilePolicy extends BasePolicy
{
    /**
     * Determine whether the user can view the customer profile.
     */
    public function view(User $user, CustomerProfile $profile): bool
    {
        return $user->id === $profile->user_id || $this->isAdmin($user);
    }

    /**
     * Determine whether the user can update the customer profile.
     */
    public function update(User $user, CustomerProfile $profile): bool
    {
        // Users can only update their own profile
        return $user->id === $profile->user_id;
    }

    /**
     * Determine whether the user can verify (KYC) the customer profile.
     */
    public function verify(User $user): bool
    {
        // Both ADMIN and OWNER can perform KYC verifications
        return $this->isAdmin($user);
    }
}
