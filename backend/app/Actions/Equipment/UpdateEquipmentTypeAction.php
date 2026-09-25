<?php

namespace App\Actions\Equipment;

use App\Models\EquipmentType;

class UpdateEquipmentTypeAction
{
    /**
     * Update an existing equipment type.
     *
     * @param  array<string, mixed>  $data
     */
    public function execute(EquipmentType $type, array $data): EquipmentType
    {
        $type->update($data);

        return $type;
    }
}
