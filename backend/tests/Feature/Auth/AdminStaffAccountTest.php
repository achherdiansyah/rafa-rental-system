<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminStaffAccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_supervisor_and_operator_with_temporary_password(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        foreach ([UserRole::SUPERVISOR, UserRole::OPERATOR] as $role) {
            $res = $this->postJson('/api/v1/admin/users', [
                'name' => $role->value.' User',
                'email' => strtolower($role->value).'@rafa.test',
                'role' => $role->value,
                'is_active' => true,
            ])->assertCreated();

            $this->assertNotEmpty($res->json('data.temporary_password'));
            $this->assertTrue($res->json('data.must_change_password'));

            $user = User::where('email', strtolower($role->value).'@rafa.test')->firstOrFail();
            $this->assertSame($role, $user->role);
            $this->assertTrue($user->must_change_password);
        }
    }

    public function test_new_staff_can_login_but_must_change_password_before_operational_access(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
        $password = $this->postJson('/api/v1/admin/users', [
            'name' => 'Operator Baru',
            'email' => 'operator.baru@rafa.test',
            'role' => 'OPERATOR',
        ])->json('data.temporary_password');

        $login = $this->postJson('/api/v1/auth/login', [
            'email' => 'operator.baru@rafa.test',
            'password' => $password,
        ])->assertOk()->assertJsonPath('data.user.role', 'OPERATOR');

        Sanctum::actingAs(User::where('email', 'operator.baru@rafa.test')->firstOrFail());
        $this->getJson('/api/v1/operator/dashboard')
            ->assertStatus(423)
            ->assertJsonPath('code', 'PASSWORD_CHANGE_REQUIRED');

        $this->putJson('/api/v1/profile/password', [
            'current_password' => $password,
            'password' => 'new-secure-password',
            'password_confirmation' => 'new-secure-password',
        ])->assertOk();

        $this->getJson('/api/v1/operator/dashboard')->assertOk();
        $this->assertFalse(User::where('email', 'operator.baru@rafa.test')->firstOrFail()->must_change_password);
    }

    public function test_admin_role_and_owner_cannot_create_staff_or_use_admin_only_creation(): void
    {
        $owner = User::factory()->owner()->create();
        Sanctum::actingAs($owner);

        $this->postJson('/api/v1/admin/users', [
            'name' => 'Nope',
            'email' => 'nope@rafa.test',
            'role' => 'SUPERVISOR',
        ])->assertForbidden();

        Sanctum::actingAs(User::factory()->admin()->create());
        $this->postJson('/api/v1/admin/users', [
            'name' => 'Admin Baru',
            'email' => 'admin.baru@rafa.test',
            'role' => 'ADMIN',
        ])->assertStatus(422)->assertJsonValidationErrors(['role']);
    }
}
