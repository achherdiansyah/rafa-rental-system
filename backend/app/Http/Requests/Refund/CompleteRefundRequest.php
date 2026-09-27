<?php

namespace App\Http\Requests\Refund;

use App\Models\User;
use App\Support\FileSecurity;
use Illuminate\Foundation\Http\FormRequest;

class CompleteRefundRequest extends FormRequest
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
            'transfer_reference' => ['nullable', 'string', 'max:255'],
            'proof' => array_merge(FileSecurity::validationRules(true)),
        ];
    }
}
