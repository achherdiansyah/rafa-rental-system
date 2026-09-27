<?php

namespace Tests\Feature\Booking;

use App\Enums\AssignmentStatus;
use App\Enums\BookingStatus;
use App\Enums\EquipmentStatus;
use App\Models\Booking;
use App\Models\BookingDetail;
use App\Models\BookingUnitAssignment;
use App\Models\Cart;
use App\Models\CustomerProfile;
use App\Models\EquipmentModel;
use App\Models\EquipmentPrice;
use App\Models\EquipmentUnit;
use App\Models\ProjectLocation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BookingWorkflowIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Log::spy();
    }

    private function makeFleet(int $units = 2): array
    {
        $model = EquipmentModel::factory()->create(['is_active' => true]);
        EquipmentPrice::factory()->create(['equipment_model_id' => $model->id, 'base_rate' => 250000]);
        $unitsList = EquipmentUnit::factory()->count($units)->create([
            'equipment_model_id' => $model->id,
            'status' => EquipmentStatus::AVAILABLE,
        ]);

        return [$model, $unitsList];
    }

    public function test_full_user_and_admin_booking_workflow(): void
    {
        $user = User::factory()->create();
        CustomerProfile::factory()->verified()->create(['user_id' => $user->id]);
        $admin = User::factory()->admin()->create();

        // 1. USER: project location
        Sanctum::actingAs($user);
        $locRes = $this->postJson('/api/v1/project-locations', [
            'project_name' => 'Tol Cisauk Raya',
            'address' => 'KM 12 Tol Serpong',
            'city' => 'Tangerang',
            'pic_name' => 'Budi',
            'pic_phone' => '08123',
        ]);
        $locRes->assertStatus(201);
        $locationId = $locRes->json('data.id');

        // 2. USER: add to cart
        [$model] = $this->makeFleet(3);
        $start = now()->addDays(10)->toDateString();
        $end = now()->addDays(15)->toDateString();

        $this->postJson('/api/v1/cart/items', [
            'equipment_model_id' => $model->id,
            'quantity' => 2,
            'is_all_in' => false,
            'start_date' => $start,
            'end_date' => $end,
            'project_location_id' => $locationId,
        ])->assertStatus(201);

        // 3. USER: create booking DRAFT from cart
        $bookingRes = $this->postJson('/api/v1/bookings');
        $bookingRes->assertStatus(201);
        $bookingId = $bookingRes->json('data.id');
        $this->assertEquals(BookingStatus::DRAFT->value, $bookingRes->json('data.status'));

        // Cart consumed
        $this->assertDatabaseCount('cart_items', 0);

        // 4. USER: submit → PENDING_APPROVAL
        $this->postJson("/api/v1/bookings/{$bookingId}/submit")
            ->assertStatus(200)
            ->assertJson(['data' => ['status' => BookingStatus::PENDING_APPROVAL->value]]);

        // 5. ADMIN: approve → APPROVED (deadline set)
        Sanctum::actingAs($admin);
        $approveRes = $this->postJson("/api/v1/bookings/{$bookingId}/approve");
        $approveRes->assertStatus(200)
            ->assertJson(['data' => ['status' => BookingStatus::APPROVED->value]]);
        $this->assertNotNull($approveRes->json('data.payment_deadline_at'));

        // 6. ADMIN: assign 2 physical units
        $units = EquipmentUnit::where('equipment_model_id', $model->id)->where('status', EquipmentStatus::AVAILABLE)->limit(2)->get();
        $booking = Booking::find($bookingId)->load('details');
        $detailId = $booking->details->first()->id;

        $this->postJson("/api/v1/bookings/{$bookingId}/assign-units", [
            'assignments' => $units->map(fn ($u) => ['booking_detail_id' => $detailId, 'equipment_unit_id' => $u->id])->all(),
        ])->assertStatus(200);

        $this->assertDatabaseCount('booking_unit_assignments', 2);
        foreach ($units as $u) {
            $this->assertEquals(EquipmentStatus::ASSIGNED, $u->fresh()->status);
        }

        // 7. Conflict: second booking requesting the same window must be rejected at draft
        Sanctum::actingAs($user);
        $loc2 = ProjectLocation::factory()->create(['user_id' => $user->id]);
        $this->postJson('/api/v1/cart/items', [
            'equipment_model_id' => $model->id,
            'quantity' => 2,
            'is_all_in' => false,
            'start_date' => $start,
            'end_date' => $end,
            'project_location_id' => $loc2->id,
        ])->assertStatus(201);

        $this->postJson('/api/v1/bookings')->assertStatus(409);
    }

    public function test_expiry_releases_units_then_cancellation_path_is_terminal(): void
    {
        [$model, $units] = $this->makeFleet(1);
        $user = User::factory()->create();
        $admin = User::factory()->admin()->create();

        // Booking APPROVED, deadline already in the past
        $location = ProjectLocation::factory()->create(['user_id' => $user->id]);
        $booking = Booking::factory()->create([
            'user_id' => $user->id,
            'project_location_id' => $location->id,
            'status' => BookingStatus::APPROVED,
            'approved_at' => now()->subHours(30),
            'payment_deadline_at' => now()->subHours(6),
        ]);
        $detail = BookingDetail::factory()->create([
            'booking_id' => $booking->id,
            'equipment_model_id' => $model->id,
            'quantity' => 1,
            'start_date' => now()->addDays(5)->toDateString(),
            'end_date' => now()->addDays(9)->toDateString(),
        ]);
        BookingUnitAssignment::factory()->create([
            'booking_detail_id' => $detail->id,
            'equipment_unit_id' => $units[0]->id,
            'is_current' => true,
            'assigned_by' => $admin->id,
        ]);
        $units[0]->update(['status' => EquipmentStatus::ASSIGNED]);

        // Scheduler expiry
        $this->artisan('bookings:expire')->assertSuccessful();
        $this->assertEquals(BookingStatus::EXPIRED, $booking->fresh()->status);
        $this->assertEquals(EquipmentStatus::AVAILABLE, $units[0]->fresh()->status);

        // EXPIRED is terminal: further cancel/reschedule blocked
        Sanctum::actingAs($admin);
        $this->postJson("/api/v1/bookings/{$booking->id}/cancel", ['reason' => 'Gagal batalkan booking expired.'])
            ->assertStatus(409)
            ->assertJson(['code' => 'INVALID_STATE_TRANSITION']);
    }

    public function test_reschedule_then_replacement_full_flow(): void
    {
        [$model, $units] = $this->makeFleet(3);
        $user = User::factory()->create();
        $admin = User::factory()->admin()->create();

        $location = ProjectLocation::factory()->create(['user_id' => $user->id]);
        $booking = Booking::factory()->create([
            'user_id' => $user->id,
            'project_location_id' => $location->id,
            'status' => BookingStatus::APPROVED,
        ]);
        $detail = BookingDetail::factory()->create([
            'booking_id' => $booking->id,
            'equipment_model_id' => $model->id,
            'quantity' => 1,
            'start_date' => now()->addDays(3)->toDateString(),
            'end_date' => now()->addDays(7)->toDateString(),
        ]);
        $assignment = BookingUnitAssignment::factory()->create([
            'booking_detail_id' => $detail->id,
            'equipment_unit_id' => $units[0]->id,
            'is_current' => true,
            'assigned_by' => $admin->id,
        ]);
        $units[0]->update(['status' => EquipmentStatus::ASSIGNED]);

        // 1. RESCHEDULE (USER): requires admin approval again
        Sanctum::actingAs($user);
        $newStart = now()->addDays(20)->toDateString();
        $newEnd = now()->addDays(24)->toDateString();
        $this->postJson("/api/v1/bookings/{$booking->id}/reschedule", [
            'new_start_date' => $newStart,
            'new_end_date' => $newEnd,
            'reason' => 'Penjadwalan proyek bergeser satu pekan.',
        ])->assertStatus(200)
            ->assertJson(['data' => ['status' => BookingStatus::PENDING_APPROVAL->value]]);

        $fresh = $booking->fresh();
        $this->assertNotEmpty($fresh->reschedule_history);
        $this->assertEquals($newStart, $fresh->details->first()->start_date->toDateString());

        // Admin re-approves
        Sanctum::actingAs($admin);
        $this->postJson("/api/v1/bookings/{$booking->id}/approve")->assertStatus(200);

        // 2. REPLACEMENT (ADMIN): swap unit0 → unit1
        $this->postJson("/api/v1/bookings/{$booking->id}/assignments/{$assignment->id}/replace", [
            'new_equipment_unit_id' => $units[1]->id,
            'reason' => 'Unit awal terindikasi kerusakan pre-dispatch.',
        ])->assertStatus(200);

        $this->assertEquals(AssignmentStatus::REPLACED, $assignment->fresh()->status);
        $this->assertEquals(EquipmentStatus::MAINTENANCE, $units[0]->fresh()->status);
        $this->assertEquals(EquipmentStatus::ASSIGNED, $units[1]->fresh()->status);
        $newAssignment = BookingUnitAssignment::where('booking_detail_id', $detail->id)->where('is_current', true)->first();
        $this->assertEquals($units[1]->id, $newAssignment->equipment_unit_id);
    }
}
