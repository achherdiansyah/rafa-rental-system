<?php

namespace App\Services\Recommendation;

use App\Models\EquipmentModel;
use App\Models\RecommendationCriteria;
use Illuminate\Support\Collection;

class RecommendationScoringService
{
    /**
     * Compute recommendation match scores and generate reasoning text for active equipment models.
     *
     * @return Collection<int, array{model: EquipmentModel, score: float, reasoning: string}>
     */
    public function evaluate(RecommendationCriteria $criteria): Collection
    {
        $weights = config('recommendation.weights', [
            'capacity_match' => 0.40,
            'terrain_suitability' => 0.30,
            'project_suitability' => 0.20,
            'price_suitability' => 0.10,
        ]);

        $minThreshold = (float) config('recommendation.min_score_threshold', 50.00);
        $maxResults = (int) config('recommendation.default_max_results', 5);

        // Retrieve active equipment models with type and prices
        $models = EquipmentModel::where('is_active', true)
            ->with(['type', 'prices'])
            ->get();

        $scored = $models->map(function (EquipmentModel $model) use ($criteria, $weights) {
            $capacityScore = $this->calculateCapacityScore($model, $criteria);
            $terrainScore = $this->calculateTerrainScore($model, $criteria);
            $projectScore = $this->calculateProjectScore($model, $criteria);
            $priceScore = $this->calculatePriceScore($model, $criteria);

            $finalScore = round(
                ($capacityScore * $weights['capacity_match']) +
                ($terrainScore * $weights['terrain_suitability']) +
                ($projectScore * $weights['project_suitability']) +
                ($priceScore * $weights['price_suitability']),
                2
            );

            $reasoning = $this->generateReasoningText(
                $model,
                $criteria,
                $capacityScore,
                $terrainScore,
                $projectScore,
                $finalScore
            );

            return [
                'model' => $model,
                'score' => $finalScore,
                'reasoning' => $reasoning,
            ];
        });

        // Filter by threshold and order by highest score
        return $scored
            ->filter(fn ($item) => $item['score'] >= $minThreshold)
            ->sortByDesc('score')
            ->take($maxResults)
            ->values();
    }

    private function calculateCapacityScore(EquipmentModel $model, RecommendationCriteria $criteria): float
    {
        if (! $criteria->load_capacity || $criteria->load_capacity <= 0) {
            return 85.0; // Default baseline if capacity not explicitly specified
        }

        $modelCap = (float) $model->capacity_value;
        $reqCap = (float) $criteria->load_capacity;

        if ($modelCap >= $reqCap) {
            // Optimal if capacity matches or slightly exceeds requirement
            $ratio = $modelCap / $reqCap;
            if ($ratio <= 1.5) {
                return 100.0;
            } elseif ($ratio <= 2.5) {
                return 90.0; // Slightly overcapacity
            } else {
                return 75.0; // Significant overcapacity
            }
        } else {
            // Under-capacity penalty
            $deficitRatio = $modelCap / $reqCap;

            return max(20.0, round($deficitRatio * 80.0, 2));
        }
    }

    private function calculateTerrainScore(EquipmentModel $model, RecommendationCriteria $criteria): float
    {
        $terrain = strtolower(trim((string) $criteria->terrain_condition));
        $typeName = strtolower($model->type?->name ?? '');

        if (str_contains($terrain, 'lumpur') || str_contains($terrain, 'rawa')) {
            if (str_contains($typeName, 'excavator')) {
                return 95.0;
            }
            if (str_contains($typeName, 'bulldozer')) {
                return 80.0;
            }

            return 50.0;
        }

        if (str_contains($terrain, 'keras') || str_contains($terrain, 'bebatuan') || str_contains($terrain, 'tambang')) {
            if (str_contains($typeName, 'bulldozer') || str_contains($typeName, 'excavator')) {
                return 95.0;
            }

            return 70.0;
        }

        if (str_contains($terrain, 'aspal') || str_contains($terrain, 'datar') || str_contains($terrain, 'gravel')) {
            if (str_contains($typeName, 'grader') || str_contains($typeName, 'roller')) {
                return 95.0;
            }

            return 85.0;
        }

        return 80.0; // Default standard terrain suitability
    }

    private function calculateProjectScore(EquipmentModel $model, RecommendationCriteria $criteria): float
    {
        $project = strtolower(trim((string) $criteria->project_type));
        $typeName = strtolower($model->type?->name ?? '');

        if (str_contains($project, 'galian') || str_contains($project, 'drainase') || str_contains($project, 'pondasi')) {
            return str_contains($typeName, 'excavator') ? 100.0 : 60.0;
        }

        if (str_contains($project, 'jalan') || str_contains($project, 'perataan') || str_contains($project, 'land clearing')) {
            return (str_contains($typeName, 'bulldozer') || str_contains($typeName, 'grader')) ? 100.0 : 70.0;
        }

        return 80.0; // Generic baseline project match
    }

    private function calculatePriceScore(EquipmentModel $model, RecommendationCriteria $criteria): float
    {
        // Active price presence ensures commercial readiness
        $hasActivePrice = $model->prices->isNotEmpty();

        return $hasActivePrice ? 90.0 : 60.0;
    }

    private function generateReasoningText(
        EquipmentModel $model,
        RecommendationCriteria $criteria,
        float $capScore,
        float $terrainScore,
        float $projScore,
        float $finalScore
    ): string {
        $reasons = [];

        $reasons[] = "Model {$model->brand} {$model->model_name} (Kapasitas {$model->capacity_value} {$model->capacity_unit}) memiliki skor kecocokan total {$finalScore}%.";

        if ($criteria->load_capacity) {
            if ($capScore >= 90.0) {
                $reasons[] = "Kapasitas unit sangat optimal untuk beban target {$criteria->load_capacity}.";
            } elseif ($capScore < 70.0) {
                $reasons[] = "Kapasitas unit berada di bawah target {$criteria->load_capacity}, disarankan evaluasi beban.";
            }
        }

        if ($criteria->terrain_condition) {
            $reasons[] = "Sistem roda/crawler cocok untuk karakteristik medan '{$criteria->terrain_condition}'.";
        }

        if ($criteria->project_type) {
            $reasons[] = "Spesifikasi tipe {$model->type?->name} relevan dengan jenis proyek '{$criteria->project_type}'.";
        }

        return implode(' ', $reasons);
    }
}
