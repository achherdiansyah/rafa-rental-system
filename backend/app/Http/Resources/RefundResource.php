<?php

namespace App\Http\Resources;

use App\Models\Refund;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Refund
 */
class RefundResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'invoice_id' => $this->invoice_id,
            'source' => $this->source instanceof \BackedEnum ? $this->source->value : $this->source,
            'amount' => (float) $this->amount,
            'reason' => $this->reason,
            'customer_bank_info' => $this->customer_bank_info,
            'transfer_reference' => $this->transfer_reference,
            'status' => $this->status instanceof \BackedEnum ? $this->status->value : $this->status,
            'failure_reason' => $this->failure_reason,
            'processed_at' => $this->processed_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'proof' => $this->whenLoaded('attachments', function () {
                $proof = $this->proof();

                return $proof ? new AttachmentResource($proof) : null;
            }),
            'invoice' => $this->whenLoaded('invoice', function () {
                return [
                    'invoice_number' => $this->invoice?->invoice_number,
                    'booking_code' => $this->invoice?->booking?->booking_code,
                    'project' => $this->invoice?->booking?->projectLocation ? [
                        'project_name' => $this->invoice->booking->projectLocation->project_name,
                        'city' => $this->invoice->booking->projectLocation->city,
                    ] : null,
                ];
            }),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
