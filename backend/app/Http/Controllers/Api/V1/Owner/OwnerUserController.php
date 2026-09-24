<?php

namespace App\Http\Controllers\Api\V1\Owner;

use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class OwnerUserController extends ApiController
{
    /**
     * Deactivate a user account (Owner exclusive).
     */
    public function deactivate(Request $request, User $user): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        Gate::authorize('deactivate-user');

        if ($actor->id === $user->id) {
            return $this->error('Anda tidak dapat menonaktifkan akun Anda sendiri.', null, 400, 'FORBIDDEN_ACTION');
        }

        $user->update(['is_active' => false]);

        return $this->success(new UserResource($user), 'Akun pengguna berhasil dinonaktifkan.');
    }
}
