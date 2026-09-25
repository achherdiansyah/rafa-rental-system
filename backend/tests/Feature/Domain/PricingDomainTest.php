<?php

namespace Tests\Feature\Domain;

use App\Actions\Equipment\Pricing\UpdateEquipmentPriceAction;
use App\Models\EquipmentModel;
use App\Models\EquipmentPrice;
use App\Models\EquipmentPriceVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PricingDomainTest extends TestCase
{
    use RefreshDatabase;

    public function test_model_has_many_prices(): void
    {
        $model = EquipmentModel::factory()->create();
        $p1 = EquipmentPrice::factory()->create(['equipment_model_id' => $model->id]);
        $p2 = EquipmentPrice::factory()->allIn()->create(['equipment_model_id' => $model->id]);

        $this->assertCount(2, $model->prices);
        $this->assertTrue($p1->model->is($model));
    }

    public function test_price_has_many_versions(): void
    {
        $user = User::factory()->create();
        $price = EquipmentPrice::factory()->create();

        $v1 = EquipmentPriceVersion::factory()->create([
            'equipment_price_id' => $price->id,
            'old_base_rate' => 200000.00,
            'new_base_rate' => 250000.00,
            'changed_by' => $user->id,
        ]);

        $v2 = EquipmentPriceVersion::factory()->create([
            'equipment_price_id' => $price->id,
            'old_base_rate' => 250000.00,
            'new_base_rate' => 300000.00,
            'changed_by' => $user->id,
        ]);

        $this->assertCount(2, $price->versions);
        $this->assertTrue($v1->price->is($price));
    }

    public function test_price_base_rate_is_decimal(): void
    {
        $price = EquipmentPrice::factory()->create(['base_rate' => 275000.50]);

        $this->assertEquals('275000.50', $price->base_rate);
    }

    public function test_price_version_preserves_old_and_new_rate(): void
    {
        $user = User::factory()->create();
        $price = EquipmentPrice::factory()->create(['base_rate' => 200000.00]);

        $version = EquipmentPriceVersion::factory()->create([
            'equipment_price_id' => $price->id,
            'old_base_rate' => 200000.00,
            'new_base_rate' => 250000.00,
            'changed_by' => $user->id,
        ]);

        $this->assertEquals('200000.00', $version->old_base_rate);
        $this->assertEquals('250000.00', $version->new_base_rate);
    }

    public function test_price_cascade_deletes_versions(): void
    {
        $user = User::factory()->create();
        $price = EquipmentPrice::factory()->create();
        $version = EquipmentPriceVersion::factory()->create([
            'equipment_price_id' => $price->id,
            'changed_by' => $user->id,
        ]);

        $this->assertDatabaseHas('equipment_price_versions', ['id' => $version->id]);

        $price->delete();

        $this->assertDatabaseMissing('equipment_price_versions', ['id' => $version->id]);
    }

    public function test_price_is_all_in_casts_to_boolean(): void
    {
        $priceAllIn = EquipmentPrice::factory()->allIn()->create();
        $priceNotAllIn = EquipmentPrice::factory()->create();

        $this->assertTrue($priceAllIn->is_all_in);
        $this->assertFalse($priceNotAllIn->is_all_in);
    }

    public function test_price_version_chain_continuity_across_sequential_updates(): void
    {
        $owner = User::factory()->owner()->create();
        $action = app(UpdateEquipmentPriceAction::class);

        $price = EquipmentPrice::factory()->create(['base_rate' => 100000.00]);

        // First update: 100k -> 150k
        $action->execute($price, ['base_rate' => 150000.00], $owner);
        // Second update: 150k -> 200k
        $action->execute($price, ['base_rate' => 200000.00], $owner);

        $versions = $price->versions()->orderBy('id')->get();
        $this->assertCount(2, $versions);

        $this->assertEquals('100000.00', $versions[0]->old_base_rate);
        $this->assertEquals('150000.00', $versions[0]->new_base_rate);

        $this->assertEquals('150000.00', $versions[1]->old_base_rate);
        $this->assertEquals('200000.00', $versions[1]->new_base_rate);
    }

    public function test_no_price_version_created_when_base_rate_is_unchanged(): void
    {
        $owner = User::factory()->owner()->create();
        $action = app(UpdateEquipmentPriceAction::class);

        $price = EquipmentPrice::factory()->create(['base_rate' => 100000.00]);

        // Update other fields but same base rate
        $action->execute($price, [
            'base_rate' => 100000.00,
            'minimum_hours' => 10,
        ], $owner);

        $this->assertCount(0, $price->versions);
    }
}
