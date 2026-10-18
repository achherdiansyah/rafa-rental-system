<?php

namespace App\Http\Requests\Timesheet;

use App\Support\FileSecurity;
use Illuminate\Foundation\Http\FormRequest;

class UploadTimesheetPaperRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'paper_form' => array_merge(FileSecurity::validationRules(true), ['image']),
        ];
    }
}
