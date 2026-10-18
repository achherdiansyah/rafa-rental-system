<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Profile\UpdateUserProfileAction;
use App\Actions\Profile\VerifyCustomerProfileAction;
use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Profile\UpdateProfileRequest;
use App\Http\Requests\Profile\VerifyCustomerProfileRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

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
     * Change the authenticated user's password (also clears the forced-change flag).
     */
    public function changePassword(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        if (! Hash::check($validated['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['Password saat ini tidak sesuai.'],
            ]);
        }

        $user->update([
            'password' => $validated['password'],
            'must_change_password' => false,
        ]);

        if ($request->user()->currentAccessToken()) {
            $user->tokens()->whereKeyNot($request->user()->currentAccessToken()->getKey())->delete();
        }

        AuditLogger::log('USER_PASSWORD_CHANGED', $user, [], [
            'changed_by' => $user->id,
        ]);

        return $this->success(new UserResource($user->fresh()), 'Password berhasil diperbarui.');
    }

    /**
     * Admin/Owner verifies a customer identity profile (Verifikasi Akun).
     */
    public function verify(VerifyCustomerProfileRequest $request, VerifyCustomerProfileAction $action): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        $profile = $action->execute(
            $actor,
            (int) $request->validated('user_id'),
            (string) $request->validated('verification_status')
        );

        return $this->success([
            'user_id' => $profile->user_id,
            'verification_status' => $profile->verification_status,
        ], 'Status verifikasi akun berhasil diperbarui.');
    }
}
