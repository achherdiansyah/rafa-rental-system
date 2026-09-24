<?php

namespace App\Actions\Equipment;

use App\Models\EquipmentUnit;

class UpdateEquipmentUnitAction
{
    /**
     * Update an existing physical equipment unit.
     *
     * @param  array<string, mixed>  $data
     */
    public function execute(EquipmentUnit $unit, array $data): EquipmentUnit
    {
        $unit->update($data);
        $unit->load('model.type');

        return $unit;
    }
}
