<?php

namespace Database\Factories;

use App\Models\RecommendationRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RecommendationRequest>
 */
class RecommendationRequestFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'status' => 'PROCESSED',
        ];
    }
}
