<?php

namespace Tests\Feature\Billing;

use App\Actions\Booking\ApproveBookingAction;
use App\Enums\AssignmentStatus;
use App\Enums\BookingStatus;
use App\Enums\EquipmentStatus;
use App\Enums\InvoiceType;
use App\Enums\RentalStatus;
use App\Enums\TimesheetStatus;
use App\Enums\UserRole;
use App\Exceptions\BusinessRuleException;
use App\Models\Booking;
use App\Models\BookingDetail;
use App\Models\BookingUnitAssignment;
use App\Models\EquipmentModel;
use App\Models\EquipmentPrice;
use App\Models\EquipmentUnit;
use App\Models\Invoice;
use App\Models\ProjectLocation;
use App\Models\Rental;
use App\Models\Timesheet;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DailyWorkBillingRulesTest extends TestCase
{
    use RefreshDatabase;

    private function setupOngoingRental(bool $isAllIn = true, float $baseRate = 350000.00, float $overtimeRate = 400000.00, float $mob = 500000.00, float $demob = 350000.00): array
    {
        $owner = User::factory()->create(['role' => UserRole::USER]);
        $admin = User::factory()->admin()->create();
        $location = ProjectLocation::factory()->create(['user_id' => $owner->id]);
        $model = EquipmentModel::factory()->create(['is_active' => true, 'model_name' => 'PC200-8']);

        EquipmentPrice::factory()->create([
            'equipment_model_id' => $model->id,
            'is_all_in' => $isAllIn,
            'base_rate' => $baseRate,
            'overtime_rate' => $overtimeRate,
            'mob_cost' => $mob,
            'demob_cost' => $demob,
            'effective_date' => now()->subMonth()->toDateString(),
        ]);

        $unit = EquipmentUnit::factory()->create([
            'equipment_model_id' => $model->id,
            'status' => EquipmentStatus::ASSIGNED,
        ]);

        $booking = Booking::factory()->create([
            'user_id' => $owner->id,
            'project_location_id' => $location->id,
            'status' => BookingStatus::PENDING_APPROVAL,
            'total_amount' => ($baseRate * 8 * 5) + $mob + $demob,
        ]);

        $detail = BookingDetail::factory()->create([
            'booking_id' => $booking->id,
            'equipment_model_id' => $model->id,
            'quantity' => 1,
            'start_date' => now()->addDays(2)->toDateString(),
            'end_date' => now()->addDays(8)->toDateString(),
            'is_all_in' => $isAllIn,
            'rental_rate_snapshot' => $baseRate,
            'overtime_rate_snapshot' => $overtimeRate,
            'mob_cost_snapshot' => $mob,
            'demob_cost_snapshot' => $demob,
            'subtotal' => $baseRate * 8 * 5,
        ]);

        BookingUnitAssignment::factory()->create([
            'booking_detail_id' => $detail->id,
            'equipment_unit_id' => $unit->id,
            'status' => AssignmentStatus::ASSIGNED,
            'is_current' => true,
            'assigned_by' => $admin->id,
        ]);

        return [$owner, $admin, $booking, $detail, $unit];
    }

    public function test_booking_approved_creates_mob_demob_invoice_only(): void
    {
        [, $admin, $booking] = $this->setupOngoingRental();

        app(ApproveBookingAction::class)->execute($admin, $booking);

        $this->assertEquals(BookingStatus::APPROVED->value, $booking->fresh()->status->value);

        // Exactly 1 invoice created, and it MUST be MOB_DEMOB
        $invoices = Invoice::where('booking_id', $booking->id)->get();
        $this->assertCount(1, $invoices);
        $this->assertEquals(InvoiceType::MOB_DEMOB, $invoices->first()->invoice_type);

        // MOB/DEMOB amounts: 500k + 350k = 850k (NO rental rate / sewa harian)
        $this->assertEqualsWithDelta(850000.00, (float) $invoices->first()->grand_total, 0.01);
        $this->assertCount(2, $invoices->first()->details);
    }

    public function test_timesheet_day_1_and_day_2_daily_invoices(): void
    {
        [$owner, $admin, $booking, , $unit] = $this->setupOngoingRental(
            isAllIn: true,
            baseRate: 350000.00,
            mob: 0,
            demob: 0
        );

        $booking->update(['status' => BookingStatus::CONFIRMED]);

        Sanctum::actingAs($admin);
        $rentalId = $this->postJson('/api/v1/rentals', ['booking_id' => $booking->id])->json('data.id');
        foreach (['dispatch', 'arrive', 'start'] as $t) {
            $this->postJson("/api/v1/rentals/{$rentalId}/{$t}")->assertOk();
        }

        $rental = Rental::findOrFail($rentalId);
        $rd = $rental->details()->first();

        // Day 1: 7 hours × 350.000 = Rp 2.450.000
        $day1 = now()->subDays(2)->toDateString();
        $res1 = $this->postJson('/api/v1/timesheets', [
            'rental_detail_id' => $rd->id,
            'report_date' => $day1,
            'start_hm' => 10,
            'end_hm' => 17,
            'break_minutes' => 0,
        ]);
        $res1->assertCreated();

        $ts1 = Timesheet::find($res1->json('data.id'));
        $this->assertEquals(TimesheetStatus::APPROVED->value, $ts1->status->value);
        $this->assertEqualsWithDelta(7.0, (float) $ts1->total_work_hours, 0.01);

        $inv1 = Invoice::where('timesheet_id', $ts1->id)->first();
        $this->assertNotNull($inv1);
        $this->assertEquals(InvoiceType::DAILY_WORK, $inv1->invoice_type);
        $this->assertEqualsWithDelta(2450000.00, (float) $inv1->grand_total, 0.01);

        // Day 2: 6 hours × 350.000 = Rp 2.100.000
        $day2 = now()->subDays(1)->toDateString();
        $res2 = $this->postJson('/api/v1/timesheets', [
            'rental_detail_id' => $rd->id,
            'report_date' => $day2,
            'start_hm' => 17,
            'end_hm' => 23,
            'break_minutes' => 0,
        ]);
        $res2->assertCreated();

        $ts2 = Timesheet::find($res2->json('data.id'));
        $this->assertEquals(TimesheetStatus::APPROVED->value, $ts2->status->value);
        $this->assertEqualsWithDelta(6.0, (float) $ts2->total_work_hours, 0.01);

        $inv2 = Invoice::where('timesheet_id', $ts2->id)->first();
        $this->assertNotNull($inv2);
        $this->assertEquals(InvoiceType::DAILY_WORK, $inv2->invoice_type);
        $this->assertEqualsWithDelta(2100000.00, (float) $inv2->grand_total, 0.01);
    }

    public function test_overtime_calculation_for_hours_greater_than_8(): void
    {
        [$owner, $admin, $booking] = $this->setupOngoingRental(
            isAllIn: true,
            baseRate: 350000.00,
            overtimeRate: 400000.00,
            mob: 0,
            demob: 0
        );

        $booking->update(['status' => BookingStatus::CONFIRMED]);

        Sanctum::actingAs($admin);
        $rentalId = $this->postJson('/api/v1/rentals', ['booking_id' => $booking->id])->json('data.id');
        foreach (['dispatch', 'arrive', 'start'] as $t) {
            $this->postJson("/api/v1/rentals/{$rentalId}/{$t}")->assertOk();
        }

        $rd = Rental::findOrFail($rentalId)->details()->first();

        // 10 hours: 8 normal × 350.000 (2.800.000) + 2 overtime × 400.000 (800.000) = 3.600.000
        $res = $this->postJson('/api/v1/timesheets', [
            'rental_detail_id' => $rd->id,
            'report_date' => now()->toDateString(),
            'start_hm' => 8,
            'end_hm' => 18,
            'break_minutes' => 0,
        ]);
        $res->assertCreated();

        $ts = Timesheet::find($res->json('data.id'));
        $inv = Invoice::where('timesheet_id', $ts->id)->first();

        $this->assertNotNull($inv);
        $this->assertEqualsWithDelta(3600000.00, (float) $inv->grand_total, 0.01);
        $this->assertCount(2, $inv->details);

        $normalLine = $inv->details()->where('description', 'like', 'Sewa Harian%')->first();
        $this->assertEquals(350000.00, (float) $normalLine->unit_price);
        $this->assertEqualsWithDelta(8.0, (float) $normalLine->quantity, 0.01);
        $this->assertEqualsWithDelta(2800000.00, (float) $normalLine->subtotal, 0.01);

        $otLine = $inv->details()->where('description', 'like', 'Lembur%')->first();
        $this->assertEquals(400000.00, (float) $otLine->unit_price);
        $this->assertEqualsWithDelta(2.0, (float) $otLine->quantity, 0.01);
        $this->assertEqualsWithDelta(800000.00, (float) $otLine->subtotal, 0.01);
    }

    public function test_all_in_and_non_all_in_scheme_respected_in_daily_invoice(): void
    {
        // Non All-in setup: 225k normal, 275k overtime
        [$owner, $admin, $booking] = $this->setupOngoingRental(
            isAllIn: false,
            baseRate: 225000.00,
            overtimeRate: 275000.00,
            mob: 0,
            demob: 0
        );

        $booking->update(['status' => BookingStatus::CONFIRMED]);

        Sanctum::actingAs($admin);
        $rentalId = $this->postJson('/api/v1/rentals', ['booking_id' => $booking->id])->json('data.id');
        foreach (['dispatch', 'arrive', 'start'] as $t) {
            $this->postJson("/api/v1/rentals/{$rentalId}/{$t}")->assertOk();
        }

        $rd = Rental::findOrFail($rentalId)->details()->first();

        $res = $this->postJson('/api/v1/timesheets', [
            'rental_detail_id' => $rd->id,
            'report_date' => now()->toDateString(),
            'start_hm' => 8,
            'end_hm' => 17,
            'break_minutes' => 0,
        ]);
        $res->assertCreated();

        $ts = Timesheet::find($res->json('data.id'));
        $inv = Invoice::where('timesheet_id', $ts->id)->first();

        // 9 hours Non All-in: 8h @ 225k (1.800.000) + 1h @ 275k (275.000) = 2.075.000
        $this->assertEqualsWithDelta(2075000.00, (float) $inv->grand_total, 0.01);
        $normalLine = $inv->details()->where('description', 'like', 'Sewa Harian%')->first();
        $this->assertStringContainsString('Non All-in', $normalLine->description);
    }

    public function test_three_day_outstanding_threshold_blocks_fourth_daily_timesheet(): void
    {
        [$owner, $admin, $booking] = $this->setupOngoingRental(mob: 0, demob: 0);

        $booking->update(['status' => BookingStatus::CONFIRMED]);

        Sanctum::actingAs($admin);
        $rentalId = $this->postJson('/api/v1/rentals', ['booking_id' => $booking->id])->json('data.id');
        foreach (['dispatch', 'arrive', 'start'] as $t) {
            $this->postJson("/api/v1/rentals/{$rentalId}/{$t}")->assertOk();
        }

        $rd = Rental::findOrFail($rentalId)->details()->first();

        // Days 1, 2, 3: Success, creates 3 unpaid daily invoices
        for ($i = 3; $i >= 1; $i--) {
            $date = now()->subDays($i)->toDateString();
            $res = $this->postJson('/api/v1/timesheets', [
                'rental_detail_id' => $rd->id,
                'report_date' => $date,
                'start_hm' => 8 + (3 - $i) * 10,
                'end_hm' => 16 + (3 - $i) * 10,
                'break_minutes' => 0,
            ]);
            $res->assertCreated();
        }

        $unpaidCount = Invoice::where('booking_id', $booking->id)
            ->where('invoice_type', InvoiceType::DAILY_WORK)
            ->count();
        $this->assertEquals(3, $unpaidCount);

        // Day 4: Must fail because max_outstanding_days = 3
        $res4 = $this->postJson('/api/v1/timesheets', [
            'rental_detail_id' => $rd->id,
            'report_date' => now()->toDateString(),
            'start_hm' => 40,
            'end_hm' => 48,
            'break_minutes' => 0,
        ]);

        $res4->assertStatus(409)
            ->assertJson(['code' => 'BUSINESS_RULE_VIOLATION']);
    }
}
