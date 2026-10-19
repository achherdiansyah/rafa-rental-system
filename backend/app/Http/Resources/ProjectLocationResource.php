<?php

namespace App\Http\Resources;

use App\Models\ProjectLocation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ProjectLocation
 */
class ProjectLocationResource extends JsonResource
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
            'project_name' => $this->project_name,
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
            'pic_name' => $this->pic_name,
            'pic_phone' => $this->pic_phone,
            'latitude' => $this->latitude !== null ? (float) $this->latitude : null,
            'longitude' => $this->longitude !== null ? (float) $this->longitude : null,
            'is_active' => (bool) $this->is_active,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}

