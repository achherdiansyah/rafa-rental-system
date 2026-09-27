<?php

namespace App\Http\Requests\Timesheet;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreTimesheetRequest extends FormRequest
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
            'rental_detail_id' => ['required', 'integer', 'exists:rental_details,id'],
            'report_date' => ['required', 'date', 'before_or_equal:today'],
            'start_hm' => ['required', 'numeric', 'min:0', 'max:999999.99'],
            'end_hm' => ['required', 'numeric', 'gt:start_hm', 'max:999999.99'],
            'break_minutes' => ['nullable', 'integer', 'min:0', 'max:1440'],
            'standby_hours' => ['nullable', 'numeric', 'min:0', 'max:24'],
            'breakdown_hours' => ['nullable', 'numeric', 'min:0', 'max:24'],
            'operator_name' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'signature_reference' => ['nullable', 'string', 'max:255'],
        ];
    }
}
