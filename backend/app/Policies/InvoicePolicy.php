<?php

namespace App\Policies;

use App\Models\Invoice;
use App\Models\User;

class InvoicePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Invoice $invoice): bool
    {
        if ($user->isAdmin() || $user->isOwner()) {
            return true;
        }

        return (int) $user->id === (int) $invoice->booking?->user_id;
    }

    /**
     * Invoice creation & lifecycle management are operational (admin-only).
     * Financial history must never be deleted — no delete policy here.
     */
    public function manage(User $user, ?Invoice $invoice = null): bool
    {
        return $user->isAdmin();
    }
}
