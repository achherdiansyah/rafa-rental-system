<?php

namespace App\Http\Requests\Equipment;

use App\Enums\EquipmentStatus;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class StoreEquipmentUnitRequest extends FormRequest
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
            'equipment_model_id' => ['required', 'integer', 'exists:equipment_models,id'],
            'serial_number' => ['required', 'string', 'max:100', 'unique:equipment_units,serial_number'],
            'plate_number' => ['nullable', 'string', 'max:30', 'unique:equipment_units,plate_number'],
            'status' => ['sometimes', new Enum(EquipmentStatus::class)],
            'last_hour_meter' => ['sometimes', 'numeric', 'min:0', 'max:99999999.99'],
            'year_of_make' => ['nullable', 'integer', 'min:1980', 'max:'.(date('Y') + 1)],
        ];
    }
}
