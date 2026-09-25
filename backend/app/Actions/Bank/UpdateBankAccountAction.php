<?php

namespace App\Actions\Bank;

use App\Models\BankAccount;

class UpdateBankAccountAction
{
    /**
     * Update an existing company bank account.
     *
     * @param  array<string, mixed>  $data
     */
    public function execute(BankAccount $bankAccount, array $data): BankAccount
    {
        $bankAccount->update($data);

        return $bankAccount;
    }
}
