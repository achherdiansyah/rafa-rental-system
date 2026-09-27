<?php

namespace App\Policies;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;

class PaymentPolicy
{
    /**
     * A payment is submitted by the booking owner (or staff) against their own invoice.
     */
    public function create(User $user, Invoice $invoice): bool
    {
        if ($user->isAdmin() || $user->isOwner()) {
            return true;
        }

        return (int) $user->id === (int) $invoice->booking?->user_id;
    }

    public function view(User $user, Payment $payment): bool
    {
        if ($user->isAdmin() || $user->isOwner()) {
            return true;
        }

        return (int) $user->id === (int) $payment->invoice?->booking?->user_id;
    }

    /**
     * Payment verification (approve/reject) is admin-only — implemented in 10D.
     * No delete: rejected payments are never removed.
     */
    public function manage(User $user): bool
    {
        return $user->isAdmin();
    }
}
