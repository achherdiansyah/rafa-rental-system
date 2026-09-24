<?php

namespace Database\Factories;

use App\Models\Timesheet;
use App\Models\TimesheetRevision;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TimesheetRevision>
 */
class TimesheetRevisionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'timesheet_id' => Timesheet::factory(),
            'version' => 1,
            'old_start_hm' => 1000.00,
            'old_end_hm' => 1008.00,
            'revision_reason' => 'Koreksi data HM lapangan',
            'revised_by' => User::factory(),
            'created_at' => now(),
        ];
    }
}
