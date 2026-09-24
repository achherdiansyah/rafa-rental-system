<?php

namespace Database\Factories;

use App\Models\EquipmentType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EquipmentType>
 */
class EquipmentTypeFactory extends Factory
{
    public function definition(): array
    {
        $types = ['Excavator', 'Bulldozer', 'Motor Grader', 'Vibro Compactor', 'Wheel Loader'];

        return [
            'name' => fake()->unique()->randomElement($types).' '.fake()->numerify('Kelas##'),
            'description' => fake()->sentence(),
        ];
    }
}
