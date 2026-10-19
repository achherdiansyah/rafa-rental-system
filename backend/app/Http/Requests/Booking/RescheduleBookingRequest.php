<?php

namespace App\Http\Requests\Booking;

use App\Support\RentalDateWindow;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class RescheduleBookingRequest extends FormRequest
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
            'new_start_date' => ['required', 'date', 'after_or_equal:'.RentalDateWindow::minStartDate()],
            'new_end_date' => ['required', 'date', 'after_or_equal:new_start_date'],
            'reason' => ['required', 'string', 'min:5'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'new_start_date.after_or_equal' => RentalDateWindow::START_MESSAGE,
            'new_end_date.after_or_equal' => RentalDateWindow::END_MESSAGE,
        ];
    }
}
