<?php

namespace App\Http\Resources;

use App\Models\EquipmentPool;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin EquipmentPool
 */
class EquipmentPoolResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'address' => $this->address,
            'city' => $this->city,
            'province_code' => $this->province_code,
            'province_name' => $this->province_name,
            'regency_code' => $this->regency_code,
            'regency_name' => $this->regency_name,
            'district_code' => $this->district_code,
            'district_name' => $this->district_name,
            'village_code' => $this->village_code,
            'village_name' => $this->village_name,
            'latitude' => $this->latitude !== null ? (float) $this->latitude : null,
            'longitude' => $this->longitude !== null ? (float) $this->longitude : null,
            'is_active' => (bool) $this->is_active,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
