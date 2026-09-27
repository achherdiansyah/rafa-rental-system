<?php

namespace App\Http\Requests\Payment;

use App\Models\User;
use App\Support\FileSecurity;
use Illuminate\Foundation\Http\FormRequest;

class SubmitPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var User|null $user */
        $user = $this->user();

        return $user !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'gt:0', 'max:999999999999.99'],
            'payment_date' => ['required', 'date'],
            'bank_account_id' => ['required', 'integer', 'exists:bank_accounts,id'],
            'sender_name' => ['nullable', 'string', 'max:255'],
            'reference' => ['nullable', 'string', 'max:255'],
            'proof' => array_merge(FileSecurity::validationRules(true)),
        ];
    }
}
