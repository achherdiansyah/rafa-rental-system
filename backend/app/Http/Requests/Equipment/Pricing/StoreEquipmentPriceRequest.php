<?php

namespace App\Http\Requests\Equipment\Pricing;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class StoreEquipmentPriceRequest extends FormRequest
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
            'equipment_model_id' => ['required', 'integer', 'exists:equipment_models,id'],
            'price_type' => ['required', 'string', 'in:HOURLY,DAILY,MONTHLY,LUMP_SUM'],
            'is_all_in' => ['required', 'boolean'],
            'base_rate' => ['required', 'numeric', 'min:0'],
            'minimum_hours' => ['required', 'integer', 'min:0'],
            'overtime_rate' => ['required', 'numeric', 'min:0'],
            'effective_date' => ['required', 'date'],
        ];
    }
}
