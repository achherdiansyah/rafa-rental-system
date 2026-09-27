<?php

namespace App\Http\Resources;

use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Invoice
 */
class InvoiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'invoice_number' => $this->invoice_number,
            'invoice_type' => $this->invoice_type instanceof \BackedEnum ? $this->invoice_type->value : $this->invoice_type,
            'booking_id' => $this->booking_id,
            'status' => $this->status instanceof \BackedEnum ? $this->status->value : $this->status,
            'issued_at' => $this->issued_at?->toIso8601String(),
            'due_at' => $this->due_at?->toIso8601String(),
            'subtotal' => (float) $this->subtotal,
            'tax_total' => (float) $this->tax_total,
            'grand_total' => (float) $this->grand_total,
            'paid_amount' => (float) $this->paid_amount,
            'balance_amount' => $this->balance(),
            'booking' => $this->whenLoaded('booking', function () {
                $booking = $this->booking;

                return [
                    'booking_code' => $booking?->booking_code,
                    'project' => $booking?->projectLocation ? [
                        'id' => $booking->projectLocation->id,
                        'project_name' => $booking->projectLocation->project_name,
                        'city' => $booking->projectLocation->city,
                    ] : null,
                ];
            }),
            'details' => InvoiceDetailResource::collection($this->whenLoaded('details')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
