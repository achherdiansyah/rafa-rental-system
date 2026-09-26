<?php

namespace App\Http\Resources;

use App\Models\BookingDetail;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin BookingDetail
 */
class BookingDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'booking_id' => $this->booking_id,
            'equipment_model_id' => $this->equipment_model_id,
            'quantity' => $this->quantity,
            'start_date' => $this->start_date?->toDateString(),
            'end_date' => $this->end_date?->toDateString(),
            'is_all_in' => (bool) $this->is_all_in,
            'rental_rate_snapshot' => (float) $this->rental_rate_snapshot,
            'subtotal' => (float) $this->subtotal,
            'model' => new EquipmentModelResource($this->whenLoaded('model')),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
