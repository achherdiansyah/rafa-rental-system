<?php

namespace App\Http\Requests\Rental;

use App\Actions\Rental\InspectRentalAction;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReadyRentalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'result' => ['required', 'string', Rule::in(InspectRentalAction::RESULTS)],
            'condition_notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
