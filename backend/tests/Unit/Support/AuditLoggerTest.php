<?php

namespace Tests\Unit\Support;

use App\Enums\UserRole;
use App\Models\User;
use App\Support\AuditLogger;
use Tests\TestCase;

class AuditLoggerTest extends TestCase
{
    public function test_build_produces_complete_payload(): void
    {
        $user = new User(['name' => 'Admin Tester', 'role' => UserRole::ADMIN]);
        $user->id = 42;
        $this->actingAs($user);

        $entity = new User(['name' => 'Target']);
        $entity->id = 99;

        $payload = AuditLogger::build(
            action: 'BOOKING_APPROVED',
            entity: $entity,
            oldState: ['status' => 'PENDING_APPROVAL'],
            newState: ['status' => 'APPROVED'],
            metadata: ['reason' => 'Valid booking'],
        );

        $this->assertEquals(42, $payload['actor_id']);
        $this->assertEquals('ADMIN', $payload['actor_role']);
        $this->assertEquals('Admin Tester', $payload['actor_name']);
        $this->assertStringContainsString('User', $payload['entity_type']);
        $this->assertEquals('BOOKING_APPROVED', $payload['action']);
        $this->assertEquals(['status' => 'PENDING_APPROVAL'], $payload['old_state']);
        $this->assertEquals(['status' => 'APPROVED'], $payload['new_state']);
        $this->assertArrayHasKey('timestamp', $payload);
    }

    public function test_sanitize_redacts_sensitive_keys(): void
    {
        $data = [
            'email' => 'user@example.com',
            'password' => 'cleartext123',
            'token' => 'abc-secret-token',
            'nested' => [
                'api_key' => 'my-api-key',
                'name' => 'safe-value',
            ],
        ];

        $result = AuditLogger::sanitize($data);

        $this->assertEquals('user@example.com', $result['email']);
        $this->assertEquals('***REDACTED***', $result['password']);
        $this->assertEquals('***REDACTED***', $result['token']);
        $this->assertEquals('***REDACTED***', $result['nested']['api_key']);
        $this->assertEquals('safe-value', $result['nested']['name']);
    }

    public function test_build_handles_guest_actor(): void
    {
        $payload = AuditLogger::build('SYSTEM_CRON_EXPIRE', null, [], [], []);

        $this->assertNull($payload['actor_id']);
        $this->assertEquals('GUEST', $payload['actor_role']);
        $this->assertEquals('System/Guest', $payload['actor_name']);
        $this->assertNull($payload['entity_type']);
        $this->assertNull($payload['entity_id']);
    }
}
