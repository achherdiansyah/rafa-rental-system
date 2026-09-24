<?php

namespace App\Http\Requests\Equipment;

use App\Models\EquipmentModel;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEquipmentModelRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var User|null $user */
        $user = $this->user();

        return $user !== null && $user->can('manage-equipment');
    }

    public function rules(): array
    {
        /** @var EquipmentModel $model */
        $model = $this->route('model');
        $modelId = $model instanceof EquipmentModel ? $model->id : $model;

        return [
            'equipment_type_id' => ['sometimes', 'required', 'integer', 'exists:equipment_types,id'],
            'brand' => ['sometimes', 'required', 'string', 'max:100'],
            'model_name' => [
                'sometimes',
                'required',
                'string',
                'max:150',
                Rule::unique('equipment_models', 'model_name')->ignore($modelId),
            ],
            'capacity_value' => ['sometimes', 'required', 'numeric', 'min:0.01', 'max:999999.99'],
            'capacity_unit' => ['sometimes', 'required', 'string', 'max:20'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
