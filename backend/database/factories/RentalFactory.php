<?php

namespace Database\Factories;

use App\Enums\RentalStatus;
use App\Models\Booking;
use App\Models\Rental;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Rental>
 */
class RentalFactory extends Factory
{
    public function definition(): array
    {
        return [
            'booking_id' => Booking::factory(),
            'status' => RentalStatus::PENDING_ASSIGNMENT->value,
            'started_at' => null,
            'completed_at' => null,
        ];
    }

    public function dispatched(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => RentalStatus::DISPATCHED->value,
        ]);
    }

    public function arrived(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => RentalStatus::ARRIVED->value,
        ]);
    }

    public function ongoing(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => RentalStatus::ONGOING->value,
            'started_at' => now(),
        ]);
    }
}
