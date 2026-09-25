<?php

namespace App\Http\Requests\Equipment;

use App\Enums\EquipmentStatus;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class UpdateEquipmentUnitStatusRequest extends FormRequest
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
            'status' => ['required', new Enum(EquipmentStatus::class)],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
