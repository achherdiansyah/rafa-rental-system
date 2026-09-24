<?php

namespace App\Http\Requests\Equipment\Pricing;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class UpdateEquipmentPriceRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var User|null $user */
        $user = $this->user();

        return $user !== null && $user->can('manage-pricing-master');
    }

    public function rules(): array
    {
        return [
            'base_rate' => ['required', 'numeric', 'min:0'],
            'minimum_hours' => ['sometimes', 'required', 'integer', 'min:0'],
            'overtime_rate' => ['sometimes', 'required', 'numeric', 'min:0'],
            'effective_date' => ['sometimes', 'required', 'date'],
        ];
    }
}
