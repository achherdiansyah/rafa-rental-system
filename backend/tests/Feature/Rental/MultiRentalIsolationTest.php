<?php

namespace Tests\Feature\Rental;

use App\Enums\AssignmentStatus;
use App\Enums\BookingStatus;
use App\Enums\EquipmentStatus;
use App\Enums\RentalStatus;
use App\Models\BankAccount;
use App\Models\Booking;
use App\Models\BookingDetail;
use App\Models\BookingUnitAssignment;
use App\Models\EquipmentModel;
use App\Models\EquipmentPrice;
use App\Models\EquipmentUnit;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\ProjectLocation;
use App\Models\Rental;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Regression: multiple rental orders must be processed per booking/rental id
 * independently. Transitioning one order must never affect the other.
 */
class MultiRentalIsolationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create();
        $this->user = User::factory()->create();
    }

    /**
     * Build a CONFIRMED booking with one assigned AVAILABLE unit.
     */
    private function confirmedBookingWithUnit(): array
    {
        $location = ProjectLocation::factory()->create(['user_id' => $this->user->id]);
        $model = EquipmentModel::factory()->create(['is_active' => true]);
        $unit = EquipmentUnit::factory()->create([
            'equipment_model_id' => $model->id,
            'status' => EquipmentStatus::AVAILABLE,
        ]);

        $booking = Booking::factory()->create([
            'user_id' => $this->user->id,
            'project_location_id' => $location->id,
            'status' => BookingStatus::CONFIRMED,
        ]);
        $detail = BookingDetail::factory()->create([
            'booking_id' => $booking->id,
            'equipment_model_id' => $model->id,
            'quantity' => 1,
            'is_all_in' => true,
            'rental_rate_snapshot' => 100000.00,
            'mob_cost_snapshot' => 500000.00,
            'demob_cost_snapshot' => 500000.00,
        ]);
        BookingUnitAssignment::factory()->create([
            'booking_detail_id' => $detail->id,
            'equipment_unit_id' => $unit->id,
            'status' => AssignmentStatus::ASSIGNED,
            'is_current' => true,
            'assigned_by' => $this->admin->id,
        ]);

        return [$booking, $unit];
    }

    public function test_two_rentals_lifecycle_are_independent(): void
    {
        [$bookingA] = $this->confirmedBookingWithUnit();
        [$bookingB] = $this->confirmedBookingWithUnit();

        Sanctum::actingAs($this->admin);
        $rentalA = $this->postJson('/api/v1/rentals', ['booking_id' => $bookingA->id])->assertCreated()->json('data');
        $rentalB = $this->postJson('/api/v1/rentals', ['booking_id' => $bookingB->id])->assertCreated()->json('data');

        $this->assertEquals('ASSIGNED', $rentalA['status']);
        $this->assertEquals('ASSIGNED', $rentalB['status']);
        // Each order references its OWN booking
        $this->assertEquals($bookingA->id, $rentalA['booking_id']);
        $this->assertEquals($bookingB->id, $rentalB['booking_id']);

        // ---- Advance ONLY rental A through full lifecycle ----
        foreach (['dispatch', 'arrive', 'start'] as $target) {
            $rentalA = $this->postJson("/api/v1/rentals/{$rentalA['id']}/{$target}")
                ->assertOk()
                ->json('data');
        }
        $this->assertEquals('ONGOING', $rentalA['status']);

        // ---- Rental B must remain ASSIGNED (untouched by A's transitions) ----
        $freshB = Rental::findOrFail($rentalB['id']);
        $this->assertEquals(RentalStatus::ASSIGNED, $freshB->status);
        $this->assertNull($freshB->started_at);

        // List endpoint returns both with their own distinct statuses
        $list = $this->getJson('/api/v1/rentals?per_page=50')->assertOk()->json('data');
        $statusById = collect($list)->keyBy('id')->map(fn ($r) => $r['status']);
        $this->assertEquals('ONGOING', $statusById[$rentalA['id']]);
        $this->assertEquals('ASSIGNED', $statusById[$rentalB['id']]);

        // ---- Complete rental B independently and verify A unaffected ----
        foreach (['dispatch', 'arrive', 'start', 'return', 'inspect'] as $target) {
            $rentalB = $this->postJson("/api/v1/rentals/{$rentalB['id']}/{$target}")
                ->assertOk()
                ->json('data');
        }
        $this->postJson("/api/v1/rentals/{$rentalB['id']}/ready", ['result' => 'READY'])
            ->assertOk();

        $this->assertEquals(RentalStatus::COMPLETED, Rental::findOrFail($rentalB['id'])->status);
        $this->assertEquals(RentalStatus::ONGOING, Rental::findOrFail($rentalA['id'])->status);
    }

    /**
     * Full real flow: booking APPROVED -> assign units -> MOB/DEMOB invoice
     * -> payment approved -> booking CONFIRMED + Rental auto-created.
     * Both orders must end up executable; each independent.
     */
    public function test_payment_approval_confirms_booking_and_creates_rental_for_both_orders(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        // Two orders, each APPROVED with one assigned unit.
        $orders = [];
        foreach ([1, 2] as $n) {
            $location = ProjectLocation::factory()->create(['user_id' => $this->user->id]);
            $model = EquipmentModel::factory()->create(['is_active' => true]);
            EquipmentPrice::factory()->create([
                'equipment_model_id' => $model->id,
                'is_all_in' => true,
                'base_rate' => 100000.00,
                'mob_cost' => 500000.00,
                'demob_cost' => 500000.00,
            ]);
            $unit = EquipmentUnit::factory()->create([
                'equipment_model_id' => $model->id,
                'status' => EquipmentStatus::AVAILABLE,
            ]);

            $booking = Booking::factory()->create([
                'user_id' => $this->user->id,
                'project_location_id' => $location->id,
                'status' => BookingStatus::PENDING_APPROVAL,
                'total_amount' => 500000.00,
                'supervisor_decision' => 'VALIDATED',
                'supervisor_validated_at' => now(),
            ]);
            $detail = BookingDetail::factory()->create([
                'booking_id' => $booking->id,
                'equipment_model_id' => $model->id,
                'quantity' => 1,
                'is_all_in' => true,
                'rental_rate_snapshot' => 100000.00,
                'overtime_rate_snapshot' => 120000.00,
                'mob_cost_snapshot' => 500000.00,
                'demob_cost_snapshot' => 500000.00,
                'subtotal' => 1000000.00,
            ]);

            $orders[] = ['booking' => $booking, 'detail' => $detail, 'unit' => $unit];
        }

        Sanctum::actingAs($this->admin);

        // Step 1: Admin approves BOTH (booking APPROVED — no assignment yet).
        foreach ($orders as $o) {
            $this->postJson("/api/v1/bookings/{$o['booking']->id}/approve")->assertOk();
        }

        // Step 2: assignment BEFORE payment is verified must be blocked.
        foreach ($orders as $o) {
            $this->postJson("/api/v1/bookings/{$o['booking']->id}/assign-units", [
                'assignments' => [[
                    'booking_detail_id' => $o['detail']->id,
                    'equipment_unit_id' => $o['unit']->id,
                ]],
            ])->assertStatus(409)->assertJson(['code' => 'INVALID_STATE_TRANSITION']);
        }

        // Step 3: Approval auto-issued MOB/DEMOB invoice.
        foreach ($orders as $o) {
            $invoice = Invoice::where('booking_id', $o['booking']->id)->first();
            $this->assertNotNull($invoice, 'MOB/DEMOB invoice harus terbit saat booking approved.');
            $this->assertEquals(1000000.00, (float) $invoice->grand_total);
        }

        // Step 4: Customer submits full payment proof (invoice still NOT settled).
        Sanctum::actingAs($this->user);
        $bank = BankAccount::factory()->create();
        $payments = [];
        foreach ($orders as $o) {
            $invoice = Invoice::where('booking_id', $o['booking']->id)->first();
            $res = $this->postJson("/api/v1/invoices/{$invoice->id}/payments", [
                'amount' => 1000000.00,
                'payment_date' => now()->toDateString(),
                'bank_account_id' => $bank->id,
                'reference' => 'TRF-'.$o['booking']->booking_code,
                'proof' => UploadedFile::fake()->image('proof.png'),
            ]);
            $res->assertCreated();
            $payments[] = Payment::findOrFail($res->json('data.id'));
        }

        // Step 5: proof uploaded but NOT yet verified — assignment still blocked.
        Sanctum::actingAs($this->admin);
        foreach ($orders as $o) {
            $this->postJson("/api/v1/bookings/{$o['booking']->id}/assign-units", [
                'assignments' => [[
                    'booking_detail_id' => $o['detail']->id,
                    'equipment_unit_id' => $o['unit']->id,
                ]],
            ])->assertStatus(409)->assertJson(['code' => 'INVALID_STATE_TRANSITION']);
        }

        Sanctum::actingAs($this->admin);
        foreach ($payments as $p) {
            $this->postJson("/api/v1/payments/{$p->id}/approve")->assertOk();
        }

        // Step 6: after payment verified, bookings become CONFIRMED and the
        // assignment gate unlocks (can_assign_units true).
        foreach ($orders as $o) {
            $book = Booking::findOrFail($o['booking']->id);
            $this->assertEquals(BookingStatus::CONFIRMED, $book->status);
            $this->assertNotNull($book->payment_met_at);
        }

        // Step 7: assign physical units on BOTH (now allowed).
        foreach ($orders as $o) {
            $this->postJson("/api/v1/bookings/{$o['booking']->id}/assign-units", [
                'assignments' => [[
                    'booking_detail_id' => $o['detail']->id,
                    'equipment_unit_id' => $o['unit']->id,
                ]],
            ])->assertOk();
        }

        // Step 8: both bookings now appear in Eksekusi Rental (per-booking, no first()).
        $list = $this->getJson('/api/v1/rentals?per_page=50')->assertOk()->json('data');
        $byBooking = collect($list)->keyBy('booking_id');
        $this->assertTrue($byBooking->has($orders[0]['booking']->id), 'Rental pesanan 1 harus muncul.');
        $this->assertTrue($byBooking->has($orders[1]['booking']->id), 'Rental pesanan 2 harus muncul.');
        $this->assertCount(2, $list);

        $this->assertEquals('ASSIGNED', $byBooking[$orders[0]['booking']->id]['status']);
        $this->assertEquals('ASSIGNED', $byBooking[$orders[1]['booking']->id]['status']);

        // Dispatch order 1 only; order 2 stays ASSIGNED.
        $rental1 = $byBooking[$orders[0]['booking']->id]['id'];
        $this->postJson("/api/v1/rentals/{$rental1}/dispatch")->assertOk();

        $list2 = $this->getJson('/api/v1/rentals?per_page=50')->assertOk()->json('data');
        $byBooking2 = collect($list2)->keyBy('booking_id');
        $this->assertEquals('DISPATCHED', $byBooking2[$orders[0]['booking']->id]['status']);
        $this->assertEquals('ASSIGNED', $byBooking2[$orders[1]['booking']->id]['status']);
    }
}