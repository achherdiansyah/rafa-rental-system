<?php

namespace App\Actions\Profile;

use App\Models\CustomerProfile;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class UpdateUserProfileAction
{
    /**
     * Update user and customer profile details.
     *
     * @param  array<string, mixed>  $data
     */
    public function execute(User $user, array $data): User
    {
        return DB::transaction(function () use ($user, $data) {
            // Update User specific fields
            $userData = [];
            if (isset($data['name'])) {
                $userData['name'] = $data['name'];
            }
            if (isset($data['phone_number'])) {
                $userData['phone_number'] = $data['phone_number'];
            }

            if (! empty($userData)) {
                $user->update($userData);
            }

            // Update Customer Profile specific fields
            $profileData = [];
            if (array_key_exists('company_name', $data)) {
                $profileData['company_name'] = $data['company_name'];
            }
            if (array_key_exists('identity_type', $data)) {
                $profileData['identity_type'] = $data['identity_type'];
            }
            if (array_key_exists('identity_number', $data)) {
                $profileData['identity_number'] = $data['identity_number'];
            }
            if (array_key_exists('address', $data)) {
                $profileData['address'] = $data['address'];
            }

            if (! empty($profileData)) {
                CustomerProfile::updateOrCreate(
                    ['user_id' => $user->id],
                    $profileData
                );
            }

            $user->load('customerProfile');

            return $user;
        });
    }
}
