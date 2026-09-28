<?php

namespace App\Actions\Bank;

use App\Exceptions\BusinessRuleException;
use App\Models\BankAccount;
use App\Support\AuditLogger;
use Illuminate\Support\Facades\DB;

class DeleteBankAccountAction
{
    /**
     * Delete a company bank account.
     *
     * Hard deletion is only allowed when the account has never been referenced
     * by a payment (FK is RESTRICT). Referenced accounts must be kept intact to
     * preserve financial history — admins should deactivate them instead.
     */
    public function execute(BankAccount $bankAccount): void
    {
        DB::transaction(function () use ($bankAccount) {
            $usedCount = $bankAccount->payments()->count();

            if ($usedCount > 0) {
                throw new BusinessRuleException(
                    "Rekening tidak dapat dihapus karena sudah dipakai {$usedCount} transaksi pembayaran. "
                    .'Nonaktifkan rekening ini untuk menyembunyikannya dari penyewa tanpa merusak histori keuangan.'
                );
            }

            $oldState = $bankAccount->only(['bank_name', 'account_number', 'account_name', 'is_active']);

            AuditLogger::log('BANK_ACCOUNT_DELETED', $bankAccount, $oldState);

            $bankAccount->delete();
        });
    }
}
