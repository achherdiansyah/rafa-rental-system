<?php

namespace App\Actions\Equipment\Pricing;

use App\Models\EquipmentPrice;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class UpdateEquipmentPriceAction
{
    /**
     * Update an existing price rate and create a new immutable version record.
     *
     * @param  array<string, mixed>  $data
     */
    public function execute(EquipmentPrice $price, array $data, User $modifier): EquipmentPrice
    {
        return DB::transaction(function () use ($price, $data, $modifier) {
            $oldRate = $price->base_rate;
            $newRate = $data['base_rate'];

            // Update master price table
            $price->update($data);

            // Record version if base rate changed
            if ((float) $oldRate !== (float) $newRate) {
                $price->versions()->create([
                    'old_base_rate' => $oldRate,
                    'new_base_rate' => $newRate,
                    'changed_by' => $modifier->id,
                    'changed_at' => now(),
                ]);
            }

            $price->load(['model.type', 'versions.changedByUser']);

            return $price;
        });
    }
}
