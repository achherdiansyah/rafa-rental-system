<?php

namespace Database\Factories;

use App\Models\EquipmentPrice;
use App\Models\EquipmentPriceVersion;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EquipmentPriceVersion>
 */
class EquipmentPriceVersionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'equipment_price_id' => EquipmentPrice::factory(),
            'old_base_rate' => 200000.00,
            'new_base_rate' => 250000.00,
            'changed_at' => now(),
            'changed_by' => User::factory(),
        ];
    }
}
