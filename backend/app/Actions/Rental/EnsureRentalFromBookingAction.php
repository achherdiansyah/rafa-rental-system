<?php

namespace App\Actions\Rental;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Rental;
use App\Models\User;

/**
 * Safety-net: creates a Rental for a booking when the booking becomes CONFIRMED
 * and its physical units have already been assigned. Idempotent — will never
 * duplicate nor fail when a rental already exists. This closes the
 * APPROVED → CONFIRMED → Rental gap so every paid order appears in Eksekusi
 * Rental with its own status/actions.
 */
class EnsureRentalFromBookingAction
{
    public function __construct(
        private readonly CreateRentalFromBookingAction $create
    ) {}

    public function execute(User $admin, Booking $booking): ?Rental
    {
        if ($booking->status !== BookingStatus::CONFIRMED) {
            return null;
        }

        if ($booking->rental()->exists()) {
            return $booking->rental;
        }

        $hasCurrentAssignments = $booking->unitAssignments()
            ->where('is_current', true)
            ->exists();

        if (! $hasCurrentAssignments) {
            return null;
        }

        return $this->create->execute($admin, $booking);
    }
}