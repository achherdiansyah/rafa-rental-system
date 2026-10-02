<?php

namespace Tests\Feature\Pricing;

use App\Actions\Booking\ApproveBookingAction;
use App\Actions\Booking\CreateBookingFromCartAction;
use App\Enums\AssignmentStatus;
use App\Enums\BookingStatus;
use App\Enums\EquipmentStatus;
use App\Enums\InvoiceType;
use App\Enums\RentalStatus;
use App\Enums\TimesheetStatus;
use App\Enums\UserRole;
use App\Models\Booking;
use App\Models\BookingDetail;
use App\Models\BookingUnitAssignment;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\CustomerProfile;
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

class PricingBusinessRuleFinalTest extends TestCase
{
    use RefreshDatabase;

    private function setupData(bool $isAllIn = true, float $baseRate = 365000.00, float $overtimeRate = 415000.00, float $mob = 600000.00, float $demob = 400000.00): array
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create(['role' => UserRole::USER]);
        CustomerProfile::factory()->create(['user_id' => $user->id, 'verification_status' => 'VERIFIED']);

        $location = ProjectLocation::factory()->create(['user_id' => $user->id]);
        $model = EquipmentModel::factory()->create(['is_active' => true, 'model_name' => 'PC200-8']);

        EquipmentPrice::factory()->create([
            'equipment_model_id' => $model->id,
            'is_all_in' => $isAllIn,
            'base_rate' => $baseRate,
            'overtime_rate' => $overtimeRate,
            'mob_cost' => $mob,
            'demob_cost' => $demob,
            'effective_date' => now()->subMonth(),
        ]);

        $unit = EquipmentUnit::factory()->create([
            'equipment_model_id' => $model->id,
            'status' => EquipmentStatus::AVAILABLE,
        ]);

        return [$admin, $user, $location, $model, $unit];
    }

    /**
     * 1 & 2: Catalog displays MOB/DEMOB and Hourly rate as reference
     */
    public function test_catalog_displays_mob_demob_and_hourly_reference(): void
    {
        [$admin, $user, , $model] = $this->setupData(isAllIn: true, baseRate: 365000, overtimeRate: 415000, mob: 600000, demob: 400000);

        Sanctum::actingAs($user);
        $res = $this->getJson("/api/v1/equipment/models/{$model->id}");

        $res->assertOk();
        $price = $res->json('data.prices.0');
        $this->assertEquals(365000, $price['base_rate']);
        $this->assertEquals(415000, $price['overtime_rate']);
        $this->assertEquals(600000, $price['mob_cost']);
        $this->assertEquals(400000, $price['demob_cost']);
    }

    /**
     * 3 & 4: Booking total only MOB/DEMOB; Hourly rate does NOT get multiplied by days
     */
    public function test_booking_total_only_mob_demob_not_hourly_multiplied(): void
    {
        [$admin, $user, $location, $model] = $this->setupData(isAllIn: true, baseRate: 365000, overtimeRate: 415000, mob: 600000, demob: 400000);

        // Create a 2nd unit so quantity=2 is available
        EquipmentUnit::factory()->create([
            'equipment_model_id' => $model->id,
            'status' => EquipmentStatus::AVAILABLE,
        ]);

        $cart = Cart::create(['user_id' => $user->id, 'project_location_id' => $location->id]);
        CartItem::create([
            'cart_id' => $cart->id,
            'equipment_model_id' => $model->id,
            'quantity' => 2, // 2 units
            'is_all_in' => true,
            'start_date' => now()->addDays(2)->toDateString(),
            'end_date' => now()->addDays(6)->toDateString(), // 5 days
        ]);

        // Hourly for 5 days * 8h * 2 units would be 29.200.000.
        // BUT rule strictly mandates: Booking total = MOB + DEMOB only!
        // 2 units * (600.000 + 400.000) = 2.000.000
        $booking = app(CreateBookingFromCartAction::class)->execute($user, $cart);

        $this->assertEquals(2000000, (float) $booking->total_amount, 'Total booking harus HANYA MOB/DEMOB (Rp 2.000.000), bukan Rp 31.200.000.');
        
        $detail = $booking->details()->first();
        $this->assertEquals(2000000, (float) $detail->subtotal);
        $this->assertEquals(365000, (float) $detail->rental_rate_snapshot, 'Snapshot tarif hourly tetap disimpan untuk billing Timesheet.');
        $this->assertEquals(415000, (float) $detail->overtime_rate_snapshot);
    }

    /**
     * 5 & 6: Admin approval displays correct total & issues MOB/DEMOB invoice only
     */
    public function test_admin_approval_displays_correct_total_and_mob_demob_invoice_only(): void
    {
        [$admin, $user, $location, $model] = $this->setupData(mob: 600000, demob: 400000);

        $cart = Cart::create(['user_id' => $user->id, 'project_location_id' => $location->id]);
        CartItem::create([
            'cart_id' => $cart->id,
            'equipment_model_id' => $model->id,
            'quantity' => 1,
            'is_all_in' => true,
            'start_date' => now()->addDays(2)->toDateString(),
            'end_date' => now()->addDays(5)->toDateString(),
        ]);

        $booking = app(CreateBookingFromCartAction::class)->execute($user, $cart);
        $booking->update(['status' => BookingStatus::PENDING_APPROVAL]);

        // Admin views booking
        Sanctum::actingAs($admin);
        $res = $this->getJson("/api/v1/bookings/{$booking->id}");
        $res->assertOk();
        $this->assertEquals(1000000, (float) $res->json('data.total_amount'));

        // Admin approves booking
        app(ApproveBookingAction::class)->execute($admin, $booking);

        $invoices = Invoice::where('booking_id', $booking->id)->get();
        $this->assertCount(1, $invoices);
        $inv = $invoices->first();
        $this->assertEquals(InvoiceType::MOB_DEMOB, $inv->invoice_type);
        $this->assertEquals(1000000, (float) $inv->grand_total);
    }

    /**
     * 7 & 8: Admin can input timesheet; User is read-only
     */
    public function test_timesheet_admin_input_and_user_read_only(): void
    {
        [$admin, $user, $location, $model, $unit] = $this->setupData();

        $booking = Booking::factory()->create([
            'user_id' => $user->id,
            'project_location_id' => $location->id,
            'status' => BookingStatus::CONFIRMED,
        ]);
        $detail = BookingDetail::factory()->create([
            'booking_id' => $booking->id,
            'equipment_model_id' => $model->id,
            'quantity' => 1,
            'is_all_in' => true,
            'rental_rate_snapshot' => 365000,
            'overtime_rate_snapshot' => 415000,
        ]);
        BookingUnitAssignment::factory()->create([
            'booking_detail_id' => $detail->id,
            'equipment_unit_id' => $unit->id,
            'is_current' => true,
            'assigned_by' => $admin->id,
        ]);

        Sanctum::actingAs($admin);
        $rentalId = $this->postJson('/api/v1/rentals', ['booking_id' => $booking->id])->json('data.id');
        foreach (['dispatch', 'arrive', 'start'] as $t) {
            $this->postJson("/api/v1/rentals/{$rentalId}/{$t}")->assertOk();
        }

        $rd = Rental::findOrFail($rentalId)->details()->first();

        // 7. Admin inputs timesheet -> auto valid / APPROVED
        Sanctum::actingAs($admin);
        $res = $this->postJson('/api/v1/timesheets', [
            'rental_detail_id' => $rd->id,
            'report_date' => now()->toDateString(),
            'start_time' => '08:00',
            'end_time' => '15:00',
            'break_minutes' => 0,
        ]);
        $res->assertCreated();
        $tsId = $res->json('data.id');
        $this->assertEquals('APPROVED', Timesheet::find($tsId)->status->value);

        // 8. User attempts to create timesheet -> FORBIDDEN (read-only)
        Sanctum::actingAs($user);
        $this->postJson('/api/v1/timesheets', [
            'rental_detail_id' => $rd->id,
            'report_date' => now()->subDay()->toDateString(),
            'start_time' => '08:00',
            'end_time' => '15:00',
        ])->assertStatus(403);

        // User can view timesheet
        $this->getJson("/api/v1/timesheets/{$tsId}")->assertOk();
    }

    /**
     * 9, 10 & 11: Daily Work Invoice uses actual hours, overtime for >8h, and correct All-in/Non All-in
     */
    public function test_daily_work_invoice_actual_hours_and_overtime(): void
    {
        // Setup Non All-in: 250k normal, 300k overtime
        [$admin, $user, $location, $model, $unit] = $this->setupData(
            isAllIn: false,
            baseRate: 250000.00,
            overtimeRate: 300000.00
        );

        $booking = Booking::factory()->create([
            'user_id' => $user->id,
            'project_location_id' => $location->id,
            'status' => BookingStatus::CONFIRMED,
        ]);
        $detail = BookingDetail::factory()->create([
            'booking_id' => $booking->id,
            'equipment_model_id' => $model->id,
            'quantity' => 1,
            'is_all_in' => false,
            'rental_rate_snapshot' => 250000,
            'overtime_rate_snapshot' => 300000,
        ]);
        BookingUnitAssignment::factory()->create([
            'booking_detail_id' => $detail->id,
            'equipment_unit_id' => $unit->id,
            'is_current' => true,
            'assigned_by' => $admin->id,
        ]);

        Sanctum::actingAs($admin);
        $rentalId = $this->postJson('/api/v1/rentals', ['booking_id' => $booking->id])->json('data.id');
        foreach (['dispatch', 'arrive', 'start'] as $t) {
            $this->postJson("/api/v1/rentals/{$rentalId}/{$t}")->assertOk();
        }

        $rd = Rental::findOrFail($rentalId)->details()->first();

        // Work: 10 hours (08:00 to 18:00, 0 break)
        // 8h normal * 250.000 = 2.000.000
        // 2h overtime * 300.000 = 600.000
        // Total Daily Invoice = 2.600.000
        Sanctum::actingAs($admin);
        $res = $this->postJson('/api/v1/timesheets', [
            'rental_detail_id' => $rd->id,
            'report_date' => now()->toDateString(),
            'start_time' => '08:00',
            'end_time' => '18:00',
            'break_minutes' => 0,
        ]);
        $res->assertCreated();

        $ts = Timesheet::find($res->json('data.id'));
        $invoice = Invoice::where('timesheet_id', $ts->id)->firstOrFail();

        $this->assertEquals(InvoiceType::DAILY_WORK, $invoice->invoice_type);
        $this->assertEquals(2600000, (float) $invoice->grand_total);
        $this->assertCount(2, $invoice->details);
        
        $normal = $invoice->details()->where('description', 'like', 'Sewa Harian%')->first();
        $this->assertEquals(8, (float) $normal->quantity);
        $this->assertEquals(250000, (float) $normal->unit_price);
        $this->assertStringContainsString('Non All-in', $normal->description);

        $ot = $invoice->details()->where('description', 'like', 'Lembur%')->first();
        $this->assertEquals(2, (float) $ot->quantity);
        $this->assertEquals(300000, (float) $ot->unit_price);
        $this->assertStringContainsString('Non All-in', $ot->description);
    }
}
