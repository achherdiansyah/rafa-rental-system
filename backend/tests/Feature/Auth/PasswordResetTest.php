<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_request_password_reset_link(): void
    {
        Notification::fake();

        $user = User::factory()->create(['email' => 'budis@example.com']);

        $response = $this->postJson('/api/v1/auth/forgot-password', [
            'email' => 'budis@example.com',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $this->assertDatabaseHas('password_reset_tokens', [
            'email' => 'budis@example.com',
        ]);
    }

    public function test_request_reset_for_non_existent_email_returns_generic_success(): void
    {
        $response = $this->postJson('/api/v1/auth/forgot-password', [
            'email' => 'nonexistent@example.com',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);
    }

    public function test_user_can_reset_password_with_valid_token(): void
    {
        $user = User::factory()->create([
            'email' => 'resetuser@example.com',
            'password' => 'oldpassword123',
        ]);

        $token = Password::createToken($user);

        $response = $this->postJson('/api/v1/auth/reset-password', [
            'token' => $token,
            'email' => 'resetuser@example.com',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Kata sandi berhasil diperbarui. Silakan masuk menggunakan kata sandi baru Anda.',
            ]);

        // Verify password was updated in DB
        $this->assertTrue(Hash::check('newpassword123', $user->fresh()->password));
    }

    public function test_user_cannot_reset_password_with_invalid_token(): void
    {
        $user = User::factory()->create([
            'email' => 'resetuser@example.com',
            'password' => 'oldpassword123',
        ]);

        $response = $this->postJson('/api/v1/auth/reset-password', [
            'token' => 'invalid-token-12345',
            'email' => 'resetuser@example.com',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
            ]);

        $this->assertTrue(Hash::check('oldpassword123', $user->fresh()->password));
    }

    public function test_user_can_login_with_new_password_after_reset(): void
    {
        $user = User::factory()->create([
            'email' => 'loginreset@example.com',
            'password' => 'initialpassword123',
        ]);

        $token = Password::createToken($user);

        $this->postJson('/api/v1/auth/reset-password', [
            'token' => $token,
            'email' => 'loginreset@example.com',
            'password' => 'brandnewpassword123',
            'password_confirmation' => 'brandnewpassword123',
        ])->assertStatus(200);

        // Login with old password fails
        $this->postJson('/api/v1/auth/login', [
            'email' => 'loginreset@example.com',
            'password' => 'initialpassword123',
        ])->assertStatus(422);

        // Login with new password succeeds
        $this->postJson('/api/v1/auth/login', [
            'email' => 'loginreset@example.com',
            'password' => 'brandnewpassword123',
        ])->assertStatus(200);
    }

    public function test_existing_tokens_are_revoked_after_password_reset(): void
    {
        $user = User::factory()->create(['email' => 'revoketokens@example.com']);
        $oldToken = $user->createToken('active_session')->plainTextToken;

        $this->assertCount(1, $user->tokens);

        $token = Password::createToken($user);

        $this->postJson('/api/v1/auth/reset-password', [
            'token' => $token,
            'email' => 'revoketokens@example.com',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ])->assertStatus(200);

        // All previous tokens must be revoked for security
        $this->assertCount(0, $user->fresh()->tokens);

        // Request with old token should now fail with 401
        $this->withHeader('Authorization', "Bearer {$oldToken}")
            ->getJson('/api/v1/auth/me')
            ->assertStatus(401);
    }

    public function test_rate_limiting_protects_forgot_password_endpoint(): void
    {
        User::factory()->create(['email' => 'ratelimit@example.com']);

        // 5 requests allowed per minute on auth endpoints
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/forgot-password', ['email' => 'ratelimit@example.com'])
                ->assertStatus(200);
        }

        // 6th request triggers 429 Too Many Requests
        $this->postJson('/api/v1/auth/forgot-password', ['email' => 'ratelimit@example.com'])
            ->assertStatus(429)
            ->assertJson([
                'success' => false,
                'code' => 'RATE_LIMIT_EXCEEDED',
            ]);
    }
}
