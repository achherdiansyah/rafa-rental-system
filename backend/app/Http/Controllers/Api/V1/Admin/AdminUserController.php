<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Admin\StoreManagedUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

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
     * Create a staff account (SUPERVISOR/OPERATOR). ADMIN/OWNER cannot be
     * created here. A temporary password is returned exactly once and the user
     * must change it before using operational endpoints.
     */
    public function store(StoreManagedUserRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $temporaryPassword = Str::password(16);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone_number' => $validated['phone_number'] ?? null,
            'role' => $validated['role'],
            'is_active' => (bool) ($validated['is_active'] ?? true),
            'password' => $temporaryPassword,
            'must_change_password' => true,
            'email_verified_at' => now(),
        ]);

        AuditLogger::log('USER_CREATED', $user, [], [
            'role' => $user->role->value,
            'created_by' => $request->user()->id,
        ]);

        $data = (new UserResource($user))->resolve();
        $data['temporary_password'] = $temporaryPassword;

        return $this->created($data, 'Akun staf berhasil dibuat. Password sementara wajib diganti saat login pertama.');
    }

    /**
     * View specific user profile.
     */
    public function show(User $user): JsonResponse
    {
        $user->load('customerProfile', 'projectLocations');

        return $this->success(new UserResource($user), 'Detail pengguna berhasil dimuat.');
    }

    /**
     * Admin assigns an operational role (USER/SUPERVISOR/OPERATOR) to an account.
     * ADMIN/OWNER accounts cannot be changed here.
     */
    public function updateRole(Request $request, User $user): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        if (! $actor->isAdmin()) {
            return $this->forbidden('Hanya Admin yang dapat mengubah role pengguna.');
        }

        if ($user->isAdmin() || $user->isOwner()) {
            return $this->error(
                'Role akun Admin/Owner tidak dapat diubah dari sini.',
                [],
                409,
                'BUSINESS_RULE_VIOLATION'
            );
        }

        $validated = $request->validate([
            'role' => ['required', Rule::in([
                UserRole::USER->value,
                UserRole::SUPERVISOR->value,
                UserRole::OPERATOR->value,
            ])],
        ]);

        $oldRole = $user->role?->value;
        $user->update(['role' => $validated['role']]);

        AuditLogger::log('USER_ROLE_UPDATED', $user, ['old_role' => $oldRole], [
            'new_role' => $validated['role'],
            'updated_by' => $actor->id,
        ]);

        return $this->success(new UserResource($user->fresh()), 'Role pengguna berhasil diperbarui.');
    }
}
