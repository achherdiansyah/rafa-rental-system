<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Models\CustomerProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthWorkflowIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private function flushAuthGuard(): void
    {
        $this->app['auth']->forgetGuards();
    }

    public function test_complete_user_lifecycle_workflow(): void
    {
        // 1. User registers via public endpoint
        $registerPayload = [
            'name' => 'Ahmad Dani',
            'email' => 'ahmad@kontraktor.com',
            'password' => 'secret12345',
            'password_confirmation' => 'secret12345',
            'phone_number' => '081299887766',
            'company_name' => 'PT Dani Mandiri',
            'identity_type' => 'KTP',
            'identity_number' => '3201019988770001',
            'address' => 'Jl. Kebon Jeruk No. 5',
        ];

        $regResponse = $this->postJson('/api/v1/auth/register', $registerPayload);
        $regResponse->assertStatus(201);
        $token = $regResponse->json('data.token');
        $userId = $regResponse->json('data.user.id');

        $this->assertNotNull($token);
        $this->assertEquals(UserRole::USER->value, $regResponse->json('data.user.role'));

        $this->flushAuthGuard();

        // 2. User accesses own profile using token
        $profileResponse = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/profile');

        $profileResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $userId,
                    'name' => 'Ahmad Dani',
                    'customer_profile' => [
                        'company_name' => 'PT Dani Mandiri',
                        'verification_status' => 'UNVERIFIED',
                    ],
                ],
            ]);

        $this->flushAuthGuard();

        // 3. User updates phone and address
        $updateResponse = $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson('/api/v1/profile', [
                'phone_number' => '081299887799',
                'address' => 'Jl. Kebon Jeruk No. 10 (Pindah)',
            ]);

        $updateResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'phone_number' => '081299887799',
                    'customer_profile' => [
                        'address' => 'Jl. Kebon Jeruk No. 10 (Pindah)',
                    ],
                ],
            ]);

        $this->flushAuthGuard();

        // 4. User attempts forbidden actions (access admin area)
        $adminAccess = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/admin/users');
        $adminAccess->assertStatus(403);

        $this->flushAuthGuard();

        // 5. User logs out
        $logoutResponse = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/auth/logout');
        $logoutResponse->assertStatus(200);

        $this->flushAuthGuard();

        // 6. Old token is immediately invalid
        $afterLogout = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/auth/me');
        $afterLogout->assertStatus(401);
    }

    public function test_admin_and_owner_administrative_workflows(): void
    {
        // 1. Setup Admin, Owner, and Customer
        $admin = User::factory()->admin()->create(['email' => 'admin@domain.com', 'password' => 'adminpass']);
        $owner = User::factory()->owner()->create(['email' => 'owner@domain.com', 'password' => 'ownerpass']);
        $customer = User::factory()->create(['email' => 'cust@domain.com']);
        CustomerProfile::factory()->create(['user_id' => $customer->id, 'verification_status' => 'UNVERIFIED']);

        // 2. Admin logs in
        $adminLogin = $this->postJson('/api/v1/auth/login', [
            'email' => 'admin@domain.com',
            'password' => 'adminpass',
        ]);
        $adminToken = $adminLogin->json('data.token');

        $this->flushAuthGuard();

        // Admin verifies customer KYC
        $verifyResponse = $this->withHeader('Authorization', "Bearer {$adminToken}")
            ->postJson('/api/v1/profile/verify', [
                'user_id' => $customer->id,
                'verification_status' => 'VERIFIED',
            ]);
        $verifyResponse->assertStatus(200);
        $this->assertEquals('VERIFIED', $customer->customerProfile->fresh()->verification_status);

        $this->flushAuthGuard();

        // Admin attempts Owner-only action (Deactivate user) -> Forbidden (403)
        $this->withHeader('Authorization', "Bearer {$adminToken}")
            ->postJson("/api/v1/owner/users/{$customer->id}/deactivate")
            ->assertStatus(403);

        $this->flushAuthGuard();

        // 3. Owner logs in
        $ownerLogin = $this->postJson('/api/v1/auth/login', [
            'email' => 'owner@domain.com',
            'password' => 'ownerpass',
        ]);
        $ownerToken = $ownerLogin->json('data.token');

        $this->flushAuthGuard();

        // Owner deactivates customer account -> Success (200)
        $deactivateResponse = $this->withHeader('Authorization', "Bearer {$ownerToken}")
            ->postJson("/api/v1/owner/users/{$customer->id}/deactivate");
        $deactivateResponse->assertStatus(200);
        $this->assertFalse($customer->fresh()->is_active);

        $this->flushAuthGuard();

        // Owner cannot deactivate their own account -> Bad Request (400)
        $selfDeactivate = $this->withHeader('Authorization', "Bearer {$ownerToken}")
            ->postJson("/api/v1/owner/users/{$owner->id}/deactivate");
        $selfDeactivate->assertStatus(400);
    }

    public function test_security_negative_attack_vectors(): void
    {
        $victim = User::factory()->create(['email' => 'victim@test.com']);
        CustomerProfile::factory()->create(['user_id' => $victim->id, 'company_name' => 'Victim Corp']);

        $attacker = User::factory()->create(['email' => 'attacker@test.com', 'role' => UserRole::USER]);
        $attackerToken = $attacker->createToken('hacker_token')->plainTextToken;

        $this->flushAuthGuard();

        // Attack 1: User tries to inject role ADMIN via profile update
        $this->withHeader('Authorization', "Bearer {$attackerToken}")
            ->putJson('/api/v1/profile', ['role' => 'ADMIN'])
            ->assertStatus(200);
        $this->assertEquals(UserRole::USER, $attacker->fresh()->role);

        $this->flushAuthGuard();

        // Attack 2: User tries to inspect another user's profile via admin endpoint
        $this->withHeader('Authorization', "Bearer {$attackerToken}")
            ->getJson("/api/v1/admin/users/{$victim->id}")
            ->assertStatus(403);

        $this->flushAuthGuard();

        // Attack 3: User tries to forge KYC verification
        $this->withHeader('Authorization', "Bearer {$attackerToken}")
            ->postJson('/api/v1/profile/verify', [
                'user_id' => $attacker->id,
                'verification_status' => 'VERIFIED',
            ])
            ->assertStatus(403);

        $this->flushAuthGuard();

        // Attack 4: User uses fake/corrupted token
        $this->withHeader('Authorization', 'Bearer invalid-corrupted-token-9999')
            ->getJson('/api/v1/profile')
            ->assertStatus(401)
            ->assertJson(['code' => 'UNAUTHENTICATED']);
    }
}
