<?php

namespace App\Http\Resources;

use App\Models\EquipmentPriceVersion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin EquipmentPriceVersion
 */
class EquipmentPriceVersionResource extends JsonResource
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
            'equipment_price_id' => $this->equipment_price_id,
            'old_base_rate' => (float) $this->old_base_rate,
            'new_base_rate' => (float) $this->new_base_rate,
            'changed_at' => $this->changed_at?->toIso8601String(),
            'changed_by' => $this->changed_by,
            'changed_by_user' => new UserResource($this->whenLoaded('changedByUser')),
        ];
    }
}
