<?php

namespace App\Http\Resources;

use App\Models\CartItem;
use App\Services\Equipment\EquipmentAvailabilityService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CartItem
 */
class CartItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $availService = app(EquipmentAvailabilityService::class);

        $availableCount = 0;
        if ($this->model && $this->start_date && $this->end_date) {
            $availableCount = $availService->getAvailableUnitsCount(
                $this->model,
                $this->start_date,
                $this->end_date
            );
        }

        $isAvailable = $availableCount >= $this->quantity;

        return [
            'id' => $this->id,
            'cart_id' => $this->cart_id,
            'equipment_model_id' => $this->equipment_model_id,
            'quantity' => $this->quantity,
            'is_all_in' => $this->is_all_in,
            'start_date' => $this->start_date?->toDateString(),
            'end_date' => $this->end_date?->toDateString(),
            'model' => new EquipmentModelResource($this->whenLoaded('model')),
            'availability' => [
                'is_available' => $isAvailable,
                'available_count' => $availableCount,
                'message' => $isAvailable 
                    ? 'Tersedia' 
                    : "Unit tidak tersedia untuk periode ini karena sudah dialokasikan pada booking lain yang sedang menunggu pembayaran.",
            ],
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
