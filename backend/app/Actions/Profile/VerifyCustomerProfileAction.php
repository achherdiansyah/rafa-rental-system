<?php

namespace App\Actions\Profile;

use App\Models\CustomerProfile;

class VerifyCustomerProfileAction
{
    /**
     * Update customer identity verification status.
     */
    public function execute(int $userId, string $status): CustomerProfile
    {
        return CustomerProfile::updateOrCreate(
            ['user_id' => $userId],
            ['verification_status' => $status]
        );
    }
}
