<?php

namespace App\Http\Requests\Equipment;

use App\Models\EquipmentUnit;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEquipmentUnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var User|null $user */
        $user = $this->user();

        return $user !== null && $user->can('manage-equipment');
    }

    public function rules(): array
    {
        /** @var EquipmentUnit $unit */
        $unit = $this->route('unit');
        $unitId = $unit instanceof EquipmentUnit ? $unit->id : $unit;

        return [
            'equipment_model_id' => ['sometimes', 'required', 'integer', 'exists:equipment_models,id'],
            'serial_number' => [
                'sometimes',
                'required',
                'string',
                'max:100',
                Rule::unique('equipment_units', 'serial_number')->ignore($unitId),
            ],
            'plate_number' => [
                'nullable',
                'string',
                'max:30',
                Rule::unique('equipment_units', 'plate_number')->ignore($unitId),
            ],
            'last_hour_meter' => ['sometimes', 'numeric', 'min:0', 'max:99999999.99'],
            'year_of_make' => ['nullable', 'integer', 'min:1980', 'max:'.(date('Y') + 1)],
        ];
    }
}
