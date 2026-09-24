<?php

namespace Tests\Feature\Domain;

use App\Enums\EquipmentStatus;
use App\Enums\UserRole;
use App\Models\EquipmentModel;
use App\Models\EquipmentUnit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EquipmentUnitApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_list_and_filter_equipment_units(): void
    {
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);

        $model1 = EquipmentModel::factory()->create();
        $model2 = EquipmentModel::factory()->create();

        EquipmentUnit::factory()->create([
            'equipment_model_id' => $model1->id,
            'serial_number' => 'KM-PC200-01',
            'plate_number' => 'B 1111 RFA',
            'status' => EquipmentStatus::AVAILABLE,
        ]);

        EquipmentUnit::factory()->create([
            'equipment_model_id' => $model2->id,
            'serial_number' => 'CAT-320D-01',
            'plate_number' => 'B 2222 RFA',
            'status' => EquipmentStatus::MAINTENANCE,
        ]);

        // Filter by model
        $resModel = $this->getJson("/api/v1/equipment/units?equipment_model_id={$model1->id}");
        $resModel->assertStatus(200);
        $this->assertCount(1, $resModel->json('data'));
        $this->assertEquals('KM-PC200-01', $resModel->json('data.0.serial_number'));

        // Filter by status
        $resStatus = $this->getJson('/api/v1/equipment/units?status=MAINTENANCE');
        $resStatus->assertStatus(200);
        $this->assertCount(1, $resStatus->json('data'));
        $this->assertEquals('CAT-320D-01', $resStatus->json('data.0.serial_number'));

        // Search by plate number
        $resSearch = $this->getJson('/api/v1/equipment/units?search=1111');
        $resSearch->assertStatus(200);
        $this->assertCount(1, $resSearch->json('data'));
        $this->assertEquals('B 1111 RFA', $resSearch->json('data.0.plate_number'));
    }

    public function test_regular_user_cannot_access_equipment_units_endpoint(): void
    {
        $user = User::factory()->create(['role' => UserRole::USER]);
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/equipment/units')->assertStatus(403);
    }

    public function test_admin_can_create_equipment_unit(): void
    {
        $admin = User::factory()->admin()->create();
        $model = EquipmentModel::factory()->create();
        Sanctum::actingAs($admin);

        $payload = [
            'equipment_model_id' => $model->id,
            'serial_number' => 'KM-PC200-999',
            'plate_number' => 'B 9999 RFA',
            'last_hour_meter' => 1500.50,
            'year_of_make' => 2022,
        ];

        $response = $this->postJson('/api/v1/equipment/units', $payload);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'serial_number' => 'KM-PC200-999',
                    'plate_number' => 'B 9999 RFA',
                    'status' => 'AVAILABLE',
                    'last_hour_meter' => 1500.50,
                ],
            ]);

        $this->assertDatabaseHas('equipment_units', [
            'serial_number' => 'KM-PC200-999',
            'status' => 'AVAILABLE',
        ]);
    }

    public function test_cannot_create_unit_with_duplicate_serial_or_plate(): void
    {
        $admin = User::factory()->admin()->create();
        $model = EquipmentModel::factory()->create();
        EquipmentUnit::factory()->create([
            'equipment_model_id' => $model->id,
            'serial_number' => 'SN-DUP-01',
            'plate_number' => 'B 8888 DUP',
        ]);

        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/v1/equipment/units', [
            'equipment_model_id' => $model->id,
            'serial_number' => 'SN-DUP-01',
            'plate_number' => 'B 8888 DUP',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['serial_number', 'plate_number']);
    }

    public function test_admin_can_update_equipment_unit(): void
    {
        $admin = User::factory()->admin()->create();
        $unit = EquipmentUnit::factory()->create([
            'serial_number' => 'OLD-SERIAL',
            'last_hour_meter' => 100.00,
        ]);

        Sanctum::actingAs($admin);

        $response = $this->putJson("/api/v1/equipment/units/{$unit->id}", [
            'serial_number' => 'NEW-SERIAL-01',
            'last_hour_meter' => 150.25,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'serial_number' => 'NEW-SERIAL-01',
                    'last_hour_meter' => 150.25,
                ],
            ]);

        $this->assertDatabaseHas('equipment_units', [
            'id' => $unit->id,
            'serial_number' => 'NEW-SERIAL-01',
        ]);
    }

    public function test_admin_can_transition_unit_to_maintenance_and_back_to_available(): void
    {
        $admin = User::factory()->admin()->create();
        $unit = EquipmentUnit::factory()->create(['status' => EquipmentStatus::AVAILABLE]);

        Sanctum::actingAs($admin);

        // 1. Move to MAINTENANCE
        $resMaint = $this->postJson("/api/v1/equipment/units/{$unit->id}/status", [
            'status' => 'MAINTENANCE',
            'notes' => 'Servis berkala 500 jam',
        ]);
        $resMaint->assertStatus(200);
        $this->assertEquals('MAINTENANCE', $unit->fresh()->status->value);

        // 2. Move back to AVAILABLE
        $resAvail = $this->postJson("/api/v1/equipment/units/{$unit->id}/status", [
            'status' => 'AVAILABLE',
            'notes' => 'Selesai servis dan uji teknis',
        ]);
        $resAvail->assertStatus(200);
        $this->assertEquals('AVAILABLE', $unit->fresh()->status->value);
    }

    public function test_cannot_manually_override_status_of_unit_in_active_rental(): void
    {
        $admin = User::factory()->admin()->create();
        $unit = EquipmentUnit::factory()->create(['status' => EquipmentStatus::ON_SITE]);

        Sanctum::actingAs($admin);

        $response = $this->postJson("/api/v1/equipment/units/{$unit->id}/status", [
            'status' => 'AVAILABLE',
            'notes' => 'Illegal override attempt',
        ]);

        $response->assertStatus(409)
            ->assertJson([
                'code' => 'INVALID_STATE_TRANSITION',
            ]);

        $this->assertEquals('ON_SITE', $unit->fresh()->status->value);
    }

    public function test_cannot_delete_unit_not_in_available_or_decommissioned_state(): void
    {
        $admin = User::factory()->admin()->create();
        $unit = EquipmentUnit::factory()->create(['status' => EquipmentStatus::MAINTENANCE]);

        Sanctum::actingAs($admin);

        $response = $this->deleteJson("/api/v1/equipment/units/{$unit->id}");

        $response->assertStatus(409)
            ->assertJson(['code' => 'CONFLICT']);
    }

    public function test_admin_can_delete_available_unit(): void
    {
        $admin = User::factory()->admin()->create();
        $unit = EquipmentUnit::factory()->create(['status' => EquipmentStatus::AVAILABLE]);

        Sanctum::actingAs($admin);

        $response = $this->deleteJson("/api/v1/equipment/units/{$unit->id}");

        $response->assertStatus(200);
        $this->assertSoftDeleted('equipment_units', ['id' => $unit->id]);
    }
}
