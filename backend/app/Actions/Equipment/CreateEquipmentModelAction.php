<?php

namespace App\Actions\Equipment;

use App\Models\EquipmentModel;

class CreateEquipmentModelAction
{
    /**
     * Create a new equipment model.
     *
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data): EquipmentModel
    {
        /** @var EquipmentModel $model */
        $model = EquipmentModel::create($data);
        $model->load('type');

        return $model;
    }
}
