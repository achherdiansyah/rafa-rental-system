<?php

namespace Database\Factories;

use App\Models\CustomerProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomerProfile>
 */
class CustomerProfileFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'company_name' => fake()->company(),
            'identity_type' => 'KTP',
            'identity_number' => fake()->unique()->numerify('3201############'),
            'address' => fake()->address(),
            'verification_status' => 'UNVERIFIED',
        ];
    }

    public function verified(): static
    {
        return $this->state(fn (array $attributes) => [
            'verification_status' => 'VERIFIED',
        ]);
    }
}
