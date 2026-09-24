<?php

namespace App\Actions\Auth;

use App\Enums\UserRole;
use App\Models\CustomerProfile;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class RegisterUserAction
{
    /**
     * Execute user registration.
     *
     * @param  array<string, mixed>  $data
     * @return array{user: User, token: string}
     */
    public function execute(array $data): array
    {
        return DB::transaction(function () use ($data) {
            /** @var User $user */
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'role' => UserRole::USER, // Always force USER role for public registration
                'phone_number' => $data['phone_number'],
                'is_active' => true,
            ]);

            // Create customer profile if identity details are provided
            CustomerProfile::create([
                'user_id' => $user->id,
                'company_name' => $data['company_name'] ?? null,
                'identity_type' => $data['identity_type'] ?? 'KTP',
                'identity_number' => $data['identity_number'] ?? null,
                'address' => $data['address'] ?? null,
                'verification_status' => 'UNVERIFIED',
            ]);

            $token = $user->createToken('auth_token')->plainTextToken;

            // Load profile relationship
            $user->load('customerProfile');

            return [
                'user' => $user,
                'token' => $token,
            ];
        });
    }
}
