<?php

namespace App\Http\Requests\Invoice;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class ExtendInvoiceDeadlineRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var User|null $user */
        $user = $this->user();

        return $user !== null && $user->isAdmin();
    }

    public function rules(): array
    {
        return [
            'hours' => ['sometimes', 'integer', 'min:0', 'max:168'],
        ];
    }
}
