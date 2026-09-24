<?php

namespace App\Http\Resources;

use App\Models\EquipmentPrice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin EquipmentPrice
 */
class EquipmentPriceResource extends JsonResource
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
            'equipment_model_id' => $this->equipment_model_id,
            'price_type' => $this->price_type,
            'is_all_in' => (bool) $this->is_all_in,
            'base_rate' => (float) $this->base_rate,
            'minimum_hours' => $this->minimum_hours,
            'overtime_rate' => (float) $this->overtime_rate,
            'effective_date' => $this->effective_date?->format('Y-m-d'),
            'model' => new EquipmentModelResource($this->whenLoaded('model')),
            'versions' => EquipmentPriceVersionResource::collection($this->whenLoaded('versions')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
