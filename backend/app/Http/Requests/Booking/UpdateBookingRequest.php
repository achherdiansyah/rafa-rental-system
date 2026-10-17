<?php

namespace App\Http\Requests\Booking;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'project_location_id' => ['nullable', 'integer', 'exists:project_locations,id'],
            'details' => ['nullable', 'array'],
            'details.*.id' => ['required', 'integer'],
            'details.*.start_date' => ['required', 'date'],
            'details.*.end_date' => ['required', 'date', 'after_or_equal:details.*.start_date'],
        ];
    }
}
