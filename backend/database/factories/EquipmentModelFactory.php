<?php

namespace Database\Factories;

use App\Models\EquipmentModel;
use App\Models\EquipmentType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EquipmentModel>
 */
class EquipmentModelFactory extends Factory
{
    public function definition(): array
    {
        return [
            'equipment_type_id' => EquipmentType::factory(),
            'brand' => fake()->randomElement(['Komatsu', 'Caterpillar', 'Kobelco', 'Hitachi', 'Sumitomo']),
            'model_name' => fake()->unique()->lexify('PC???-').fake()->numerify('##'),
            'capacity_value' => fake()->randomElement([20.00, 30.00, 10.00, 15.50]),
            'capacity_unit' => 'Ton',
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
