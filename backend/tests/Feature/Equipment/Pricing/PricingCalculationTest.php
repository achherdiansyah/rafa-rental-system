<?php

namespace Tests\Feature\Equipment\Pricing;

use App\Models\EquipmentModel;
use App\Models\EquipmentPrice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PricingCalculationTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_calculate_booking_price_for_all_in_and_non_all_in_lines(): void
    {
        $model1 = EquipmentModel::factory()->create(['brand' => 'Komatsu', 'model_name' => 'PC200']);
        $model2 = EquipmentModel::factory()->create(['brand' => 'Caterpillar', 'model_name' => 'D85']);

        // Model 1: All-in Price (Rp 250,000/hour) => Daily: 2,000,000
        EquipmentPrice::factory()->allIn()->create([
            'equipment_model_id' => $model1->id,
            'base_rate' => 250000.00,
            'effective_date' => '2026-01-01',
        ]);

        // Model 2: Non All-in Price (Rp 150,000/hour) => Daily: 1,200,000
        EquipmentPrice::factory()->create([
            'equipment_model_id' => $model2->id,
            'is_all_in' => false,
            'base_rate' => 150000.00,
            'effective_date' => '2026-01-01',
        ]);

        $payload = [
            'items' => [
                [
                    'equipment_model_id' => $model1->id,
                    'quantity' => 2,
                    'start_date' => '2026-10-01',
                    'end_date' => '2026-10-03', // 3 days
                    'is_all_in' => true,
                    'mob_rate_per_unit' => 1000000.00,
                    'demob_rate_per_unit' => 1000000.00,
                ],
                [
                    'equipment_model_id' => $model2->id,
                    'quantity' => 1,
                    'start_date' => '2026-10-01',
                    'end_date' => '2026-10-05', // 5 days
                    'is_all_in' => false,
                    'mob_rate_per_unit' => 500000.00,
                    'demob_rate_per_unit' => 0.00,
                ],
            ],
        ];

        $response = $this->postJson('/api/v1/pricing/calculate', $payload);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'items',
                    'total_rental_amount',
                    'total_mob_amount',
                    'total_demob_amount',
                    'tax_amount',
                    'discount_amount',
                    'grand_total',
                ],
            ]);

        // Assert Model 1 details (2,000,000 * 3 days * 2 units = 12,000,000)
        // Mob: 1,000,000 * 2 = 2,000,000, Demob: 1,000,000 * 2 = 2,000,000
        // Line 1 Total = 16,000,000
        $this->assertEquals(12000000, $response->json('data.items.0.rental_subtotal'));
        $this->assertEquals(2000000, $response->json('data.items.0.mob_subtotal'));
        $this->assertEquals(2000000, $response->json('data.items.0.demob_subtotal'));
        $this->assertEquals(16000000, $response->json('data.items.0.line_total'));

        // Assert Model 2 details (1,200,000 * 5 days * 1 unit = 6,000,000)
        // Mob: 500,000 * 1 = 500,000, Demob = 0
        // Line 2 Total = 6,500,000
        $this->assertEquals(6000000, $response->json('data.items.1.rental_subtotal'));
        $this->assertEquals(500000, $response->json('data.items.1.mob_subtotal'));
        $this->assertEquals(0, $response->json('data.items.1.demob_subtotal'));
        $this->assertEquals(6500000, $response->json('data.items.1.line_total'));

        // Assert Aggregates
        $this->assertEquals(18000000, $response->json('data.total_rental_amount'));
        $this->assertEquals(2500000, $response->json('data.total_mob_amount'));
        $this->assertEquals(2000000, $response->json('data.total_demob_amount'));
        $this->assertEquals(0, $response->json('data.tax_amount'));
        $this->assertEquals(0, $response->json('data.discount_amount'));
        $this->assertEquals(22500000, $response->json('data.grand_total'));
    }

    public function test_pricing_calculation_fails_if_master_price_not_set(): void
    {
        $model = EquipmentModel::factory()->create();

        // Set ONLY non_all_in price
        EquipmentPrice::factory()->create([
            'equipment_model_id' => $model->id,
            'is_all_in' => false,
            'effective_date' => '2026-01-01',
        ]);

        $payload = [
            'items' => [
                [
                    'equipment_model_id' => $model->id,
                    'quantity' => 1,
                    'start_date' => '2026-10-01',
                    'end_date' => '2026-10-02',
                    'is_all_in' => true, // Requesting All-in which is not set
                ],
            ],
        ];

        $response = $this->postJson('/api/v1/pricing/calculate', $payload);

        $response->assertStatus(409)
            ->assertJson([
                'success' => false,
                'code' => 'BUSINESS_RULE_VIOLATION',
            ]);

        $this->assertStringContainsString('belum ditetapkan', $response->json('message'));
    }

    public function test_pricing_calculation_uses_most_recent_effective_date(): void
    {
        $model = EquipmentModel::factory()->create();

        // Old price: 100,000/h
        EquipmentPrice::factory()->create([
            'equipment_model_id' => $model->id,
            'is_all_in' => false,
            'base_rate' => 100000.00,
            'effective_date' => '2025-01-01',
        ]);

        // New price: 200,000/h (effective from Nov 1, 2026)
        EquipmentPrice::factory()->create([
            'equipment_model_id' => $model->id,
            'is_all_in' => false,
            'base_rate' => 200000.00,
            'effective_date' => '2026-11-01',
        ]);

        // Booking on Oct 1, 2026 -> Should use Old Price
        $resOld = $this->postJson('/api/v1/pricing/calculate', [
            'items' => [[
                'equipment_model_id' => $model->id,
                'quantity' => 1,
                'start_date' => '2026-10-01',
                'end_date' => '2026-10-01', // 1 day
                'is_all_in' => false,
            ]],
        ]);

        $this->assertEquals(800000, $resOld->json('data.grand_total')); // 100,000 * 8h

        // Booking on Dec 1, 2026 -> Should use New Price
        $resNew = $this->postJson('/api/v1/pricing/calculate', [
            'items' => [[
                'equipment_model_id' => $model->id,
                'quantity' => 1,
                'start_date' => '2026-12-01',
                'end_date' => '2026-12-01', // 1 day
                'is_all_in' => false,
            ]],
        ]);

        $this->assertEquals(1600000, $resNew->json('data.grand_total')); // 200,000 * 8h
    }

    public function test_pricing_calculation_fails_if_equipment_model_is_inactive(): void
    {
        $model = EquipmentModel::factory()->inactive()->create();

        EquipmentPrice::factory()->create([
            'equipment_model_id' => $model->id,
            'is_all_in' => false,
            'base_rate' => 150000.00,
            'effective_date' => '2026-01-01',
        ]);

        $payload = [
            'items' => [
                [
                    'equipment_model_id' => $model->id,
                    'quantity' => 1,
                    'start_date' => '2026-10-01',
                    'end_date' => '2026-10-02',
                    'is_all_in' => false,
                ],
            ],
        ];

        $response = $this->postJson('/api/v1/pricing/calculate', $payload);

        $response->assertStatus(409)
            ->assertJson([
                'success' => false,
                'code' => 'BUSINESS_RULE_VIOLATION',
            ]);

        $this->assertStringContainsString('sedang tidak aktif', $response->json('message'));
    }

    public function test_pricing_calculation_rejects_negative_mob_and_demob_rates(): void
    {
        $model = EquipmentModel::factory()->create();

        $payload = [
            'items' => [
                [
                    'equipment_model_id' => $model->id,
                    'quantity' => 1,
                    'start_date' => '2026-10-01',
                    'end_date' => '2026-10-02',
                    'is_all_in' => false,
                    'mob_rate_per_unit' => -50000,
                    'demob_rate_per_unit' => -100000,
                ],
            ],
        ];

        $response = $this->postJson('/api/v1/pricing/calculate', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'items.0.mob_rate_per_unit',
                'items.0.demob_rate_per_unit',
            ]);
    }

    public function test_pricing_calculation_with_zero_mob_and_demob(): void
    {
        $model = EquipmentModel::factory()->create();
        EquipmentPrice::factory()->create([
            'equipment_model_id' => $model->id,
            'is_all_in' => false,
            'base_rate' => 100000.00,
            'effective_date' => '2026-01-01',
        ]);

        $payload = [
            'items' => [
                [
                    'equipment_model_id' => $model->id,
                    'quantity' => 1,
                    'start_date' => '2026-10-01',
                    'end_date' => '2026-10-01', // 1 day
                    'is_all_in' => false,
                    'mob_rate_per_unit' => 0,
                    'demob_rate_per_unit' => 0,
                ],
            ],
        ];

        $response = $this->postJson('/api/v1/pricing/calculate', $payload);

        $response->assertStatus(200);
        $this->assertEquals(800000, $response->json('data.total_rental_amount'));
        $this->assertEquals(0, $response->json('data.total_mob_amount'));
        $this->assertEquals(0, $response->json('data.total_demob_amount'));
        $this->assertEquals(800000, $response->json('data.grand_total'));
    }

    public function test_validation_fails_on_missing_fields_or_wrong_dates(): void
    {
        $payload = [
            'items' => [
                [
                    'equipment_model_id' => 999, // Unknown model
                    'quantity' => 0,             // Invalid quantity
                    'start_date' => '2026-10-10',
                    'end_date' => '2026-10-09',  // End date before start date
                    'is_all_in' => 'not-a-bool', // Invalid boolean
                ],
            ],
        ];

        $response = $this->postJson('/api/v1/pricing/calculate', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'items.0.equipment_model_id',
                'items.0.quantity',
                'items.0.end_date',
                'items.0.is_all_in',
            ]);
    }
}
