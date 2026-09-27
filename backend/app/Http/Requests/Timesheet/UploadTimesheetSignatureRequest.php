<?php

namespace App\Http\Requests\Timesheet;

use App\Support\FileSecurity;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UploadTimesheetSignatureRequest extends FormRequest
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
            'signature' => FileSecurity::validationRules(true),
        ];
    }
}
