<?php

namespace App\Policies;

use App\Enums\BookingStatus;
use App\Enums\UserRole;
use App\Models\Booking;
use App\Models\User;

class BookingPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Booking $booking): bool
    {
        if ($user->isAdmin() || $user->isOwner()) {
            return true;
        }

        if ($user->isSupervisor()) {
            return $booking->status === BookingStatus::PENDING_APPROVAL;
        }

        return (int) $user->id === (int) $booking->user_id;
    }

    public function create(User $user): bool
    {
        return $user->isUser();
    }

    public function submit(User $user, Booking $booking): bool
    {
        return (int) $user->id === (int) $booking->user_id;
    }

    /**
     * Correct (pre-validation) a DRAFT or Supervisor-revision booking.
     */
    public function update(User $user, Booking $booking): bool
    {
        if ($user->isAdmin() || $user->isOwner()) {
            return true;
        }

        if ((int) $user->id !== (int) $booking->user_id) {
            return false;
        }

        return $booking->status === BookingStatus::DRAFT
            || ($booking->status === BookingStatus::PENDING_APPROVAL && $booking->supervisor_decision === 'REVISION');
    }

    public function approve(User $user): bool
    {
        return $user->hasRole(UserRole::ADMIN, UserRole::OWNER);
    }

    public function reject(User $user): bool
    {
        return $user->hasRole(UserRole::ADMIN, UserRole::OWNER);
    }

    /**
     * Supervisor performs the initial validation (not final approval).
     */
    public function validate(User $user): bool
    {
        return $user->isSupervisor();
    }

    public function assignUnits(User $user): bool
    {
        return $user->isAdmin();
    }

    public function replaceUnit(User $user): bool
    {
        return $user->isAdmin();
    }

    public function extendDeadline(User $user): bool
    {
        return $user->hasRole(UserRole::ADMIN, UserRole::OWNER);
    }

    public function reschedule(User $user, Booking $booking): bool
    {
        if ($user->isAdmin() || $user->isOwner()) {
            return true;
        }

        return (int) $user->id === (int) $booking->user_id;
    }
}