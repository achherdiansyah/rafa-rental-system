<?php

namespace Tests\Feature\Domain;

use App\Enums\UserRole;
use App\Models\EquipmentModel;
use App\Models\EquipmentType;
use App\Models\EquipmentUnit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EquipmentTypeAndModelApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_can_list_and_search_equipment_types(): void
    {
        EquipmentType::factory()->create(['name' => 'Excavator Crawler']);
        EquipmentType::factory()->create(['name' => 'Bulldozer Heavy']);

        $response = $this->getJson('/api/v1/equipment/types?search=Excavator');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => ['id', 'name', 'description'],
                ],
            ]);

        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('Excavator Crawler', $response->json('data.0.name'));
    }

    public function test_public_can_list_and_filter_equipment_models(): void
    {
        $type1 = EquipmentType::factory()->create(['name' => 'Excavator']);
        $type2 = EquipmentType::factory()->create(['name' => 'Bulldozer']);

        EquipmentModel::factory()->create([
            'equipment_type_id' => $type1->id,
            'brand' => 'Komatsu',
            'model_name' => 'PC200-8',
            'is_active' => true,
        ]);

        EquipmentModel::factory()->create([
            'equipment_type_id' => $type2->id,
            'brand' => 'Caterpillar',
            'model_name' => 'D85ESS',
            'is_active' => true,
        ]);

        // Filter by type
        $responseType = $this->getJson("/api/v1/equipment/models?equipment_type_id={$type1->id}");
        $responseType->assertStatus(200);
        $this->assertCount(1, $responseType->json('data'));
        $this->assertEquals('PC200-8', $responseType->json('data.0.model_name'));

        // Filter by brand
        $responseBrand = $this->getJson('/api/v1/equipment/models?brand=Caterpillar');
        $responseBrand->assertStatus(200);
        $this->assertCount(1, $responseBrand->json('data'));
        $this->assertEquals('D85ESS', $responseBrand->json('data.0.model_name'));
    }

    public function test_admin_can_create_equipment_type(): void
    {
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);

        $payload = [
            'name' => 'Motor Grader',
            'description' => 'Alat berat untuk meratakan jalan tanah dan gravel',
        ];

        $response = $this->postJson('/api/v1/equipment/types', $payload);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'name' => 'Motor Grader',
                ],
            ]);

        $this->assertDatabaseHas('equipment_types', ['name' => 'Motor Grader']);
    }

    public function test_user_cannot_create_equipment_type(): void
    {
        $user = User::factory()->create(['role' => UserRole::USER]);
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/equipment/types', [
            'name' => 'Illegal Type',
        ]);

        $response->assertStatus(403);
    }

    public function test_admin_can_create_equipment_model(): void
    {
        $admin = User::factory()->admin()->create();
        $type = EquipmentType::factory()->create(['name' => 'Excavator']);

        Sanctum::actingAs($admin);

        $payload = [
            'equipment_type_id' => $type->id,
            'brand' => 'Kobelco',
            'model_name' => 'SK200-10',
            'capacity_value' => 20.50,
            'capacity_unit' => 'Ton',
            'is_active' => true,
        ];

        $response = $this->postJson('/api/v1/equipment/models', $payload);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'brand' => 'Kobelco',
                    'model_name' => 'SK200-10',
                    'capacity_value' => 20.50,
                ],
            ]);

        $this->assertDatabaseHas('equipment_models', [
            'model_name' => 'SK200-10',
            'equipment_type_id' => $type->id,
        ]);
    }

    public function test_cannot_create_model_with_invalid_type_id(): void
    {
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/v1/equipment/models', [
            'equipment_type_id' => 999999, // Non-existent
            'brand' => 'Komatsu',
            'model_name' => 'PC300',
            'capacity_value' => 30,
            'capacity_unit' => 'Ton',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['equipment_type_id']);
    }

    public function test_cannot_create_duplicate_model_name(): void
    {
        $admin = User::factory()->admin()->create();
        $type = EquipmentType::factory()->create();
        EquipmentModel::factory()->create(['model_name' => 'PC200-8', 'equipment_type_id' => $type->id]);

        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/v1/equipment/models', [
            'equipment_type_id' => $type->id,
            'brand' => 'Komatsu',
            'model_name' => 'PC200-8',
            'capacity_value' => 20,
            'capacity_unit' => 'Ton',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['model_name']);
    }

    public function test_admin_can_update_equipment_model(): void
    {
        $admin = User::factory()->admin()->create();
        $model = EquipmentModel::factory()->create(['brand' => 'Komatsu', 'model_name' => 'PC200-7']);

        Sanctum::actingAs($admin);

        $response = $this->putJson("/api/v1/equipment/models/{$model->id}", [
            'model_name' => 'PC200-8 Updated',
            'capacity_value' => 21.00,
            'capacity_unit' => 'Ton',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'model_name' => 'PC200-8 Updated',
                ],
            ]);

        $this->assertDatabaseHas('equipment_models', [
            'id' => $model->id,
            'model_name' => 'PC200-8 Updated',
        ]);
    }

    public function test_cannot_delete_equipment_type_if_models_exist(): void
    {
        $admin = User::factory()->admin()->create();
        $type = EquipmentType::factory()->create();
        EquipmentModel::factory()->create(['equipment_type_id' => $type->id]);

        Sanctum::actingAs($admin);

        $response = $this->deleteJson("/api/v1/equipment/types/{$type->id}");

        $response->assertStatus(409)
            ->assertJson(['code' => 'CONFLICT']);

        $this->assertDatabaseHas('equipment_types', ['id' => $type->id, 'deleted_at' => null]);
    }

    public function test_cannot_delete_equipment_model_if_units_exist(): void
    {
        $admin = User::factory()->admin()->create();
        $model = EquipmentModel::factory()->create();
        EquipmentUnit::factory()->create(['equipment_model_id' => $model->id]);

        Sanctum::actingAs($admin);

        $response = $this->deleteJson("/api/v1/equipment/models/{$model->id}");

        $response->assertStatus(409)
            ->assertJson(['code' => 'CONFLICT']);

        $this->assertDatabaseHas('equipment_models', ['id' => $model->id, 'deleted_at' => null]);
    }

    public function test_admin_can_delete_empty_equipment_model(): void
    {
        $admin = User::factory()->admin()->create();
        $model = EquipmentModel::factory()->create();

        Sanctum::actingAs($admin);

        $response = $this->deleteJson("/api/v1/equipment/models/{$model->id}");

        $response->assertStatus(200);
        $this->assertSoftDeleted('equipment_models', ['id' => $model->id]);
    }
}
