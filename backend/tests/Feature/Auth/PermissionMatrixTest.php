<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Models\CustomerProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PermissionMatrixTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_role_matrix_permissions(): void
    {
        $user = User::factory()->create(['role' => UserRole::USER]);
        $targetUser = User::factory()->create(['name' => 'Target Person']);

        Sanctum::actingAs($user);

        // 1. Allowed: View Own Profile
        $response = $this->getJson('/api/v1/profile');
        $response->assertStatus(200);

        // 2. Allowed: Update Own Profile
        $response = $this->putJson('/api/v1/profile', ['name' => 'My Updated Name']);
        $response->assertStatus(200);

        // 3. Forbidden: View All Users
        $response = $this->getJson('/api/v1/admin/users');
        $response->assertStatus(403)
            ->assertJson(['code' => 'FORBIDDEN_ACTION']);

        // 4. Forbidden: View Specific Other User
        $response = $this->getJson("/api/v1/admin/users/{$targetUser->id}");
        $response->assertStatus(403);

        // 5. Forbidden: Verify Customer KYC
        $response = $this->postJson('/api/v1/profile/verify', [
            'user_id' => $targetUser->id,
            'verification_status' => 'VERIFIED',
        ]);
        $response->assertStatus(403);

        // 6. Forbidden: Deactivate Account
        $response = $this->postJson("/api/v1/owner/users/{$targetUser->id}/deactivate");
        $response->assertStatus(403);

        // 7. Policy Gate Evaluation
        $this->assertFalse(Gate::forUser($user)->allows('viewAny', User::class));
        $this->assertTrue(Gate::forUser($user)->allows('view', $user));
        $this->assertFalse(Gate::forUser($user)->allows('view', $targetUser));
        $this->assertTrue(Gate::forUser($user)->allows('update', $user));
        $this->assertFalse(Gate::forUser($user)->allows('update', $targetUser));
        $this->assertFalse(Gate::forUser($user)->allows('deactivate-user'));
    }

    public function test_admin_role_matrix_permissions(): void
    {
        $admin = User::factory()->admin()->create();
        $targetUser = User::factory()->create(['name' => 'Target Customer']);
        CustomerProfile::factory()->create(['user_id' => $targetUser->id]);

        Sanctum::actingAs($admin);

        // 1. Allowed: View All Users
        $response = $this->getJson('/api/v1/admin/users');
        $response->assertStatus(200);

        // 2. Allowed: View Specific User
        $response = $this->getJson("/api/v1/admin/users/{$targetUser->id}");
        $response->assertStatus(200);

        // 3. Allowed: Verify Customer KYC
        $response = $this->postJson('/api/v1/profile/verify', [
            'user_id' => $targetUser->id,
            'verification_status' => 'VERIFIED',
        ]);
        $response->assertStatus(200);

        // 4. Forbidden: Deactivate User (OWNER only)
        $response = $this->postJson("/api/v1/owner/users/{$targetUser->id}/deactivate");
        $response->assertStatus(403)
            ->assertJson(['code' => 'FORBIDDEN_ACTION']);

        // 5. Policy Gate Evaluation
        $this->assertTrue(Gate::forUser($admin)->allows('viewAny', User::class));
        $this->assertTrue(Gate::forUser($admin)->allows('view', $targetUser));
        $this->assertFalse(Gate::forUser($admin)->allows('deactivate-user'));
    }

    public function test_owner_role_matrix_permissions(): void
    {
        $owner = User::factory()->owner()->create();
        $targetUser = User::factory()->create(['name' => 'Subordinate User']);
        CustomerProfile::factory()->create(['user_id' => $targetUser->id]);

        Sanctum::actingAs($owner);

        // 1. Allowed: View All Users
        $response = $this->getJson('/api/v1/admin/users');
        $response->assertStatus(200);

        // 2. Allowed: Verify Customer KYC
        $response = $this->postJson('/api/v1/profile/verify', [
            'user_id' => $targetUser->id,
            'verification_status' => 'VERIFIED',
        ]);
        $response->assertStatus(200);

        // 3. Allowed: Deactivate Other User Account
        $response = $this->postJson("/api/v1/owner/users/{$targetUser->id}/deactivate");
        $response->assertStatus(200);
        $this->assertFalse($targetUser->fresh()->is_active);

        // 4. Forbidden: Owner cannot deactivate themselves
        $response = $this->postJson("/api/v1/owner/users/{$owner->id}/deactivate");
        $response->assertStatus(400)
            ->assertJson(['code' => 'FORBIDDEN_ACTION']);

        // 5. Policy Gate Evaluation
        $this->assertTrue(Gate::forUser($owner)->allows('viewAny', User::class));
        $this->assertTrue(Gate::forUser($owner)->allows('deactivate-user'));
    }
}
