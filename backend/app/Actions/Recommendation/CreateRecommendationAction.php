<?php

namespace App\Actions\Recommendation;

use App\Enums\RecommendationStatus;
use App\Models\ProjectLocation;
use App\Models\RecommendationRequest;
use App\Models\User;
use App\Services\Recommendation\RecommendationScoringService;
use Illuminate\Auth\Access\AuthorizationException;
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
            // A recommendation may be tied to one of the user's own project
            // locations (medan/kapasitas tetap berasal dari input kriteria, bukan
            // disimpulkan dari alamat/koordinat).
            $projectLocationId = $data['project_location_id'] ?? null;
            if ($projectLocationId !== null && ! ($user->isAdmin() || $user->isOwner())) {
                $owned = ProjectLocation::whereKey((int) $projectLocationId)
                    ->where('user_id', $user->id)
                    ->exists();
                if (! $owned) {
                    throw new AuthorizationException('Lokasi proyek bukan milik akun Anda.');
                }
            }

            /** @var RecommendationRequest $recRequest */
            $recRequest = RecommendationRequest::create([
                'user_id' => $user->id,
                'project_location_id' => $projectLocationId,
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
                'start_date' => $data['start_date'] ?? null,
                'end_date' => $data['end_date'] ?? null,
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
                    'score_breakdown' => $item['breakdown'],
                    'available_units' => $item['available_units'] ?? null,
                    'reasoning_text' => $item['reasoning'],
                ]);
            }

            $recRequest->load([
                'criteria',
                'projectLocation',
                'results.model' => function ($q) {
                    $q->with(['type', 'prices', 'attachments'])->withCount('units');
                },
                'user',
            ]);

            return $recRequest;
        });
    }
}
