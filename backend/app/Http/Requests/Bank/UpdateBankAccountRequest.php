<?php

namespace App\Http\Requests\Bank;

use App\Models\BankAccount;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBankAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var User|null $user */
        $user = $this->user();

        return $user !== null && $user->can('manage-bank-accounts');
    }

    public function rules(): array
    {
        /** @var BankAccount $bankAccount */
        $bankAccount = $this->route('bankAccount');
        $accountId = $bankAccount instanceof BankAccount ? $bankAccount->id : $bankAccount;

        return [
            'bank_name' => ['sometimes', 'required', 'string', 'max:100'],
            'account_number' => [
                'sometimes',
                'required',
                'string',
                'max:100',
                Rule::unique('bank_accounts', 'account_number')->ignore($accountId),
            ],
            'account_name' => ['sometimes', 'required', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
