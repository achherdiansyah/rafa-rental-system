<?php

namespace Tests\Feature\Domain;

use App\Enums\EquipmentStatus;
use App\Models\EquipmentModel;
use App\Models\EquipmentType;
use App\Models\EquipmentUnit;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EquipmentDomainTest extends TestCase
{
    use RefreshDatabase;

    public function test_type_has_many_models(): void
    {
        $type = EquipmentType::factory()->create();
        $m1 = EquipmentModel::factory()->create(['equipment_type_id' => $type->id]);
        $m2 = EquipmentModel::factory()->create(['equipment_type_id' => $type->id]);

        $this->assertCount(2, $type->models);
        $this->assertTrue($m1->type->is($type));
    }

    public function test_model_has_many_units(): void
    {
        $model = EquipmentModel::factory()->create();
        $u1 = EquipmentUnit::factory()->create(['equipment_model_id' => $model->id]);
        $u2 = EquipmentUnit::factory()->create(['equipment_model_id' => $model->id]);

        $this->assertCount(2, $model->units);
        $this->assertTrue($u1->model->is($model));
    }

    public function test_unit_serial_number_is_unique(): void
    {
        EquipmentUnit::factory()->create(['serial_number' => 'SN-UNIQUE-001']);

        $this->expectException(QueryException::class);
        EquipmentUnit::factory()->create(['serial_number' => 'SN-UNIQUE-001']);
    }

    public function test_unit_plate_number_is_unique(): void
    {
        EquipmentUnit::factory()->create(['plate_number' => 'B 1234 XY']);

        $this->expectException(QueryException::class);
        EquipmentUnit::factory()->create(['plate_number' => 'B 1234 XY']);
    }

    public function test_unit_plate_number_is_nullable(): void
    {
        $unit = EquipmentUnit::factory()->create(['plate_number' => null]);

        $this->assertNull($unit->plate_number);
    }

    public function test_unit_status_casts_to_enum(): void
    {
        $unit = EquipmentUnit::factory()->create();

        $this->assertInstanceOf(EquipmentStatus::class, $unit->status);
        $this->assertEquals(EquipmentStatus::AVAILABLE, $unit->status);
    }

    public function test_model_name_is_unique(): void
    {
        EquipmentModel::factory()->create(['model_name' => 'PC200-8']);

        $this->expectException(QueryException::class);
        EquipmentModel::factory()->create(['model_name' => 'PC200-8']);
    }

    public function test_type_name_is_unique(): void
    {
        EquipmentType::factory()->create(['name' => 'Excavator']);

        $this->expectException(QueryException::class);
        EquipmentType::factory()->create(['name' => 'Excavator']);
    }

    public function test_restrict_delete_type_with_models(): void
    {
        $type = EquipmentType::factory()->create();
        EquipmentModel::factory()->create(['equipment_type_id' => $type->id]);

        $this->expectException(QueryException::class);
        $type->forceDelete();
    }

    public function test_restrict_delete_model_with_units(): void
    {
        $model = EquipmentModel::factory()->create();
        EquipmentUnit::factory()->create(['equipment_model_id' => $model->id]);

        $this->expectException(QueryException::class);
        $model->forceDelete();
    }

    public function test_unit_supports_soft_delete(): void
    {
        $unit = EquipmentUnit::factory()->create();
        $unit->delete();

        $this->assertSoftDeleted('equipment_units', ['id' => $unit->id]);
        $this->assertNotNull(EquipmentUnit::withTrashed()->find($unit->id));
    }

    public function test_unit_last_hour_meter_defaults_to_zero(): void
    {
        $unit = EquipmentUnit::factory()->create(['last_hour_meter' => 0.00]);

        $this->assertEquals('0.00', $unit->last_hour_meter);
    }
}
