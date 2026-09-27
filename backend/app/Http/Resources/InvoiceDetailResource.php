<?php

namespace App\Http\Resources;

use App\Models\InvoiceDetail;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin InvoiceDetail
 */
class InvoiceDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'description' => $this->description,
            'unit_price' => (float) $this->unit_price,
            'quantity' => (float) $this->quantity,
            'subtotal' => (float) $this->subtotal,
        ];
    }
}
