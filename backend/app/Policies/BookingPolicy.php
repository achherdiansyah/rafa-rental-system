<?php

namespace App\Policies;

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
}
