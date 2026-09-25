<?php

namespace App\Http\Requests\Pricing;

use App\DTOs\Pricing\LineItemPricingInput;
use Illuminate\Foundation\Http\FormRequest;

class CalculatePricingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Public calculation / simulation endpoint
    }

    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1'],
            'items.*.equipment_model_id' => ['required', 'integer', 'exists:equipment_models,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:50'],
            'items.*.start_date' => ['required', 'date'],
            'items.*.end_date' => ['required', 'date', 'after_or_equal:items.*.start_date'],
            'items.*.is_all_in' => ['required', 'boolean'],
            'items.*.mob_rate_per_unit' => ['nullable', 'numeric', 'min:0'],
            'items.*.demob_rate_per_unit' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    /**
     * @return LineItemPricingInput[]
     */
    public function toLineItemInputs(): array
    {
        $items = $this->validated('items');

        return array_map(fn (array $item) => LineItemPricingInput::fromArray($item), $items);
    }
}
