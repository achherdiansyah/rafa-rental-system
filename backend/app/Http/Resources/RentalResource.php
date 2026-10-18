<?php

namespace App\Http\Resources;

use App\Models\Rental;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Rental
 */
class RentalResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'booking_id' => $this->booking_id,
            'status' => $this->status instanceof \BackedEnum ? $this->status->value : $this->status,
            'started_at' => $this->started_at?->toISOString(),
            'completed_at' => $this->completed_at?->toISOString(),
            'booking' => new BookingResource($this->whenLoaded('booking')),
            'payment_hold' => $this->whenLoaded('activePaymentHold', function () {
                $hold = $this->activePaymentHold;
                if (! $hold) {
                    return null;
                }

                return [
                    'id' => $hold->id,
                    'status' => $hold->status,
                    'reason_code' => $hold->reason_code,
                    'trigger_invoice_id' => $hold->trigger_invoice_id,
                    'trigger_invoice_number' => $hold->triggerInvoice?->invoice_number,
                    'outstanding_amount' => (float) $hold->outstanding_amount,
                    'started_at' => $hold->started_at?->toIso8601String(),
                ];
            }),
            'details' => RentalDetailResource::collection($this->whenLoaded('details')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
