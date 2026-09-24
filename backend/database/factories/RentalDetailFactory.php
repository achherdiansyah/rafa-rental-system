<?php

namespace Database\Factories;

use App\Enums\RentalStatus;
use App\Models\BookingUnitAssignment;
use App\Models\Rental;
use App\Models\RentalDetail;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RentalDetail>
 */
class RentalDetailFactory extends Factory
{
    public function definition(): array
    {
        return [
            'rental_id' => Rental::factory(),
            'assignment_id' => BookingUnitAssignment::factory(),
            'check_in_hm' => null,
            'check_out_hm' => null,
            'condition_notes' => null,
            'status' => RentalStatus::PENDING_ASSIGNMENT->value,
        ];
    }
}
