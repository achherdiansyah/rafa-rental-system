<?php

namespace App\Http\Requests\Cart;

use App\Support\RentalDateWindow;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class AddCartItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'equipment_model_id' => ['required', 'integer', 'exists:equipment_models,id'],
            'quantity' => ['required', 'integer', 'min:1'],
            'is_all_in' => ['required', 'boolean'],
            'start_date' => ['required', 'date', 'after_or_equal:'.RentalDateWindow::minStartDate()],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'project_location_id' => ['nullable', 'integer', 'exists:project_locations,id'], // optional on first add
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'start_date.after_or_equal' => RentalDateWindow::START_MESSAGE,
            'end_date.after_or_equal' => RentalDateWindow::END_MESSAGE,
        ];
    }
}
