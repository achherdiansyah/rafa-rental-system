<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Booking;
use App\Models\User;

class BookingPolicy
{
    /**
     * Determine whether the user can view any bookings.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the booking.
     */
    public function view(User $user, Booking $booking): bool
    {
        if ($user->isAdmin() || $user->isOwner()) {
            return true;
        }

        return (int) $user->id === (int) $booking->user_id;
    }

    /**
     * Determine whether the user can create a booking.
     */
    public function create(User $user): bool
    {
        return $user->isUser();
    }

    /**
     * Determine whether the user can submit the booking (DRAFT -> PENDING_APPROVAL).
     */
    public function submit(User $user, Booking $booking): bool
    {
        return (int) $user->id === (int) $booking->user_id;
    }

    /**
     * Determine whether the user can approve the booking (PENDING_APPROVAL -> APPROVED).
     */
    public function approve(User $user): bool
    {
        return $user->hasRole(UserRole::ADMIN, UserRole::OWNER);
    }

    /**
     * Determine whether the user can reject the booking (PENDING_APPROVAL -> REJECTED).
     */
    public function reject(User $user): bool
    {
        return $user->hasRole(UserRole::ADMIN, UserRole::OWNER);
    }

    /**
     * Determine whether the user can assign physical units (admin-exclusive).
     */
    public function assignUnits(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can replace a unit assignment (admin-exclusive).
     */
    public function replaceUnit(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can extend a payment deadline (admin/owner).
     */
    public function extendDeadline(User $user): bool
    {
        return $user->hasRole(UserRole::ADMIN, UserRole::OWNER);
    }
}
