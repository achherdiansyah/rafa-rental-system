<?php

namespace Database\Factories;

use App\Enums\EquipmentStatus;
use App\Models\EquipmentModel;
use App\Models\EquipmentUnit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EquipmentUnit>
 */
class EquipmentUnitFactory extends Factory
{
    public function definition(): array
    {
        return [
            'equipment_model_id' => EquipmentModel::factory(),
            'serial_number' => 'SN-'.fake()->unique()->numerify('#####-####'),
            'plate_number' => 'B '.fake()->unique()->numerify('####').' '.fake()->lexify('??'),
            'status' => EquipmentStatus::AVAILABLE->value,
            'last_hour_meter' => fake()->randomFloat(2, 100, 5000),
            'year_of_make' => fake()->numberBetween(2018, 2024),
        ];
    }

    public function assigned(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => EquipmentStatus::ASSIGNED->value,
        ]);
    }

    public function maintenance(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => EquipmentStatus::MAINTENANCE->value,
        ]);
    }
}
