<?php

namespace Database\Factories;

use App\Models\EquipmentModel;
use App\Models\RecommendationRequest;
use App\Models\RecommendationResult;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RecommendationResult>
 */
class RecommendationResultFactory extends Factory
{
    public function definition(): array
    {
        return [
            'request_id' => RecommendationRequest::factory(),
            'equipment_model_id' => EquipmentModel::factory(),
            'match_score' => fake()->randomFloat(2, 75.00, 99.50),
            'reasoning_text' => 'Kapasitas dan spesifikasi track sesuai untuk medan galian basah.',
        ];
    }
}
