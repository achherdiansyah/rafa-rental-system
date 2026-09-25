<?php

namespace App\Actions\Bank;

use App\Models\BankAccount;

class CreateBankAccountAction
{
    /**
     * Create a new company bank account.
     *
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data): BankAccount
    {
        if (! isset($data['is_active'])) {
            $data['is_active'] = true;
        }

        return BankAccount::create($data);
    }
}
