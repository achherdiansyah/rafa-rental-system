<?php

namespace App\Http\Resources;

use App\Models\EquipmentModel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin EquipmentModel
 */
class EquipmentModelResource extends JsonResource
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
            'equipment_type_id' => $this->equipment_type_id,
            'brand' => $this->brand,
            'model_name' => $this->model_name,
            'capacity_value' => (float) $this->capacity_value,
            'capacity_unit' => $this->capacity_unit,
            'is_active' => (bool) $this->is_active,
            'type' => new EquipmentTypeResource($this->whenLoaded('type')),
            'prices' => $this->whenLoaded('prices'),
            'attachments' => AttachmentResource::collection($this->whenLoaded('attachments')),
            'units_count' => $this->whenCounted('units'),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
