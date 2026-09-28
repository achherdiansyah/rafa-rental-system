<?php

namespace App\Console\Commands;

use App\Actions\Booking\ExpireBookingAction;
use App\Models\Booking;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ExpireBookingsCommand extends Command
{
    protected $signature = 'bookings:expire';

    protected $description = 'Expire bookings whose payment deadline has passed and release their slots';

    public function handle(ExpireBookingAction $action): int
    {
        $candidates = Booking::whereIn('status', config('availability.payment_pending_statuses', []))
            ->whereNull('payment_met_at')
            ->get();

        $expiredCount = 0;

        foreach ($candidates as $booking) {
            try {
                $action->execute($booking);
                $expiredCount++;
            } catch (\Throwable $e) {
                Log::warning('AUDIT [BOOKING_EXPIRY_SKIP]: '.$e->getMessage(), [
                    'booking_id' => $booking->id,
                ]);
            }
        }

        if ($expiredCount > 0) {
            Log::info("AUDIT [BOOKING_EXPIRY]: {$expiredCount} booking(s) expired by scheduler.");
        }

        $this->info("Berhasil: {$expiredCount} booking expired dari {$candidates->count()} kandidat.");

        return self::SUCCESS;
    }
}
