<?php

namespace App\Actions\Profile;

use App\Models\CustomerProfile;
use App\Models\User;
use App\Support\AuditLogger;

class VerifyCustomerProfileAction
{
    /**
     * Update customer identity verification status.
     */
    public function execute(User $admin, int $userId, string $status): CustomerProfile
    {
        $profile = CustomerProfile::updateOrCreate(
            ['user_id' => $userId],
            ['verification_status' => $status]
        );

        AuditLogger::log('ACCOUNT_VERIFIED', $profile, [], [
            'new_status' => $status,
            'verified_by' => $admin->id,
            'verified_at' => now()->toIso8601String(),
        ]);

        return $profile;
    }
}
