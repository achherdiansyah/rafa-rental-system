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
            'unit_assignments' => $this->whenLoaded('unitAssignments', function () {
                return $this->unitAssignments->map(fn ($assignment) => [
                    'id' => $assignment->id,
                    'equipment_unit_id' => $assignment->equipment_unit_id,
                    'status' => $assignment->status instanceof \BackedEnum ? $assignment->status->value : $assignment->status,
                    'is_current' => (bool) $assignment->is_current,
                    'replaced_reason' => $assignment->replaced_reason,
                    'unit' => $assignment->unit ? [
                        'id' => $assignment->unit->id,
                        'serial_number' => $assignment->unit->serial_number,
                        'plate_number' => $assignment->unit->plate_number,
                        'status' => $assignment->unit->status instanceof \BackedEnum ? $assignment->unit->status->value : $assignment->unit->status,
                    ] : null,
                ]);
            }),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
