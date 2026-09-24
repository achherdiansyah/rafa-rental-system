<?php

namespace Database\Factories;

use App\Enums\TimesheetStatus;
use App\Models\RentalDetail;
use App\Models\Timesheet;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Timesheet>
 */
class TimesheetFactory extends Factory
{
    public function definition(): array
    {
        $startHm = fake()->randomFloat(2, 1000, 5000);
        $workHours = fake()->randomFloat(2, 4, 10);

        return [
            'rental_detail_id' => RentalDetail::factory(),
            'report_date' => now()->toDateString(),
            'start_hm' => $startHm,
            'end_hm' => round($startHm + $workHours, 2),
            'total_work_hours' => $workHours,
            'standby_hours' => 0.00,
            'breakdown_hours' => 0.00,
            'status' => TimesheetStatus::DRAFT->value,
            'approved_by' => null,
        ];
    }

    public function submitted(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TimesheetStatus::SUBMITTED->value,
        ]);
    }
}
