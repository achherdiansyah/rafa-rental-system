<?php

namespace Database\Factories;

use App\Enums\AssignmentStatus;
use App\Models\BookingDetail;
use App\Models\BookingUnitAssignment;
use App\Models\EquipmentUnit;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BookingUnitAssignment>
 */
class BookingUnitAssignmentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'booking_detail_id' => BookingDetail::factory(),
            'equipment_unit_id' => EquipmentUnit::factory(),
            'status' => AssignmentStatus::ASSIGNED->value,
            'is_current' => true,
            'assigned_by' => User::factory()->admin(),
            'replaced_reason' => null,
        ];
    }

    public function replaced(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => AssignmentStatus::REPLACED->value,
            'is_current' => false,
            'replaced_reason' => 'Unit breakdown during mobilization',
        ]);
    }
}
