<?php

namespace App\Http\Resources;

use App\Models\Booking;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Booking
 */
class BookingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'booking_code' => $this->booking_code,
            'user_id' => $this->user_id,
            'project_location_id' => $this->project_location_id,
            'status' => $this->status instanceof \BackedEnum ? $this->status->value : $this->status,
            'rejection_reason' => $this->rejection_reason,
            'total_amount' => (float) $this->total_amount,
            'project_location' => new ProjectLocationResource($this->whenLoaded('projectLocation')),
            'details' => BookingDetailResource::collection($this->whenLoaded('details')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
