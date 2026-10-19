<?php

namespace App\Http\Requests\Cart;

use App\Support\RentalDateWindow;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCartItemRequest extends FormRequest
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
            'quantity' => ['sometimes', 'integer', 'min:1'],
            'is_all_in' => ['sometimes', 'boolean'],
            'start_date' => ['sometimes', 'date', 'after_or_equal:'.RentalDateWindow::minStartDate()],
            'end_date' => ['sometimes', 'date', 'after_or_equal:start_date'],
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
