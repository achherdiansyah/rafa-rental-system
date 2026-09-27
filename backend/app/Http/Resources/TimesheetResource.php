<?php

namespace App\Http\Resources;

use App\Models\Timesheet;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Timesheet
 */
class TimesheetResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'rental_detail_id' => $this->rental_detail_id,
            'report_date' => $this->report_date?->toDateString(),
            'start_hm' => (float) $this->start_hm,
            'end_hm' => (float) $this->end_hm,
            'break_minutes' => $this->break_minutes,
            'total_work_hours' => (float) $this->total_work_hours,
            'standby_hours' => (float) $this->standby_hours,
            'breakdown_hours' => (float) $this->breakdown_hours,
            'operator_name' => $this->operator_name,
            'notes' => $this->notes,
            'signature_reference' => $this->signature_reference,
            'status' => $this->status instanceof \BackedEnum ? $this->status->value : $this->status,
            'rental' => $this->whenLoaded('rentalDetail', function () {
                $rental = $this->rentalDetail?->rental;
                $unit = $this->rentalDetail?->assignment?->unit;

                return [
                    'rental_id' => $rental?->id,
                    'booking_code' => $rental?->booking?->booking_code,
                    'project_name' => $rental?->booking?->projectLocation?->project_name,
                    'unit_id' => $unit?->id,
                    'unit_serial' => $unit?->serial_number,
                    'unit_plate' => $unit?->plate_number,
                ];
            }),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
