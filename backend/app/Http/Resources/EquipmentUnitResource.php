<?php

namespace App\Http\Resources;

use App\Models\EquipmentUnit;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin EquipmentUnit
 */
class EquipmentUnitResource extends JsonResource
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
            'serial_number' => $this->serial_number,
            'plate_number' => $this->plate_number,
            'status' => $this->status->value,
            'last_hour_meter' => (float) $this->last_hour_meter,
            'year_of_make' => $this->year_of_make,
            'model' => new EquipmentModelResource($this->whenLoaded('model')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
