<?php

namespace Database\Factories;

use App\Models\ProjectLocation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProjectLocation>
 */
class ProjectLocationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'project_name' => fake()->words(3, true).' Project',
            'address' => fake()->streetAddress(),
            'city' => fake()->city(),
            'pic_name' => fake()->name(),
            'pic_phone' => fake()->numerify('08##########'),
            'latitude' => fake()->latitude(-7.5, -6.0),
            'longitude' => fake()->longitude(106.5, 108.0),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
