<?php

namespace App\Http\Resources;

use App\Models\RecommendationCriteria;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin RecommendationCriteria
 */
class RecommendationCriteriaResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'request_id' => $this->request_id,
            'project_type' => $this->project_type,
            'work_volume' => $this->work_volume !== null ? (float) $this->work_volume : null,
            'terrain_condition' => $this->terrain_condition,
            'depth_requirement' => $this->depth_requirement !== null ? (float) $this->depth_requirement : null,
            'reach_requirement' => $this->reach_requirement !== null ? (float) $this->reach_requirement : null,
            'load_capacity' => $this->load_capacity !== null ? (float) $this->load_capacity : null,
            'target_productivity' => $this->target_productivity,
            'location_access' => $this->location_access,
            'duration_days' => $this->duration_days,
            'start_date' => $this->start_date?->toDateString(),
            'end_date' => $this->end_date?->toDateString(),
            'budget_range' => $this->budget_range,
            'additional_params' => $this->additional_params,
        ];
    }
}
