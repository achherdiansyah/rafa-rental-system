<?php

namespace Tests\Feature\Domain;

use App\Enums\RentalStatus;
use App\Enums\TimesheetStatus;
use App\Models\Booking;
use App\Models\BookingUnitAssignment;
use App\Models\Rental;
use App\Models\RentalDetail;
use App\Models\Timesheet;
use App\Models\TimesheetRevision;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RentalAndTimesheetDomainTest extends TestCase
{
    use RefreshDatabase;

    public function test_booking_has_one_rental(): void
    {
        $booking = Booking::factory()->create();
        $rental = Rental::factory()->create(['booking_id' => $booking->id]);

        $this->assertTrue($rental->booking->is($booking));
    }

    public function test_rental_has_many_details(): void
    {
        $rental = Rental::factory()->create();
        $d1 = RentalDetail::factory()->create(['rental_id' => $rental->id]);
        $d2 = RentalDetail::factory()->create(['rental_id' => $rental->id]);

        $this->assertCount(2, $rental->details);
        $this->assertTrue($d1->rental->is($rental));
    }

    public function test_rental_detail_assignment_id_is_unique(): void
    {
        $assignment = BookingUnitAssignment::factory()->create();
        RentalDetail::factory()->create(['assignment_id' => $assignment->id]);

        $this->expectException(QueryException::class);
        RentalDetail::factory()->create(['assignment_id' => $assignment->id]);
    }

    public function test_rental_status_casts_to_enum(): void
    {
        $rental = Rental::factory()->ongoing()->create();

        $this->assertInstanceOf(RentalStatus::class, $rental->status);
        $this->assertEquals(RentalStatus::ONGOING, $rental->status);
    }

    public function test_timesheet_belongs_to_rental_detail(): void
    {
        $detail = RentalDetail::factory()->create();
        $timesheet = Timesheet::factory()->create(['rental_detail_id' => $detail->id]);

        $this->assertTrue($timesheet->rentalDetail->is($detail));
        $this->assertCount(1, $detail->timesheets);
    }

    public function test_timesheet_has_many_revisions_without_losing_old_data(): void
    {
        $admin = User::factory()->admin()->create();
        $timesheet = Timesheet::factory()->create([
            'start_hm' => 1000.00,
            'end_hm' => 1008.00,
        ]);

        $rev1 = TimesheetRevision::factory()->create([
            'timesheet_id' => $timesheet->id,
            'version' => 1,
            'old_start_hm' => 1000.00,
            'old_end_hm' => 1008.00,
            'revision_reason' => 'Salah input jam mulai',
            'revised_by' => $admin->id,
        ]);

        $this->assertCount(1, $timesheet->revisions);
        $this->assertEquals('1000.00', $rev1->old_start_hm);
        $this->assertEquals('1008.00', $rev1->old_end_hm);
        $this->assertTrue($rev1->revisedByUser->is($admin));
    }

    public function test_timesheet_status_casts_to_enum(): void
    {
        $timesheet = Timesheet::factory()->submitted()->create();

        $this->assertInstanceOf(TimesheetStatus::class, $timesheet->status);
        $this->assertEquals(TimesheetStatus::SUBMITTED, $timesheet->status);
    }
}
