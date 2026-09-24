<?php

namespace Database\Factories;

use App\Models\EquipmentModel;
use App\Models\EquipmentPrice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EquipmentPrice>
 */
class EquipmentPriceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'equipment_model_id' => EquipmentModel::factory(),
            'price_type' => 'HOURLY',
            'is_all_in' => false,
            'base_rate' => 250000.00,
            'minimum_hours' => 8,
            'overtime_rate' => 300000.00,
            'effective_date' => now()->toDateString(),
        ];
    }

    public function allIn(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_all_in' => true,
            'base_rate' => 350000.00,
        ]);
    }
}
