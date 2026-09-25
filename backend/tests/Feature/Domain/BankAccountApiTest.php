<?php

namespace Tests\Feature\Domain;

use App\Enums\UserRole;
use App\Models\BankAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BankAccountApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_only_view_active_bank_accounts(): void
    {
        $user = User::factory()->create(['role' => UserRole::USER]);
        Sanctum::actingAs($user);

        BankAccount::factory()->create([
            'bank_name' => 'BCA',
            'account_number' => '1111111111',
            'is_active' => true,
        ]);

        BankAccount::factory()->create([
            'bank_name' => 'Mandiri Inactive',
            'account_number' => '2222222222',
            'is_active' => false,
        ]);

        $response = $this->getJson('/api/v1/bank-accounts');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => ['id', 'bank_name', 'account_number', 'account_name', 'is_active'],
                ],
            ]);

        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('1111111111', $response->json('data.0.account_number'));
    }

    public function test_user_cannot_view_inactive_bank_account_detail(): void
    {
        $user = User::factory()->create(['role' => UserRole::USER]);
        Sanctum::actingAs($user);

        $inactiveAccount = BankAccount::factory()->create([
            'is_active' => false,
        ]);

        $response = $this->getJson("/api/v1/bank-accounts/{$inactiveAccount->id}");

        $response->assertStatus(404);
    }

    public function test_admin_and_owner_can_view_all_bank_accounts_including_inactive(): void
    {
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);

        BankAccount::factory()->create(['is_active' => true]);
        BankAccount::factory()->create(['is_active' => false]);

        $response = $this->getJson('/api/v1/bank-accounts');

        $response->assertStatus(200);
        $this->assertCount(2, $response->json('data'));
    }

    public function test_admin_can_create_bank_account(): void
    {
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);

        $payload = [
            'bank_name' => 'Bank Central Asia (BCA)',
            'account_number' => '8888999900',
            'account_name' => 'PT RAFA RENTAL NUSANTARA',
            'is_active' => true,
        ];

        $response = $this->postJson('/api/v1/bank-accounts', $payload);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'bank_name' => 'Bank Central Asia (BCA)',
                    'account_number' => '8888999900',
                    'account_name' => 'PT RAFA RENTAL NUSANTARA',
                    'is_active' => true,
                ],
            ]);

        $this->assertDatabaseHas('bank_accounts', [
            'account_number' => '8888999900',
        ]);
    }

    public function test_user_cannot_create_bank_account(): void
    {
        $user = User::factory()->create(['role' => UserRole::USER]);
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/bank-accounts', [
            'bank_name' => 'BCA',
            'account_number' => '12345678',
            'account_name' => 'Hacker Account',
        ]);

        $response->assertStatus(403);
    }

    public function test_cannot_create_duplicate_account_number(): void
    {
        $admin = User::factory()->admin()->create();
        BankAccount::factory()->create(['account_number' => 'DUPLICATE123']);

        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/v1/bank-accounts', [
            'bank_name' => 'Mandiri',
            'account_number' => 'DUPLICATE123',
            'account_name' => 'PT RAFA',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['account_number']);
    }

    public function test_admin_can_update_and_deactivate_bank_account(): void
    {
        $admin = User::factory()->admin()->create();
        $account = BankAccount::factory()->create(['is_active' => true]);

        Sanctum::actingAs($admin);

        $response = $this->putJson("/api/v1/bank-accounts/{$account->id}", [
            'bank_name' => 'BCA Syariah',
            'is_active' => false,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'bank_name' => 'BCA Syariah',
                    'is_active' => false,
                ],
            ]);

        $this->assertFalse($account->fresh()->is_active);
    }

    public function test_user_cannot_update_bank_account(): void
    {
        $user = User::factory()->create(['role' => UserRole::USER]);
        $account = BankAccount::factory()->create();

        Sanctum::actingAs($user);

        $response = $this->putJson("/api/v1/bank-accounts/{$account->id}", [
            'bank_name' => 'Hacked Bank',
            'is_active' => false,
        ]);

        $response->assertStatus(403);
    }

    public function test_owner_can_manage_bank_accounts(): void
    {
        $owner = User::factory()->owner()->create();
        Sanctum::actingAs($owner);

        // Owner can list inactive
        BankAccount::factory()->create(['is_active' => false]);
        $this->getJson('/api/v1/bank-accounts')->assertStatus(200);

        // Owner can create
        $createRes = $this->postJson('/api/v1/bank-accounts', [
            'bank_name' => 'Owner Bank',
            'account_number' => 'OWNER-123',
            'account_name' => 'OWNER PT',
            'is_active' => true,
        ]);
        $createRes->assertStatus(201);
        $accountId = $createRes->json('data.id');

        // Owner can update
        $this->putJson("/api/v1/bank-accounts/{$accountId}", [
            'bank_name' => 'Owner Bank Updated',
        ])->assertStatus(200);
    }
}
