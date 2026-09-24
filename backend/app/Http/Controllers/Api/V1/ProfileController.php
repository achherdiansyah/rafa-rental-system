<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Profile\UpdateUserProfileAction;
use App\Actions\Profile\VerifyCustomerProfileAction;
use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Profile\UpdateProfileRequest;
use App\Http\Requests\Profile\VerifyCustomerProfileRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileController extends ApiController
{
    /**
     * Get the authenticated user profile.
     */
    public function show(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $user->load('customerProfile');

        return $this->success(new UserResource($user), 'Profil berhasil dimuat.');
    }

    /**
     * Update the authenticated user profile.
     */
    public function update(UpdateProfileRequest $request, UpdateUserProfileAction $action): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $updatedUser = $action->execute($user, $request->validated());

        return $this->success(new UserResource($updatedUser), 'Profil berhasil diperbarui.');
    }

    /**
     * Admin/Owner verifies a customer identity profile (KYC).
     */
    public function verify(VerifyCustomerProfileRequest $request, VerifyCustomerProfileAction $action): JsonResponse
    {
        $profile = $action->execute(
            (int) $request->validated('user_id'),
            (string) $request->validated('verification_status')
        );

        return $this->success([
            'user_id' => $profile->user_id,
            'verification_status' => $profile->verification_status,
        ], 'Status verifikasi profil berhasil diperbarui.');
    }
}
