<?php

namespace Tests\Feature\Equipment\Pricing;

use App\Enums\UserRole;
use App\Models\EquipmentModel;
use App\Models\EquipmentPrice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EquipmentPriceApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_create_master_price_and_initial_version_is_recorded(): void
    {
        $owner = User::factory()->owner()->create();
        $model = EquipmentModel::factory()->create();

        Sanctum::actingAs($owner);

        $payload = [
            'equipment_model_id' => $model->id,
            'price_type' => 'HOURLY',
            'is_all_in' => false,
            'base_rate' => 250000.00,
            'minimum_hours' => 8,
            'overtime_rate' => 300000.00,
            'effective_date' => '2026-10-01',
        ];

        $response = $this->postJson('/api/v1/equipment/prices', $payload);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'base_rate',
                    'is_all_in',
                    'versions' => [
                        '*' => ['id', 'old_base_rate', 'new_base_rate', 'changed_by'],
                    ],
                ],
            ]);

        $priceId = $response->json('data.id');

        $this->assertDatabaseHas('equipment_prices', [
            'id' => $priceId,
            'base_rate' => 250000.00,
        ]);

        $this->assertDatabaseHas('equipment_price_versions', [
            'equipment_price_id' => $priceId,
            'old_base_rate' => 0.00,
            'new_base_rate' => 250000.00,
            'changed_by' => $owner->id,
        ]);
    }

    public function test_owner_can_update_price_and_new_immutable_version_is_appended(): void
    {
        $owner = User::factory()->owner()->create();
        $price = EquipmentPrice::factory()->create(['base_rate' => 250000.00]);

        Sanctum::actingAs($owner);

        $response = $this->putJson("/api/v1/equipment/prices/{$price->id}", [
            'base_rate' => 300000.00,
            'minimum_hours' => 8,
            'overtime_rate' => 350000.00,
            'effective_date' => '2026-11-01',
        ]);

        $response->assertStatus(200);

        $this->assertEquals(300000.00, $price->fresh()->base_rate);

        // Assert new version created with old and new base rate
        $this->assertDatabaseHas('equipment_price_versions', [
            'equipment_price_id' => $price->id,
            'old_base_rate' => 250000.00,
            'new_base_rate' => 300000.00,
            'changed_by' => $owner->id,
        ]);
    }

    public function test_regular_user_cannot_create_or_update_master_price(): void
    {
        $user = User::factory()->create(['role' => UserRole::USER]);
        $model = EquipmentModel::factory()->create();

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/equipment/prices', [
            'equipment_model_id' => $model->id,
            'price_type' => 'HOURLY',
            'is_all_in' => false,
            'base_rate' => 250000.00,
            'minimum_hours' => 8,
            'overtime_rate' => 300000.00,
            'effective_date' => '2026-10-01',
        ]);

        $response->assertStatus(403);
    }

    public function test_admin_and_owner_can_list_and_view_price_version_history(): void
    {
        $admin = User::factory()->admin()->create();
        $owner = User::factory()->owner()->create();
        $price = EquipmentPrice::factory()->create(['base_rate' => 200000.00]);

        $price->versions()->create([
            'old_base_rate' => 150000.00,
            'new_base_rate' => 200000.00,
            'changed_by' => $owner->id,
            'changed_at' => now(),
        ]);

        Sanctum::actingAs($admin);

        $response = $this->getJson("/api/v1/equipment/prices/{$price->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $price->id,
                    'base_rate' => 200000.00,
                ],
            ]);

        $this->assertCount(1, $response->json('data.versions'));
    }

    public function test_admin_and_owner_can_list_prices(): void
    {
        $admin = User::factory()->admin()->create();
        $owner = User::factory()->owner()->create();
        EquipmentPrice::factory()->count(3)->create();

        Sanctum::actingAs($admin);
        $this->getJson('/api/v1/equipment/prices')->assertStatus(200);

        Sanctum::actingAs($owner);
        $this->getJson('/api/v1/equipment/prices')->assertStatus(200);
    }

    public function test_admin_cannot_create_or_update_master_price(): void
    {
        $admin = User::factory()->admin()->create();
        $model = EquipmentModel::factory()->create();
        $price = EquipmentPrice::factory()->create();

        Sanctum::actingAs($admin);

        $this->postJson('/api/v1/equipment/prices', [
            'equipment_model_id' => $model->id,
            'price_type' => 'HOURLY',
            'is_all_in' => false,
            'base_rate' => 250000.00,
            'minimum_hours' => 8,
            'overtime_rate' => 300000.00,
            'effective_date' => '2026-10-01',
        ])->assertStatus(403);

        $this->putJson("/api/v1/equipment/prices/{$price->id}", [
            'base_rate' => 300000.00,
            'minimum_hours' => 8,
            'overtime_rate' => 350000.00,
            'effective_date' => '2026-11-01',
        ])->assertStatus(403);
    }

    public function test_regular_user_cannot_update_master_price(): void
    {
        $user = User::factory()->create(['role' => UserRole::USER]);
        $price = EquipmentPrice::factory()->create();

        Sanctum::actingAs($user);

        $this->putJson("/api/v1/equipment/prices/{$price->id}", [
            'base_rate' => 300000.00,
            'minimum_hours' => 8,
            'overtime_rate' => 350000.00,
            'effective_date' => '2026-11-01',
        ])->assertStatus(403);
    }
}
