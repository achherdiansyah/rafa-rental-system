<?php

namespace App\Http\Resources;

use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Payment
 */
class PaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'invoice_id' => $this->invoice_id,
            'status' => $this->status instanceof \BackedEnum ? $this->status->value : $this->status,
            'amount' => (float) $this->amount,
            'payment_date' => $this->payment_date?->toIso8601String(),
            'sender_name' => $this->sender_name,
            'reference' => $this->reference,
            'rejection_reason' => $this->rejection_reason,
            'proof' => $this->whenLoaded('attachments', function () {
                $proof = $this->proof();

                return $proof ? new AttachmentResource($proof) : null;
            }),
            'invoice' => $this->whenLoaded('invoice', function () {
                return [
                    'invoice_number' => $this->invoice?->invoice_number,
                    'status' => $this->invoice?->status instanceof \BackedEnum ? $this->invoice->status->value : $this->invoice?->status,
                    'booking_code' => $this->invoice?->booking?->booking_code,
                ];
            }),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
