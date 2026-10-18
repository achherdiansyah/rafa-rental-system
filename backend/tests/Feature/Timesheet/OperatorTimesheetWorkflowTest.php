<?php

namespace Tests\Feature\Timesheet;

use App\Enums\AssignmentStatus;
use App\Enums\BookingStatus;
use App\Enums\EquipmentStatus;
use App\Enums\InvoiceType;
use App\Enums\RentalStatus;
use App\Enums\TimesheetStatus;
use App\Models\Booking;
use App\Models\BookingDetail;
use App\Models\BookingUnitAssignment;
use App\Models\EquipmentModel;
use App\Models\EquipmentPrice;
use App\Models\EquipmentUnit;
use App\Models\Invoice;
use App\Models\ProjectLocation;
use App\Models\Rental;
use App\Models\RentalDetail;
use App\Models\RentalOperatorAssignment;
use App\Models\RentalPaymentHold;
use App\Models\Timesheet;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OperatorTimesheetWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    /**
     * @return array{admin: User, operator: User, other: User, rental: Rental, detail: RentalDetail}
     */
    private function scenario(): array
    {
        $admin = User::factory()->admin()->create();
        $operator = User::factory()->operator()->create();
        $other = User::factory()->operator()->create();
        $customer = User::factory()->create(['role' => 'USER']);

        $model = EquipmentModel::factory()->create(['is_active' => true]);
        EquipmentPrice::factory()->create([
            'equipment_model_id' => $model->id,
            'base_rate' => 200000,
            'overtime_rate' => 300000,
            'is_all_in' => true,
        ]);
        $unit = EquipmentUnit::factory()->create([
            'equipment_model_id' => $model->id,
            'status' => EquipmentStatus::ON_SITE,
        ]);
        $booking = Booking::factory()->create([
            'user_id' => $customer->id,
            'project_location_id' => ProjectLocation::factory()->create(['user_id' => $customer->id])->id,
            'status' => BookingStatus::ONGOING->value,
        ]);
        $bookingDetail = BookingDetail::factory()->create([
            'booking_id' => $booking->id,
            'equipment_model_id' => $model->id,
            'quantity' => 1,
            'is_all_in' => true,
            'rental_rate_snapshot' => 200000,
            'overtime_rate_snapshot' => 300000,
        ]);
        $assignment = BookingUnitAssignment::factory()->create([
            'booking_detail_id' => $bookingDetail->id,
            'equipment_unit_id' => $unit->id,
            'status' => AssignmentStatus::ASSIGNED,
            'is_current' => true,
            'assigned_by' => $admin->id,
        ]);
        $rental = Rental::factory()->create([
            'booking_id' => $booking->id,
            'status' => RentalStatus::ONGOING->value,
        ]);
        $detail = RentalDetail::factory()->create([
            'rental_id' => $rental->id,
            'assignment_id' => $assignment->id,
            'status' => RentalStatus::ONGOING->value,
        ]);
        RentalOperatorAssignment::create([
            'rental_id' => $rental->id,
            'rental_detail_id' => $detail->id,
            'operator_user_id' => $operator->id,
            'assigned_by' => $admin->id,
            'started_at' => now(),
            'status' => RentalOperatorAssignment::ACTIVE,
        ]);

        return compact('admin', 'operator', 'other', 'rental', 'detail');
    }

    public function test_operator_submits_paper_timesheet_and_admin_approval_creates_one_invoice(): void
    {
        ['admin' => $admin, 'operator' => $operator, 'detail' => $detail] = $this->scenario();

        Sanctum::actingAs($operator);
        $created = $this->postJson('/api/v1/timesheets', [
            'rental_detail_id' => $detail->id,
            'report_date' => now()->toDateString(),
            'start_time' => '08:00',
            'end_time' => '18:00',
        ])->assertCreated()->assertJsonPath('data.status', TimesheetStatus::DRAFT->value);

        $id = $created->json('data.id');

        $this->postJson("/api/v1/timesheets/{$id}/submit")
            ->assertStatus(409)
            ->assertJsonPath('code', 'BUSINESS_RULE_VIOLATION');

        $this->postJson("/api/v1/timesheets/{$id}/paper-photo", [
            'paper_form' => UploadedFile::fake()->image('timesheet.jpg', 600, 800),
        ])->assertOk();

        $this->postJson("/api/v1/timesheets/{$id}/submit")
            ->assertOk()
            ->assertJsonPath('data.status', TimesheetStatus::SUBMITTED->value);

        $this->assertSame(0, Invoice::where('timesheet_id', $id)->count());

        Sanctum::actingAs($admin);
        $this->postJson("/api/v1/timesheets/{$id}/approve")
            ->assertOk()
            ->assertJsonPath('data.status', TimesheetStatus::APPROVED->value);

        $this->assertSame(1, Invoice::where('timesheet_id', $id)->count());
        $invoice = Invoice::where('timesheet_id', $id)->firstOrFail();
        $this->assertEquals(InvoiceType::DAILY_WORK, $invoice->invoice_type);
        // 8h normal @200k + 2h overtime @300k = 2.200.000
        $this->assertEqualsWithDelta(2200000.0, (float) $invoice->grand_total, 0.01);

        $this->postJson("/api/v1/timesheets/{$id}/approve")->assertOk();
        $this->assertSame(1, Invoice::where('timesheet_id', $id)->count());
    }

    public function test_operator_cannot_create_or_submit_for_another_operators_assignment(): void
    {
        ['other' => $other, 'detail' => $detail] = $this->scenario();

        Sanctum::actingAs($other);
        $this->postJson('/api/v1/timesheets', [
            'rental_detail_id' => $detail->id,
            'report_date' => now()->toDateString(),
            'start_time' => '08:00',
            'end_time' => '17:00',
        ])->assertForbidden();
    }

    public function test_payment_hold_blocks_new_timesheet_input(): void
    {
        ['operator' => $operator, 'rental' => $rental, 'detail' => $detail] = $this->scenario();

        RentalPaymentHold::create([
            'rental_id' => $rental->id,
            'status' => RentalPaymentHold::ACTIVE,
            'reason_code' => RentalPaymentHold::REASON_INVOICE_COUNT,
            'outstanding_amount' => 3000000,
            'started_at' => now(),
        ]);

        Sanctum::actingAs($operator);
        $this->postJson('/api/v1/timesheets', [
            'rental_detail_id' => $detail->id,
            'report_date' => now()->toDateString(),
            'start_time' => '08:00',
            'end_time' => '17:00',
        ])->assertStatus(409)->assertJsonPath('code', 'BUSINESS_RULE_VIOLATION');
    }

    public function test_rejection_requires_reason_and_operator_can_fix_and_resubmit(): void
    {
        ['admin' => $admin, 'operator' => $operator, 'detail' => $detail] = $this->scenario();

        Sanctum::actingAs($operator);
        $id = $this->postJson('/api/v1/timesheets', [
            'rental_detail_id' => $detail->id,
            'report_date' => now()->toDateString(),
            'start_time' => '08:00',
            'end_time' => '17:00',
        ])->json('data.id');
        $this->postJson("/api/v1/timesheets/{$id}/paper-photo", [
            'paper_form' => UploadedFile::fake()->image('paper.png'),
        ])->assertOk();
        $this->postJson("/api/v1/timesheets/{$id}/submit")->assertOk();

        Sanctum::actingAs($admin);
        $this->postJson("/api/v1/timesheets/{$id}/reject", ['reason' => 'Jam tidak sesuai form kertas.'])
            ->assertOk()
            ->assertJsonPath('data.status', TimesheetStatus::REJECTED->value);
        $this->assertSame(0, Invoice::where('timesheet_id', $id)->count());

        Sanctum::actingAs($operator);
        $this->postJson("/api/v1/timesheets/{$id}/submit")->assertOk();
        Sanctum::actingAs($admin);
        $this->postJson("/api/v1/timesheets/{$id}/approve")->assertOk();
        $this->assertSame(1, Invoice::where('timesheet_id', $id)->count());
    }
}

