<?php

namespace Tests\Feature\Domain;

use App\Enums\BookingStatus;
use App\Models\CustomerProfile;
use App\Models\EquipmentModel;
use App\Models\EquipmentPrice;
use App\Models\EquipmentUnit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Pre-Phase 14 (USER-AUDIT-01): ensure the booking state machine has no
 * dead/alias state. Source of truth T-B01: DRAFT -> PENDING_APPROVAL
 * (SUBMITTED is only a phase label and never a persisted value).
 */
class BookingStatusConsistencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_submitted_is_not_an_existing_booking_status(): void
    {
        $values = array_column(BookingStatus::cases(), 'value');
        $this->assertNotContains('SUBMITTED', $values);
    }

    public function test_submit_lands_on_pending_approval_not_submitted(): void
    {
        $user = User::factory()->create();
        CustomerProfile::factory()->for($user)->create(['verification_status' => 'VERIFIED']);

        $model = EquipmentModel::factory()->create(['is_active' => true]);
        EquipmentPrice::factory()->create([
            'equipment_model_id' => $model->id,
            'is_all_in' => true,
            'base_rate' => 150000.0,
            'mob_cost' => 0,
            'demob_cost' => 0,
            'effective_date' => now()->subMonth()->toDateString(),
        ]);
        EquipmentUnit::factory()->create(['equipment_model_id' => $model->id, 'status' => 'AVAILABLE']);

        Sanctum::actingAs($user);

        $projectId = $this->postJson('/api/v1/project-locations', [
            'project_name' => 'Audit SOT',
            'city' => 'Jakarta',
            'pic_name' => 'A',
            'pic_phone' => '0812',
            'address' => 'Jl. Tes',
        ])->assertCreated()->json('data.id');

        $this->postJson('/api/v1/cart/items', [
            'equipment_model_id' => $model->id,
            'quantity' => 1,
            'start_date' => now()->addDays(2)->toDateString(),
            'end_date' => now()->addDays(5)->toDateString(),
            'is_all_in' => true,
        ])->assertCreated();
        $this->putJson('/api/v1/cart/location', ['project_location_id' => $projectId])->assertOk();

        $bookingId = $this->postJson('/api/v1/bookings', [])->assertCreated()->json('data.id');
        $this->postJson("/api/v1/bookings/{$bookingId}/submit")->assertOk();

        $fresh = (object) \App\Models\Booking::find($bookingId)->toArray();
        $this->assertEquals(BookingStatus::PENDING_APPROVAL->value, $fresh->status);

        // Nothing anywhere may persist the alias value
        $this->assertDatabaseMissing('bookings', ['id' => $bookingId, 'status' => 'SUBMITTED']);
    }
}