<?php

namespace Database\Factories;

use App\Models\Cart;
use App\Models\ProjectLocation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Cart>
 */
class CartFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'project_location_id' => null,
        ];
    }

    public function withLocation(): static
    {
        return $this->state(fn (array $attributes) => [
            'project_location_id' => ProjectLocation::factory()->create([
                'user_id' => $attributes['user_id'],
            ])->id,
        ]);
    }
}
