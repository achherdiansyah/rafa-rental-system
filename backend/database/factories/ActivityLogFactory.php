<?php

namespace Database\Factories;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ActivityLog>
 */
class ActivityLogFactory extends Factory
{
    public function definition(): array
    {
        return [
            'log_name' => 'audit',
            'description' => 'User logged in',
            'subject_type' => User::class,
            'subject_id' => 1,
            'causer_type' => User::class,
            'causer_id' => 1,
            'properties' => ['ip' => '127.0.0.1'],
            'ip_address' => '127.0.0.1',
            'created_at' => now(),
        ];
    }
}
