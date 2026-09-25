<?php

namespace App\Actions\Recommendation;

use App\Enums\RecommendationStatus;
use App\Models\RecommendationRequest;
use App\Models\User;
use App\Services\Recommendation\RecommendationScoringService;
use Illuminate\Support\Facades\DB;

class CreateRecommendationAction
{
    public function __construct(
        protected RecommendationScoringService $scoringService
    ) {}

    /**
     * Create a recommendation request, persist input criteria, evaluate scoring, and record results.
     *
     * @param  array<string, mixed>  $data
     */
    public function execute(User $user, array $data): RecommendationRequest
    {
        return DB::transaction(function () use ($user, $data) {
            /** @var RecommendationRequest $recRequest */
            $recRequest = RecommendationRequest::create([
                'user_id' => $user->id,
                'status' => RecommendationStatus::PROCESSED,
            ]);

            // Save input criteria
            $criteria = $recRequest->criteria()->create([
                'project_type' => $data['project_type'],
                'work_volume' => $data['work_volume'] ?? null,
                'terrain_condition' => $data['terrain_condition'],
                'depth_requirement' => $data['depth_requirement'] ?? null,
                'reach_requirement' => $data['reach_requirement'] ?? null,
                'load_capacity' => $data['load_capacity'] ?? null,
                'target_productivity' => $data['target_productivity'] ?? null,
                'location_access' => $data['location_access'] ?? null,
                'duration_days' => $data['duration_days'] ?? null,
                'budget_range' => $data['budget_range'] ?? null,
                'additional_params' => $data['additional_params'] ?? null,
            ]);

            // Run rule-based scoring engine
            $results = $this->scoringService->evaluate($criteria);

            // Persist recommendation results
            foreach ($results as $item) {
                $recRequest->results()->create([
                    'equipment_model_id' => $item['model']->id,
                    'match_score' => $item['score'],
                    'reasoning_text' => $item['reasoning'],
                ]);
            }

            $recRequest->load([
                'criteria',
                'results.model' => function ($q) {
                    $q->with(['type', 'prices', 'attachments'])->withCount('units');
                },
                'user',
            ]);

            return $recRequest;
        });
    }
}
