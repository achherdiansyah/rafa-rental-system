<?php

namespace Database\Factories;

use App\Models\BusinessCalendar;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BusinessCalendar>
 */
class BusinessCalendarFactory extends Factory
{
    public function definition(): array
    {
        return [
            'calendar_date' => now()->toDateString(),
            'is_working_day' => true,
            'holiday_name' => null,
        ];
    }

    public function holiday(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_working_day' => false,
            'holiday_name' => 'Hari Libur Nasional',
        ]);
    }
}
