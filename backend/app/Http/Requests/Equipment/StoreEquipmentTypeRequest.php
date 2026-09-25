<?php

namespace App\Http\Requests\Equipment;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class StoreEquipmentTypeRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255', 'unique:equipment_types,name'],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
