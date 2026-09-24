<?php

namespace App\Actions\Equipment;

use App\Enums\EquipmentStatus;
use App\Models\EquipmentUnit;

class CreateEquipmentUnitAction
{
    /**
     * Create a new physical equipment unit.
     *
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data): EquipmentUnit
    {
        if (! isset($data['status'])) {
            $data['status'] = EquipmentStatus::AVAILABLE;
        }

        /** @var EquipmentUnit $unit */
        $unit = EquipmentUnit::create($data);
        $unit->load('model.type');

        return $unit;
    }
}
