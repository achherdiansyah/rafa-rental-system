<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminUserController extends ApiController
{
    /**
     * List all users with their customer profiles.
     */
    public function index(Request $request): JsonResponse
    {
        $query = User::with('customerProfile')->latest();

        if ($role = $request->query('role')) {
            $query->where('role', $role);
        }

        $users = $query->paginate(15);

        return $this->success(UserResource::collection($users->items()), 'Daftar pengguna berhasil dimuat.', 200, [
            'current_page' => $users->currentPage(),
            'per_page' => $users->perPage(),
            'total' => $users->total(),
            'last_page' => $users->lastPage(),
        ]);
    }

    /**
     * View specific user profile.
     */
    public function show(User $user): JsonResponse
    {
        $user->load('customerProfile', 'projectLocations');

        return $this->success(new UserResource($user), 'Detail pengguna berhasil dimuat.');
    }
}
