<?php

namespace App\Policies;

use App\Models\Refund;
use App\Models\User;

class RefundPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Refund $refund): bool
    {
        if ($user->isAdmin() || $user->isOwner()) {
            return true;
        }

        return (int) $user->id === (int) $refund->invoice?->booking?->user_id;
    }

    /**
     * Refund approval is OWNER-only per Phase 1 permission matrix.
     */
    public function approve(User $user, ?Refund $refund = null): bool
    {
        return $user->isOwner();
    }

    /**
     * Refund execution is admin (staff finance). History is never deleted —
     * there is deliberately no delete ability here.
     */
    public function manage(User $user, ?Refund $refund = null): bool
    {
        return $user->isAdmin();
    }
}
