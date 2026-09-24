<?php

namespace Tests\Feature\Profile;

use App\Enums\UserRole;
use App\Models\CustomerProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_view_own_profile(): void
    {
        $user = User::factory()->create(['name' => 'Budi Santoso']);
        CustomerProfile::factory()->create([
            'user_id' => $user->id,
            'company_name' => 'PT Sumber Makmur',
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/profile');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $user->id,
                    'name' => 'Budi Santoso',
                    'customer_profile' => [
                        'company_name' => 'PT Sumber Makmur',
                    ],
                ],
            ]);
    }

    public function test_unauthenticated_user_cannot_view_profile(): void
    {
        $response = $this->getJson('/api/v1/profile');

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'code' => 'UNAUTHENTICATED',
            ]);
    }

    public function test_user_can_update_own_profile_details(): void
    {
        $user = User::factory()->create([
            'name' => 'Nama Awal',
            'phone_number' => '081111111111',
        ]);
        CustomerProfile::factory()->create([
            'user_id' => $user->id,
            'company_name' => 'PT Awal',
            'identity_number' => '3201000000000001',
        ]);

        Sanctum::actingAs($user);

        $payload = [
            'name' => 'Nama Baru',
            'phone_number' => '082222222222',
            'company_name' => 'PT Sukses Baru',
            'identity_number' => '3201000000000002',
            'address' => 'Alamat Kantor Baru No. 12',
        ];

        $response = $this->putJson('/api/v1/profile', $payload);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Profil berhasil diperbarui.',
                'data' => [
                    'name' => 'Nama Baru',
                    'phone_number' => '082222222222',
                    'customer_profile' => [
                        'company_name' => 'PT Sukses Baru',
                        'identity_number' => '3201000000000002',
                        'address' => 'Alamat Kantor Baru No. 12',
                    ],
                ],
            ]);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Nama Baru',
            'phone_number' => '082222222222',
        ]);

        $this->assertDatabaseHas('customer_profiles', [
            'user_id' => $user->id,
            'company_name' => 'PT Sukses Baru',
        ]);
    }

    public function test_user_cannot_change_role_via_profile_update(): void
    {
        $user = User::factory()->create(['role' => UserRole::USER]);

        Sanctum::actingAs($user);

        $response = $this->putJson('/api/v1/profile', [
            'name' => 'Trying Escalation',
            'role' => 'ADMIN',
        ]);

        $response->assertStatus(200);

        $this->assertEquals(UserRole::USER, $user->fresh()->role);
    }

    public function test_user_cannot_update_with_duplicate_phone_number(): void
    {
        User::factory()->create(['phone_number' => '089999999999']);
        $user = User::factory()->create(['phone_number' => '081111111111']);

        Sanctum::actingAs($user);

        $response = $this->putJson('/api/v1/profile', [
            'phone_number' => '089999999999',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['phone_number']);
    }

    public function test_admin_can_verify_customer_profile(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = User::factory()->create();
        CustomerProfile::factory()->create([
            'user_id' => $customer->id,
            'verification_status' => 'UNVERIFIED',
        ]);

        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/v1/profile/verify', [
            'user_id' => $customer->id,
            'verification_status' => 'VERIFIED',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'user_id' => $customer->id,
                    'verification_status' => 'VERIFIED',
                ],
            ]);

        $this->assertDatabaseHas('customer_profiles', [
            'user_id' => $customer->id,
            'verification_status' => 'VERIFIED',
        ]);
    }

    public function test_regular_user_cannot_verify_customer_profile(): void
    {
        $user = User::factory()->create(['role' => UserRole::USER]);
        $target = User::factory()->create();

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/profile/verify', [
            'user_id' => $target->id,
            'verification_status' => 'VERIFIED',
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'code' => 'FORBIDDEN_ACTION',
            ]);
    }

    public function test_admin_can_list_and_view_any_user_profile(): void
    {
        $admin = User::factory()->admin()->create();
        $targetUser = User::factory()->create(['name' => 'Target Customer']);

        Sanctum::actingAs($admin);

        // List
        $listResponse = $this->getJson('/api/v1/admin/users');
        $listResponse->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => ['id', 'name', 'email', 'role'],
                ],
                'meta' => ['total', 'current_page'],
            ]);

        // View single
        $showResponse = $this->getJson("/api/v1/admin/users/{$targetUser->id}");
        $showResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $targetUser->id,
                    'name' => 'Target Customer',
                ],
            ]);
    }

    public function test_regular_user_cannot_access_admin_users_list(): void
    {
        $user = User::factory()->create(['role' => UserRole::USER]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/admin/users');

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'code' => 'FORBIDDEN_ACTION',
            ]);
    }
}
