<?php

namespace Tests\Feature\Recommendation;

use App\Enums\BookingStatus;
use App\Enums\EquipmentStatus;
use App\Enums\UserRole;
use App\Models\Booking;
use App\Models\BookingDetail;
use App\Models\EquipmentModel;
use App\Models\EquipmentPrice;
use App\Models\EquipmentType;
use App\Models\EquipmentUnit;
use App\Models\RecommendationRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RecommendationCriticalScenariosTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Skenario 1 & 2: Equipment cocok dan available masuk rekomendasi,
     * tetapi equipment cocok yang unavailable di rentang waktu tersebut dieksklusi.
     */
    public function test_scenario_available_equipment_included_and_unavailable_excluded(): void
    {
        $user = User::factory()->create(['role' => UserRole::USER]);
        Sanctum::actingAs($user);

        $type = EquipmentType::factory()->create(['name' => 'Hydraulic Excavator']);

        // Model 1: Excavator A (Cocok & Ada Unit Tersedia)
        $modelA = EquipmentModel::factory()->create([
            'equipment_type_id' => $type->id,
            'brand' => 'Komatsu',
            'model_name' => 'PC200-Avail',
            'capacity_value' => 20.00,
            'is_active' => true,
        ]);
        EquipmentPrice::factory()->create(['equipment_model_id' => $modelA->id]);
        EquipmentUnit::factory()->create([
            'equipment_model_id' => $modelA->id,
            'status' => EquipmentStatus::AVAILABLE,
        ]);

        // Model 2: Excavator B (Cocok tapi Sedang Tersewa / Booked Penuh di Tanggal Tersebut)
        $modelB = EquipmentModel::factory()->create([
            'equipment_type_id' => $type->id,
            'brand' => 'Kobelco',
            'model_name' => 'SK200-Booked',
            'capacity_value' => 20.00,
            'is_active' => true,
        ]);
        EquipmentPrice::factory()->create(['equipment_model_id' => $modelB->id]);
        $unitB = EquipmentUnit::factory()->create([
            'equipment_model_id' => $modelB->id,
            'status' => EquipmentStatus::AVAILABLE,
        ]);

        // Pasang booking aktif untuk Unit B pada 1-5 Oktober 2026
        $otherUser = User::factory()->create();
        $booking = Booking::factory()->create(['user_id' => $otherUser->id, 'status' => BookingStatus::CONFIRMED]);
        $detail = BookingDetail::factory()->create([
            'booking_id' => $booking->id,
            'equipment_model_id' => $modelB->id,
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-05',
        ]);
        DB::table('booking_unit_assignments')->insert([
            'booking_detail_id' => $detail->id,
            'equipment_unit_id' => $unitB->id,
            'status' => 'ASSIGNED',
            'is_current' => true,
            'assigned_by' => $otherUser->id,
        ]);

        // Request rekomendasi untuk periode yang sama (1-3 Oktober 2026)
        $response = $this->postJson('/api/v1/recommendations/request', [
            'project_type' => 'Galian Basah',
            'terrain_condition' => 'Lumpur / Rawa',
            'load_capacity' => 20.00,
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-03',
        ]);

        $response->assertStatus(201);
        $results = $response->json('data.results');
        $modelIds = collect($results)->pluck('equipment_model_id')->all();

        // Model A harus masuk, Model B harus dieksklusi karena bentrok jadwal
        $this->assertContains($modelA->id, $modelIds);
        $this->assertNotContains($modelB->id, $modelIds);
    }

    /**
     * Skenario 8 & 9: Rekomendasi dijamin tidak membuat booking,
     * tidak membuat reservasi, dan tidak mengubah status unit fisik.
     */
    public function test_scenario_recommendation_does_not_create_booking_or_mutate_unit_status(): void
    {
        $user = User::factory()->create(['role' => UserRole::USER]);
        Sanctum::actingAs($user);

        $type = EquipmentType::factory()->create(['name' => 'Bulldozer']);
        $model = EquipmentModel::factory()->create(['equipment_type_id' => $type->id, 'is_active' => true]);
        EquipmentPrice::factory()->create(['equipment_model_id' => $model->id]);
        $unit = EquipmentUnit::factory()->create([
            'equipment_model_id' => $model->id,
            'status' => EquipmentStatus::AVAILABLE,
        ]);

        $initialBookingsCount = Booking::count();
        $initialAssignmentsCount = DB::table('booking_unit_assignments')->count();

        $response = $this->postJson('/api/v1/recommendations/request', [
            'project_type' => 'Perataan Tanah',
            'terrain_condition' => 'Tanah Keras',
            'load_capacity' => 25.00,
        ]);

        $response->assertStatus(201);

        // Assert tidak ada booking baru yang terbuat
        $this->assertEquals($initialBookingsCount, Booking::count());
        $this->assertEquals($initialAssignmentsCount, DB::table('booking_unit_assignments')->count());

        // Assert status unit tetap AVAILABLE tanpa perubahan
        $this->assertEquals(EquipmentStatus::AVAILABLE, $unit->fresh()->status);
    }

    /**
     * Skenario 7: User tidak dapat mengintip hasil rekomendasi milik user lain.
     */
    public function test_scenario_user_cannot_access_other_users_recommendation(): void
    {
        $userA = User::factory()->create(['role' => UserRole::USER]);
        $userB = User::factory()->create(['role' => UserRole::USER]);

        $reqB = RecommendationRequest::factory()->create(['user_id' => $userB->id]);

        Sanctum::actingAs($userA);

        $this->getJson("/api/v1/recommendations/{$reqB->id}")->assertStatus(403);
    }
}
