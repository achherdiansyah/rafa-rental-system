<?php

namespace Database\Factories;

use App\Models\EquipmentPool;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EquipmentPool>
 */
class EquipmentPoolFactory extends Factory
{
    protected $model = EquipmentPool::class;

    public function definition(): array
    {
        return [
            'name' => 'Pool '.fake()->city(),
            'address' => fake()->address(),
            'city' => fake()->city(),
            'latitude' => -6.2,
            'longitude' => 106.8,
            'is_active' => true,
        ];
    }
}