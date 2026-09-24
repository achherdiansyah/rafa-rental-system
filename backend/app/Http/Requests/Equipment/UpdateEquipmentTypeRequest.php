<?php

namespace App\Http\Requests\Equipment;

use App\Models\EquipmentType;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEquipmentTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var User|null $user */
        $user = $this->user();

        return $user !== null && $user->can('manage-equipment');
    }

    public function rules(): array
    {
        /** @var EquipmentType $type */
        $type = $this->route('type');
        $typeId = $type instanceof EquipmentType ? $type->id : $type;

        return [
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('equipment_types', 'name')->ignore($typeId),
            ],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
