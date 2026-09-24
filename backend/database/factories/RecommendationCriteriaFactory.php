<?php

namespace Database\Factories;

use App\Models\RecommendationCriteria;
use App\Models\RecommendationRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RecommendationCriteria>
 */
class RecommendationCriteriaFactory extends Factory
{
    public function definition(): array
    {
        return [
            'request_id' => RecommendationRequest::factory(),
            'project_type' => fake()->randomElement(['Pertambangan', 'Konstruksi Jalan', 'Galian Basah', 'Land Clearing']),
            'terrain_condition' => fake()->randomElement(['Lumpur / Basah', 'Tanah Keras / Bebatuan', 'Pasir / Berbatu', 'Aspal / Kering']),
            'load_capacity' => fake()->randomElement([20.00, 30.00, 15.00]),
            'budget_range' => 'Rp 5.000.000 - Rp 10.000.000 / hari',
        ];
    }
}
