<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthAndRoleTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['auth:sanctum', 'role:ADMIN,OWNER'])->get('/api/test-admin-only', function () {
            return response()->json(['message' => 'Admin area']);
        });

        Route::middleware(['auth:sanctum', 'role:OWNER'])->get('/api/test-owner-only', function () {
            return response()->json(['message' => 'Owner area']);
        });
    }

    public function test_unauthenticated_request_returns_standard_401(): void
    {
        $response = $this->getJson('/api/v1/auth/me');

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'message' => 'Unauthenticated.',
                'code' => 'UNAUTHENTICATED',
            ]);
    }

    public function test_user_role_blocked_from_admin_route_returns_standard_403(): void
    {
        $user = new User([
            'id' => 1,
            'name' => 'Regular User',
            'email' => 'user@example.com',
            'role' => UserRole::USER,
            'is_active' => true,
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/test-admin-only');

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'You do not have the required role permissions.',
                'code' => 'FORBIDDEN_ACTION',
            ]);
    }

    public function test_admin_role_can_access_admin_route(): void
    {
        $admin = new User([
            'id' => 2,
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'role' => UserRole::ADMIN,
            'is_active' => true,
        ]);

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/test-admin-only');

        $response->assertStatus(200)
            ->assertJson(['message' => 'Admin area']);
    }

    public function test_admin_blocked_from_owner_only_route(): void
    {
        $admin = new User([
            'id' => 2,
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'role' => UserRole::ADMIN,
            'is_active' => true,
        ]);

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/test-owner-only');

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'code' => 'FORBIDDEN_ACTION',
            ]);
    }

    public function test_owner_can_access_owner_route(): void
    {
        $owner = new User([
            'id' => 3,
            'name' => 'Owner User',
            'email' => 'owner@example.com',
            'role' => UserRole::OWNER,
            'is_active' => true,
        ]);

        Sanctum::actingAs($owner);

        $response = $this->getJson('/api/test-owner-only');

        $response->assertStatus(200)
            ->assertJson(['message' => 'Owner area']);
    }

    public function test_permission_gates_match_matrix(): void
    {
        $user = new User(['id' => 1, 'role' => UserRole::USER]);
        $admin = new User(['id' => 2, 'role' => UserRole::ADMIN]);
        $owner = new User(['id' => 3, 'role' => UserRole::OWNER]);

        // User cannot approve bookings
        $this->assertFalse(Gate::forUser($user)->allows('approve-booking'));
        $this->assertTrue(Gate::forUser($admin)->allows('approve-booking'));
        $this->assertTrue(Gate::forUser($owner)->allows('approve-booking'));

        // Only Admin can assign units
        $this->assertFalse(Gate::forUser($user)->allows('assign-units'));
        $this->assertTrue(Gate::forUser($admin)->allows('assign-units'));
        $this->assertFalse(Gate::forUser($owner)->allows('assign-units'));

        // Only Owner can view revenue reports
        $this->assertFalse(Gate::forUser($user)->allows('view-revenue-reports'));
        $this->assertFalse(Gate::forUser($admin)->allows('view-revenue-reports'));
        $this->assertTrue(Gate::forUser($owner)->allows('view-revenue-reports'));
    }
}
