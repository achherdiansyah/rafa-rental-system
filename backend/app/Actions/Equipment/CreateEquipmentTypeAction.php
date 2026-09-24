<?php

namespace App\Actions\Equipment;

use App\Models\EquipmentType;

class CreateEquipmentTypeAction
{
    /**
     * Create a new equipment type.
     *
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data): EquipmentType
    {
        return EquipmentType::create($data);
    }
}
