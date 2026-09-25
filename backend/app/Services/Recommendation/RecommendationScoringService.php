<?php

namespace App\Services\Recommendation;

use App\Models\EquipmentModel;
use App\Models\RecommendationCriteria;
use App\Services\Equipment\EquipmentAvailabilityService;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class RecommendationScoringService
{
    public function __construct(
        protected EquipmentAvailabilityService $availabilityService
    ) {}

    /**
     * Compute recommendation match scores, rank models deterministically, and generate explanation.
     * Excludes models that have zero availability during the requested period.
     *
     * @return Collection<int, array{model: EquipmentModel, score: float, lowest_rate: float, model_id: int, reasoning: string, breakdown: array<string, mixed>}>
     */
    public function evaluate(RecommendationCriteria $criteria): Collection
    {
        $criteriaConfig = config('recommendation.criteria', []);
        $activeCriteria = array_filter($criteriaConfig, fn ($c) => ($c['is_active'] ?? true) === true);

        // Normalize weights so the sum always equals 1.0 (100%)
        $totalWeight = array_sum(array_column($activeCriteria, 'weight'));
        if ($totalWeight <= 0) {
            $totalWeight = 1.0;
        }

        $minThreshold = (float) config('recommendation.min_score_threshold', 50.00);
        $maxResults = (int) config('recommendation.default_max_results', 5);

        // Retrieve active equipment models with type and active prices
        $models = EquipmentModel::where('is_active', true)
            ->with(['type', 'prices'])
            ->get();

        // 1. Resolve period for availability check
        $startDate = $criteria->start_date ? Carbon::parse($criteria->start_date) : null;
        $endDate = $criteria->end_date ? Carbon::parse($criteria->end_date) : null;

        if ($startDate && ! $endDate && $criteria->duration_days) {
            $endDate = $startDate->copy()->addDays($criteria->duration_days - 1);
        }

        // 2. Fetch bulk availability map for all models
        $modelIds = $models->pluck('id')->all();
        $availabilityMap = $this->availabilityService->getAvailabilityMap($modelIds, $startDate, $endDate);

        $scored = collect();

        foreach ($models as $model) {
            $availableUnits = (int) $availabilityMap->get($model->id, 0);

            // Business Rule: Exclude equipment that is physically unavailable
            if ($availableUnits <= 0) {
                continue;
            }

            $breakdown = [];
            $totalWeightedScore = 0.0;

            foreach ($activeCriteria as $key => $config) {
                $rawScore = $this->calculateCriteriaScore($key, $model, $criteria);
                $normalizedWeight = (float) ($config['weight'] / $totalWeight);
                $contribution = round($rawScore * $normalizedWeight, 2);

                $totalWeightedScore += $rawScore * $normalizedWeight;

                $breakdown[$key] = [
                    'name' => $config['name'] ?? $key,
                    'code' => $config['code'] ?? strtoupper($key),
                    'weight' => round($normalizedWeight, 4),
                    'score' => round($rawScore, 2),
                    'contribution' => $contribution,
                ];
            }

            $finalScore = min(100.00, max(0.00, round($totalWeightedScore, 2)));

            // Compute lowest active base rate for deterministic tie-breaking
            $lowestPrice = $model->prices->min('base_rate');
            $lowestRate = $lowestPrice !== null ? (float) $lowestPrice : PHP_FLOAT_MAX;

            $reasoning = $this->generateReasoningText($model, $criteria, $breakdown, $finalScore, $availableUnits);

            $scored->push([
                'model' => $model,
                'score' => $finalScore,
                'lowest_rate' => $lowestRate,
                'model_id' => $model->id,
                'available_units' => $availableUnits,
                'reasoning' => $reasoning,
                'breakdown' => $breakdown,
            ]);
        }

        // Filter by threshold, sort deterministically, and take max results
        // Sort order: 1) score DESC, 2) lowest_rate ASC, 3) model_id ASC
        return $scored
            ->filter(fn ($item) => $item['score'] >= $minThreshold)
            ->sort(function ($a, $b) {
                // 1. Score descending
                if ($a['score'] !== $b['score']) {
                    return $b['score'] <=> $a['score'];
                }

                // 2. Lowest price ascending (more economical wins tie)
                if ($a['lowest_rate'] !== $b['lowest_rate']) {
                    return $a['lowest_rate'] <=> $b['lowest_rate'];
                }

                // 3. Model ID ascending (deterministic fallback)
                return $a['model_id'] <=> $b['model_id'];
            })
            ->take($maxResults)
            ->values();
    }

    private function calculateCriteriaScore(string $key, EquipmentModel $model, RecommendationCriteria $criteria): float
    {
        return match ($key) {
            'capacity_match' => $this->calculateCapacityScore($model, $criteria),
            'terrain_suitability' => $this->calculateTerrainScore($model, $criteria),
            'project_suitability' => $this->calculateProjectScore($model, $criteria),
            'price_suitability' => $this->calculatePriceScore($model, $criteria),
            default => 75.0,
        };
    }

    private function calculateCapacityScore(EquipmentModel $model, RecommendationCriteria $criteria): float
    {
        if (! $criteria->load_capacity || (float) $criteria->load_capacity <= 0) {
            return 85.0; // Standard default when capacity is not specified
        }

        $modelCap = (float) $model->capacity_value;
        $reqCap = (float) $criteria->load_capacity;

        if ($modelCap >= $reqCap) {
            $ratio = $modelCap / $reqCap;
            if ($ratio <= 1.25) {
                return 100.0; // Perfect fit (within 25% margin)
            } elseif ($ratio <= 1.75) {
                return 92.0; // Good fit (slight overcapacity)
            } elseif ($ratio <= 2.50) {
                return 80.0; // Moderate overcapacity
            } else {
                return 65.0; // Excessive overcapacity
            }
        } else {
            // Deficit penalty: proportional to how short the unit is
            $ratio = $modelCap / $reqCap;

            return max(20.0, round($ratio * 75.0, 2));
        }
    }

    private function calculateTerrainScore(EquipmentModel $model, RecommendationCriteria $criteria): float
    {
        $terrain = strtolower(trim((string) $criteria->terrain_condition));
        $typeName = strtolower($model->type?->name ?? '');

        if (str_contains($terrain, 'lumpur') || str_contains($terrain, 'rawa') || str_contains($terrain, 'basah')) {
            if (str_contains($typeName, 'excavator')) {
                return 98.0;
            }
            if (str_contains($typeName, 'bulldozer')) {
                return 82.0;
            }

            return 50.0;
        }

        if (str_contains($terrain, 'keras') || str_contains($terrain, 'bebatuan') || str_contains($terrain, 'tambang')) {
            if (str_contains($typeName, 'bulldozer') || str_contains($typeName, 'excavator')) {
                return 96.0;
            }

            return 70.0;
        }

        if (str_contains($terrain, 'aspal') || str_contains($terrain, 'datar') || str_contains($terrain, 'gravel')) {
            if (str_contains($typeName, 'grader') || str_contains($typeName, 'roller') || str_contains($typeName, 'loader')) {
                return 98.0;
            }

            return 85.0;
        }

        return 80.0; // Baseline standard terrain
    }

    private function calculateProjectScore(EquipmentModel $model, RecommendationCriteria $criteria): float
    {
        $project = strtolower(trim((string) $criteria->project_type));
        $typeName = strtolower($model->type?->name ?? '');

        if (str_contains($project, 'galian') || str_contains($project, 'drainase') || str_contains($project, 'pondasi')) {
            return str_contains($typeName, 'excavator') ? 100.0 : 60.0;
        }

        if (str_contains($project, 'jalan') || str_contains($project, 'perataan') || str_contains($project, 'land clearing') || str_contains($project, 'timbunan')) {
            if (str_contains($typeName, 'bulldozer') || str_contains($typeName, 'grader')) {
                return 100.0;
            }

            return 70.0;
        }

        return 80.0; // General construction match
    }

    private function calculatePriceScore(EquipmentModel $model, RecommendationCriteria $criteria): float
    {
        // Models with established price rates have full commercial readiness
        if ($model->prices->isEmpty()) {
            return 55.0;
        }

        return 95.0;
    }

    /**
     * @param  array<string, array{name: string, score: float, contribution: float}>  $breakdown
     */
    private function generateReasoningText(
        EquipmentModel $model,
        RecommendationCriteria $criteria,
        array $breakdown,
        float $finalScore,
        int $availableUnits = 0
    ): string {
        $reasons = [];

        $reasons[] = "Model {$model->brand} {$model->model_name} (Kapasitas {$model->capacity_value} {$model->capacity_unit}) memperoleh total skor kesesuaian {$finalScore}%.";

        if ($criteria->load_capacity && isset($breakdown['capacity_match'])) {
            $capScore = $breakdown['capacity_match']['score'];
            if ($capScore >= 90.0) {
                $reasons[] = "Kapasitas muat unit sangat efisien dan memadai untuk beban target {$criteria->load_capacity} {$model->capacity_unit}.";
            } elseif ($capScore < 70.0) {
                $reasons[] = "Kapasitas unit berada di bawah target beban {$criteria->load_capacity} {$model->capacity_unit}, disarankan evaluasi beban berkala.";
            }
        }

        if ($criteria->terrain_condition && isset($breakdown['terrain_suitability'])) {
            $reasons[] = "Konfigurasi traksi sesuai untuk karakteristik medan '{$criteria->terrain_condition}'.";
        }

        if ($criteria->project_type && isset($breakdown['project_suitability'])) {
            $reasons[] = "Fungsi operasional tipe {$model->type?->name} relevan dengan jenis pekerjaan '{$criteria->project_type}'.";
        }

        if ($criteria->depth_requirement) {
            $reasons[] = "Mendukung kedalaman operasional hingga {$criteria->depth_requirement} meter.";
        }

        if ($criteria->reach_requirement) {
            $reasons[] = "Mendukung jangkauan boom/arm hingga {$criteria->reach_requirement} meter.";
        }

        return implode(' ', $reasons);
    }
}
