<?php

namespace Tests\Feature\Rental;

use App\Enums\AssignmentStatus;
use App\Enums\BookingStatus;
use App\Enums\EquipmentStatus;
use App\Enums\RentalStatus;
use App\Enums\UserRole;
use App\Models\Booking;
use App\Models\BookingDetail;
use App\Models\BookingUnitAssignment;
use App\Models\EquipmentModel;
use App\Models\EquipmentUnit;
use App\Models\ProjectLocation;
use App\Models\Rental;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RentalReturnInspectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Log::spy();
    }

    private function confirmedBookingWithUnit(): array
    {
        $user = User::factory()->create(['role' => UserRole::USER]);
        $admin = User::factory()->admin()->create();
        $location = ProjectLocation::factory()->create(['user_id' => $user->id]);

        $model = EquipmentModel::factory()->create(['is_active' => true]);
        $unit = EquipmentUnit::factory()->create([
            'equipment_model_id' => $model->id,
            'status' => EquipmentStatus::ASSIGNED,
        ]);

        $booking = Booking::factory()->create([
            'user_id' => $user->id,
            'project_location_id' => $location->id,
            'status' => BookingStatus::CONFIRMED,
        ]);
        $detail = BookingDetail::factory()->create([
            'booking_id' => $booking->id,
            'equipment_model_id' => $model->id,
            'quantity' => 1,
            'start_date' => now()->addDays(3)->toDateString(),
            'end_date' => now()->addDays(9)->toDateString(),
        ]);

        $assignment = BookingUnitAssignment::factory()->create([
            'booking_detail_id' => $detail->id,
            'equipment_unit_id' => $unit->id,
            'status' => AssignmentStatus::ASSIGNED,
            'is_current' => true,
            'assigned_by' => $admin->id,
        ]);

        return [$booking, $admin, $unit, $assignment];
    }

    private function driveToOnGoing(string $rentalId, string $token): void
    {
        foreach (['dispatch', 'arrive', 'start'] as $target) {
            $this->withToken($token)->postJson("/api/v1/rentals/{$rentalId}/{$target}")->assertStatus(200);
        }
    }

    private function adminToken(): string
    {
        return User::factory()->admin()->create()->createToken('test')->plainTextToken;
    }

    public function test_valid_return_transition_records_return_and_unit_not_available(): void
    {
        [$booking, , $unit] = $this->confirmedBookingWithUnit();
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);

        $rentalId = $this->postJson('/api/v1/rentals', ['booking_id' => $booking->id])->json('data.id');

        // Move to ONGOING
        $this->postJson("/api/v1/rentals/{$rentalId}/dispatch")->assertStatus(200);
        $this->postJson("/api/v1/rentals/{$rentalId}/arrive")->assertStatus(200);
        $this->postJson("/api/v1/rentals/{$rentalId}/start")->assertStatus(200);

        // RETURN -> DEMOBILIZING, unit DEMOBILIZING (NOT available)
        $returnRes = $this->postJson("/api/v1/rentals/{$rentalId}/return");
        $returnRes->assertStatus(200)->assertJson(['data' => ['status' => RentalStatus::DEMOBILIZING->value]]);
        $unit = $unit->fresh();
        $this->assertEquals(EquipmentStatus::DEMOBILIZING, $unit->status);
        $this->assertNotEquals(EquipmentStatus::AVAILABLE, $unit->status);
    }

    public function test_unit_not_available_until_inspection_declares_ready(): void
    {
        [$booking, , $unit] = $this->confirmedBookingWithUnit();
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);

        $rentalId = $this->postJson('/api/v1/rentals', ['booking_id' => $booking->id])->json('data.id');
        $this->driveToOnGoing($rentalId, $admin->createToken('t')->plainTextToken);

        // With token-less actingAs already the admin above; re-assert via Sanctum is fine.

        // Return + inspect
        $this->postJson("/api/v1/rentals/{$rentalId}/return")->assertStatus(200);
        $this->postJson("/api/v1/rentals/{$rentalId}/inspect")->assertStatus(200);

        // Still NOT available after return, until READY result.
        $rental = Rental::findOrFail($rentalId);
        $this->assertEquals(RentalStatus::RETURN_INSPECTED, $rental->fresh()->status);
        $this->assertNotEquals(EquipmentStatus::AVAILABLE, $unit->fresh()->status);

        // Hold: do not mark ready yet -> rental stays open, unit stays in inspection.
        $this->assertNull($rental->fresh()->completed_at);
        $this->assertEquals(EquipmentStatus::RETURN_INSPECTION, $unit->fresh()->status);
    }

    public function test_mark_ready_makes_unit_available_and_stores_inspection_history(): void
    {
        [$booking, , $unit] = $this->confirmedBookingWithUnit();
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);

        $rentalId = $this->postJson('/api/v1/rentals', ['booking_id' => $booking->id])->json('data.id');
        $this->driveToOnGoing($rentalId, $admin->createToken('t')->plainTextToken);
        $this->postJson("/api/v1/rentals/{$rentalId}/return")->assertStatus(200);
        $this->postJson("/api/v1/rentals/{$rentalId}/inspect")->assertStatus(200);

        $res = $this->postJson("/api/v1/rentals/{$rentalId}/ready", [
            'result' => 'READY',
            'condition_notes' => 'Unit layak pakai, tidak ada kerusakan.',
        ]);
        $res->assertStatus(200)->assertJson([
            'data' => ['status' => RentalStatus::COMPLETED->value],
        ]);

        $rental = Rental::findOrFail($rentalId);
        $this->assertNotNull($rental->completed_at);

        $detail = $rental->details()->firstOrFail();
        $this->assertEquals('READY', $detail->inspection_result);
        $this->assertEquals('Unit layak pakai, tidak ada kerusakan.', $detail->condition_notes);
        $this->assertNotNull($detail->checked_out_at);

        $this->assertEquals(EquipmentStatus::AVAILABLE, $unit->fresh()->status);
    }

    public function test_maintenance_result_puts_unit_in_maintenance_not_available(): void
    {
        [$booking, , $unit] = $this->confirmedBookingWithUnit();
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);

        $rentalId = $this->postJson('/api/v1/rentals', ['booking_id' => $booking->id])->json('data.id');
        $this->driveToOnGoing($rentalId, $admin->createToken('t')->plainTextToken);
        $this->postJson("/api/v1/rentals/{$rentalId}/return")->assertStatus(200);
        $this->postJson("/api/v1/rentals/{$rentalId}/inspect")->assertStatus(200);

        $res = $this->postJson("/api/v1/rentals/{$rentalId}/ready", [
            'result' => 'MAINTENANCE',
            'condition_notes' => 'Ganti oli mesin.',
        ]);
        $res->assertStatus(200)->assertJson(['data' => ['status' => RentalStatus::COMPLETED->value]]);

        $detail = Rental::findOrFail($rentalId)->details()->firstOrFail();
        $this->assertEquals('MAINTENANCE', $detail->inspection_result);
        $this->assertEquals(EquipmentStatus::MAINTENANCE, $unit->fresh()->status);
    }

    public function test_damaged_result_records_damage_without_automatic_charge(): void
    {
        [$booking, , $unit] = $this->confirmedBookingWithUnit();
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);

        $rentalId = $this->postJson('/api/v1/rentals', ['booking_id' => $booking->id])->json('data.id');
        $this->driveToOnGoing($rentalId, $admin->createToken('t')->plainTextToken);
        $this->postJson("/api/v1/rentals/{$rentalId}/return")->assertStatus(200);
        $this->postJson("/api/v1/rentals/{$rentalId}/inspect")->assertStatus(200);

        $res = $this->postJson("/api/v1/rentals/{$rentalId}/ready", [
            'result' => 'DAMAGED',
            'condition_notes' => 'Bucket retak pada bagian silinder.',
        ]);
        $res->assertStatus(200)->assertJson(['data' => ['status' => RentalStatus::COMPLETED->value]]);

        $detail = Rental::findOrFail($rentalId)->details()->firstOrFail();
        $this->assertEquals('DAMAGED', $detail->inspection_result);
        $this->assertEquals('Bucket retak pada bagian silinder.', $detail->condition_notes);

        // Damaged does NOT create an automatic charge/billing record.
        if (Schema::hasTable('invoices') && Schema::hasColumn('invoices', 'rental_id')) {
            $this->assertDatabaseMissing('invoices', ['rental_id' => $rentalId]);
        }
        if (Schema::hasTable('damage_charges')) {
            $this->assertDatabaseMissing('damage_charges', ['rental_detail_id' => $detail->id]);
        }
        $this->assertEquals(EquipmentStatus::MAINTENANCE, $unit->fresh()->status);
    }

    public function test_user_cannot_perform_return_inspection_or_ready(): void
    {
        [$booking, , $unit] = $this->confirmedBookingWithUnit();
        $user = User::factory()->create(['role' => UserRole::USER]);
        $admin = User::factory()->admin()->create();

        Sanctum::actingAs($admin);
        $rentalId = $this->postJson('/api/v1/rentals', ['booking_id' => $booking->id])->json('data.id');
        $this->driveToOnGoing($rentalId, $admin->createToken('t')->plainTextToken);

        Sanctum::actingAs($user);
        $this->postJson("/api/v1/rentals/{$rentalId}/return")->assertStatus(403);
        $this->postJson("/api/v1/rentals/{$rentalId}/inspect")->assertStatus(403);
        $this->postJson("/api/v1/rentals/{$rentalId}/ready", ['result' => 'READY'])->assertStatus(403);
    }

    public function test_ready_rejects_non_return_inspected_rental_and_invalid_result(): void
    {
        [$booking] = $this->confirmedBookingWithUnit();
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);

        $rentalId = $this->postJson('/api/v1/rentals', ['booking_id' => $booking->id])->json('data.id');

        // Rental still ASSIGNED: ready not allowed.
        $this->postJson("/api/v1/rentals/{$rentalId}/ready", ['result' => 'READY'])
            ->assertStatus(409)
            ->assertJson(['code' => 'INVALID_STATE_TRANSITION']);

        $this->postJson("/api/v1/rentals/{$rentalId}/ready", ['result' => 'SELL'])
            ->assertStatus(422);
    }
}
