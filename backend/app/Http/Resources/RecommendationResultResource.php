<?php

namespace App\Http\Resources;

use App\Models\RecommendationResult;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin RecommendationResult
 */
class RecommendationResultResource extends JsonResource
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
            'equipment_model_id' => $this->equipment_model_id,
            'rank' => $this->rank ?? null,
            'match_score' => (float) $this->match_score,
            'score_breakdown' => $this->score_breakdown,
            'available_units' => $this->available_units,
            'reasoning_text' => $this->reasoning_text,
            'model' => new EquipmentModelResource($this->whenLoaded('model')),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
