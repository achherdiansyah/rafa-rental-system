<?php

namespace App\Http\Requests\Equipment;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class StoreEquipmentModelRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var User|null $user */
        $user = $this->user();

        return $user !== null && $user->can('manage-equipment');
    }

    public function rules(): array
    {
        return [
            'equipment_type_id' => ['required', 'integer', 'exists:equipment_types,id'],
            'brand' => ['required', 'string', 'max:100'],
            'model_name' => ['required', 'string', 'max:150', 'unique:equipment_models,model_name'],
            'capacity_value' => ['required', 'numeric', 'min:0.01', 'max:999999.99'],
            'capacity_unit' => ['required', 'string', 'max:20'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
