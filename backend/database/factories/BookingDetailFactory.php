<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\BookingDetail;
use App\Models\EquipmentModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BookingDetail>
 */
class BookingDetailFactory extends Factory
{
    public function definition(): array
    {
        $start = now()->addDays(2);

        return [
            'booking_id' => Booking::factory(),
            'equipment_model_id' => EquipmentModel::factory(),
            'quantity' => 1,
            'start_date' => $start->toDateString(),
            'end_date' => $start->addDays(5)->toDateString(),
            'is_all_in' => false,
            'rental_rate_snapshot' => 250000.00,
            'subtotal' => 10000000.00,
        ];
    }
}
