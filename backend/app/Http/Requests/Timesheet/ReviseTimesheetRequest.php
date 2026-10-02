<?php

namespace App\Http\Requests\Timesheet;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ReviseTimesheetRequest extends FormRequest
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
            'start_time' => ['nullable', 'string', 'regex:/^\d{1,2}:\d{2}$/'],
            'end_time' => ['nullable', 'string', 'regex:/^\d{1,2}:\d{2}$/'],
            'start_hm' => ['sometimes', 'numeric', 'min:0'],
            'end_hm' => ['sometimes', 'numeric', 'min:0'],
            'break_minutes' => ['sometimes', 'integer', 'min:0', 'max:1440'],
            'standby_hours' => ['sometimes', 'numeric', 'min:0'],
            'breakdown_hours' => ['sometimes', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'reason' => ['required', 'string', 'min:5'],
        ];
    }
}
