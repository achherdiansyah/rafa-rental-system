<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Request;

final class AuditLogger
{
    /**
     * Build and format an audit log entry.
     *
     * @param  string  $action  Name of the action (e.g. 'BOOKING_APPROVED', 'PAYMENT_VERIFIED')
     * @param  Model|null  $entity  The target Eloquent model
     * @param  array<string, mixed>  $oldState  Previous state values
     * @param  array<string, mixed>  $newState  New state values
     * @param  array<string, mixed>  $metadata  Additional operational context
     * @return array<string, mixed>
     */
    public static function build(
        string $action,
        ?Model $entity = null,
        array $oldState = [],
        array $newState = [],
        array $metadata = []
    ): array {
        /** @var User|null $actor */
        $actor = Auth::user();

        return [
            'actor_id' => $actor?->id,
            'actor_role' => $actor?->role?->value ?? 'GUEST',
            'actor_name' => $actor?->name ?? 'System/Guest',
            'entity_type' => $entity ? get_class($entity) : null,
            'entity_id' => $entity?->getKey(),
            'action' => $action,
            'old_state' => self::sanitize($oldState),
            'new_state' => self::sanitize($newState),
            'metadata' => self::sanitize($metadata),
            'ip_address' => Request::ip() ?? '127.0.0.1',
            'user_agent' => Request::userAgent() ?? 'CLI/Unknown',
            'timestamp' => now()->toIso8601String(),
        ];
    }

    /**
     * Log an audit event to the application audit log stream.
     *
     * @param  array<string, mixed>  $oldState
     * @param  array<string, mixed>  $newState
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>
     */
    public static function log(
        string $action,
        ?Model $entity = null,
        array $oldState = [],
        array $newState = [],
        array $metadata = []
    ): array {
        $payload = self::build($action, $entity, $oldState, $newState, $metadata);

        Log::info(sprintf('AUDIT [%s]: Entity=%s#%s by Actor=%s',
            $action,
            $payload['entity_type'] ?? 'None',
            $payload['entity_id'] ?? '0',
            $payload['actor_name']
        ), $payload);

        return $payload;
    }

    /**
     * Mask sensitive keys to prevent credential leakage.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function sanitize(array $data): array
    {
        $sensitiveKeys = [
            'password',
            'password_confirmation',
            'secret',
            'token',
            'bearer_token',
            'authorization',
            'remember_token',
            'access_token',
            'api_key',
            'card_number',
            'cvv',
        ];

        $sanitized = [];

        foreach ($data as $key => $value) {
            if (is_string($key) && in_array(strtolower($key), $sensitiveKeys, true)) {
                $sanitized[$key] = '***REDACTED***';
            } elseif (is_array($value)) {
                $sanitized[$key] = self::sanitize($value);
            } else {
                $sanitized[$key] = $value;
            }
        }

        return $sanitized;
    }
}
