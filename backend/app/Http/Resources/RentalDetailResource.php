<?php

namespace App\Http\Resources;

use App\Models\RentalDetail;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin RentalDetail
 */
class RentalDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'rental_id' => $this->rental_id,
            'assignment_id' => $this->assignment_id,
            'status' => $this->status instanceof \BackedEnum ? $this->status->value : $this->status,
            'check_in_hm' => $this->check_in_hm !== null ? (float) $this->check_in_hm : null,
            'check_out_hm' => $this->check_out_hm !== null ? (float) $this->check_out_hm : null,
            'condition_notes' => $this->condition_notes,
            'unit' => $this->whenLoaded('assignment', function () {
                $unit = $this->assignment?->unit;

                return $unit ? [
                    'id' => $unit->id,
                    'serial_number' => $unit->serial_number,
                    'plate_number' => $unit->plate_number,
                    'status' => $unit->status instanceof \BackedEnum ? $unit->status->value : $unit->status,
                ] : null;
            }),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
