<?php

namespace App\Actions\Equipment;

use App\Models\EquipmentModel;

class UpdateEquipmentModelAction
{
    /**
     * Update an existing equipment model.
     *
     * @param  array<string, mixed>  $data
     */
    public function execute(EquipmentModel $model, array $data): EquipmentModel
    {
        $model->update($data);
        $model->load('type');

        return $model;
    }
}
