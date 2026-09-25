<?php

namespace App\Http\Resources;

use App\Models\RecommendationRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin RecommendationRequest
 */
class RecommendationRequestResource extends JsonResource
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
            'user_id' => $this->user_id,
            'status' => $this->status instanceof \BackedEnum ? $this->status->value : $this->status,
            'criteria' => new RecommendationCriteriaResource($this->whenLoaded('criteria')),
            'results' => RecommendationResultResource::collection($this->whenLoaded('results')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
