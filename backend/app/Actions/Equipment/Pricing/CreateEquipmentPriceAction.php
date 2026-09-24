<?php

namespace App\Actions\Equipment\Pricing;

use App\Models\EquipmentPrice;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CreateEquipmentPriceAction
{
    /**
     * Create a new master price entry and record its initial version.
     *
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data, User $creator): EquipmentPrice
    {
        return DB::transaction(function () use ($data, $creator) {
            /** @var EquipmentPrice $price */
            $price = EquipmentPrice::create([
                'equipment_model_id' => $data['equipment_model_id'],
                'price_type' => $data['price_type'],
                'is_all_in' => $data['is_all_in'],
                'base_rate' => $data['base_rate'],
                'minimum_hours' => $data['minimum_hours'] ?? 0,
                'overtime_rate' => $data['overtime_rate'] ?? 0.00,
                'effective_date' => $data['effective_date'],
            ]);

            // Create initial version record
            $price->versions()->create([
                'old_base_rate' => 0.00,
                'new_base_rate' => $data['base_rate'],
                'changed_by' => $creator->id,
                'changed_at' => now(),
            ]);

            $price->load(['model.type', 'versions.changedByUser']);

            return $price;
        });
    }
}
