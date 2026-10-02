<?php

namespace Tests\Feature\Equipment;

use App\Enums\EquipmentStatus;
use App\Models\EquipmentModel;
use App\Models\EquipmentUnit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CatalogAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_reports_real_available_units_count(): void
    {
        $user = \App\Models\User::factory()->create();
        $model = EquipmentModel::factory()->create(['is_active' => true]);

        EquipmentUnit::factory()->count(4)->create([
            'equipment_model_id' => $model->id,
            'status' => EquipmentStatus::AVAILABLE,
        ]);
        EquipmentUnit::factory()->create([
            'equipment_model_id' => $model->id,
            'status' => EquipmentStatus::MAINTENANCE,
        ]);

        Sanctum::actingAs($user);
        $res = $this->getJson('/api/v1/equipment/models');

        $res->assertOk();
        $catalog = collect($res->json('data'))->firstWhere('id', $model->id);
        $this->assertEquals(4, $catalog['available_units_count']);
    }

    public function test_detail_reports_available_units_count(): void
    {
        $user = \App\Models\User::factory()->create();
        $model = EquipmentModel::factory()->create(['is_active' => true]);

        EquipmentUnit::factory()->count(2)->create([
            'equipment_model_id' => $model->id,
            'status' => EquipmentStatus::AVAILABLE,
        ]);
        EquipmentUnit::factory()->create([
            'equipment_model_id' => $model->id,
            'status' => EquipmentStatus::DECOMMISSIONED,
        ]);

        Sanctum::actingAs($user);
        $res = $this->getJson("/api/v1/equipment/models/{$model->id}");

        $res->assertOk()
            ->assertJsonPath('data.available_units_count', 2);
    }
}