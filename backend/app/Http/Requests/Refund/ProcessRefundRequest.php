<?php

namespace App\Http\Requests\Refund;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class ProcessRefundRequest extends FormRequest
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
            'customer_bank_info' => ['required', 'string', 'max:500'],
            'transfer_reference' => ['nullable', 'string', 'max:255'],
        ];
    }
}
