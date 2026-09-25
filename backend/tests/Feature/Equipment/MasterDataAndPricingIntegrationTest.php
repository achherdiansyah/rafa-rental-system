<?php

namespace Tests\Feature\Equipment;

use App\Enums\EquipmentStatus;
use App\Enums\UserRole;
use App\Models\EquipmentPriceVersion;
use App\Models\EquipmentUnit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MasterDataAndPricingIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    /**
     * Complete end-to-end master data and pricing lifecycle.
     */
    public function test_complete_master_data_and_pricing_lifecycle_workflow(): void
    {
        $owner = User::factory()->owner()->create(['name' => 'Pak Owner']);
        $admin = User::factory()->admin()->create(['name' => 'Admin Operasional']);
        $user = User::factory()->create(['name' => 'Pelanggan Setia', 'role' => UserRole::USER]);

        // 1. OWNER: Create Equipment Type
        Sanctum::actingAs($owner);
        $typeRes = $this->postJson('/api/v1/equipment/types', [
            'name' => 'Hydraulic Excavator',
            'description' => 'Excavator kelas berat untuk galian dan konstruksi sipil',
        ]);
        $typeRes->assertStatus(201);
        $typeId = $typeRes->json('data.id');

        // 2. ADMIN: Create Equipment Model under the Type
        Sanctum::actingAs($admin);
        $modelRes = $this->postJson('/api/v1/equipment/models', [
            'equipment_type_id' => $typeId,
            'brand' => 'Komatsu',
            'model_name' => 'PC210-10M0',
            'capacity_value' => 21.00,
            'capacity_unit' => 'Ton',
            'is_active' => true,
        ]);
        $modelRes->assertStatus(201);
        $modelId = $modelRes->json('data.id');

        // 3. ADMIN: Upload Equipment Media Photo
        $photoFile = UploadedFile::fake()->image('pc210_main.jpg', 1200, 800)->size(1500);
        $photoRes = $this->postJson("/api/v1/equipment/models/{$modelId}/photos", [
            'photo' => $photoFile,
        ]);
        $photoRes->assertStatus(201);
        $attachmentId = $photoRes->json('data.id');

        // 4. ADMIN: Register Physical Equipment Units
        $unit1Res = $this->postJson('/api/v1/equipment/units', [
            'equipment_model_id' => $modelId,
            'serial_number' => 'KM-PC210-001',
            'plate_number' => 'B 1001 RFA',
            'last_hour_meter' => 250.00,
            'year_of_make' => 2024,
        ]);
        $unit1Res->assertStatus(201);
        $unit1Id = $unit1Res->json('data.id');

        $unit2Res = $this->postJson('/api/v1/equipment/units', [
            'equipment_model_id' => $modelId,
            'serial_number' => 'KM-PC210-002',
            'plate_number' => 'B 1002 RFA',
            'last_hour_meter' => 120.00,
            'year_of_make' => 2024,
        ]);
        $unit2Res->assertStatus(201);
        $unit2Id = $unit2Res->json('data.id');

        // 5. OWNER: Configure Master Prices (Non All-in & All-in)
        Sanctum::actingAs($owner);

        // 5a. Non All-in Price
        $priceNonAllInRes = $this->postJson('/api/v1/equipment/prices', [
            'equipment_model_id' => $modelId,
            'price_type' => 'HOURLY',
            'is_all_in' => false,
            'base_rate' => 225000.00,
            'minimum_hours' => 8,
            'overtime_rate' => 275000.00,
            'effective_date' => '2026-01-01',
        ]);
        $priceNonAllInRes->assertStatus(201);
        $priceNonAllInId = $priceNonAllInRes->json('data.id');

        // 5b. All-in Price
        $priceAllInRes = $this->postJson('/api/v1/equipment/prices', [
            'equipment_model_id' => $modelId,
            'price_type' => 'HOURLY',
            'is_all_in' => true,
            'base_rate' => 350000.00,
            'minimum_hours' => 8,
            'overtime_rate' => 400000.00,
            'effective_date' => '2026-01-01',
        ]);
        $priceAllInRes->assertStatus(201);

        // 6. OWNER: Update Non All-in Price to trigger version audit trail
        $updatePriceRes = $this->putJson("/api/v1/equipment/prices/{$priceNonAllInId}", [
            'base_rate' => 240000.00,
            'minimum_hours' => 8,
            'overtime_rate' => 290000.00,
            'effective_date' => '2026-06-01',
        ]);
        $updatePriceRes->assertStatus(200);

        // Assert 3 total versions exist in DB (2 for non-all-in, 1 for all-in)
        $this->assertDatabaseCount('equipment_price_versions', 3);
        $this->assertEquals(2, EquipmentPriceVersion::where('equipment_price_id', $priceNonAllInId)->count());
        $this->assertDatabaseHas('equipment_price_versions', [
            'equipment_price_id' => $priceNonAllInId,
            'old_base_rate' => 225000.00,
            'new_base_rate' => 240000.00,
            'changed_by' => $owner->id,
        ]);

        // 7. ADMIN: Setup Company Bank Account
        Sanctum::actingAs($admin);
        $bankRes = $this->postJson('/api/v1/bank-accounts', [
            'bank_name' => 'Bank Central Asia (BCA)',
            'account_number' => '1234567890',
            'account_name' => 'PT RAFA RENTAL NUSANTARA',
            'is_active' => true,
        ]);
        $bankRes->assertStatus(201);

        // 8. USER: Browse catalog & inspect equipment details
        Sanctum::actingAs($user);

        // 8a. Catalog listing with models & prices eager loaded
        $catalogRes = $this->getJson('/api/v1/equipment/models?brand=Komatsu');
        $catalogRes->assertStatus(200);
        $this->assertCount(1, $catalogRes->json('data'));
        $this->assertEquals('PC210-10M0', $catalogRes->json('data.0.model_name'));
        $this->assertEquals(2, $catalogRes->json('data.0.units_count'));

        // 8b. Equipment model detail with specs, photos, and prices
        $detailRes = $this->getJson("/api/v1/equipment/models/{$modelId}");
        $detailRes->assertStatus(200);
        $this->assertEquals('Komatsu', $detailRes->json('data.brand'));
        $this->assertCount(1, $detailRes->json('data.attachments'));
        $this->assertCount(2, $detailRes->json('data.prices'));

        // 8c. Active bank accounts visible to User
        $bankListRes = $this->getJson('/api/v1/bank-accounts');
        $bankListRes->assertStatus(200);
        $this->assertCount(1, $bankListRes->json('data'));

        // 9. SIMULATION: Pricing calculation engine test (3 units, 5 days, MOB/DEMOB)
        // Rate: 240,000/hr * 8h = 1,920,000/day * 5 days * 2 units = 19,200,000
        // Mob: 1,500,000 * 2 = 3,000,000
        // Demob: 1,500,000 * 2 = 3,000,000
        // Line total: 25,200,000
        $calcRes = $this->postJson('/api/v1/pricing/calculate', [
            'items' => [
                [
                    'equipment_model_id' => $modelId,
                    'quantity' => 2,
                    'start_date' => '2026-07-01',
                    'end_date' => '2026-07-05',
                    'is_all_in' => false,
                    'mob_rate_per_unit' => 1500000.00,
                    'demob_rate_per_unit' => 1500000.00,
                ],
            ],
        ]);
        $calcRes->assertStatus(200);
        $this->assertEquals(19200000, $calcRes->json('data.total_rental_amount'));
        $this->assertEquals(3000000, $calcRes->json('data.total_mob_amount'));
        $this->assertEquals(3000000, $calcRes->json('data.total_demob_amount'));
        $this->assertEquals(25200000, $calcRes->json('data.grand_total'));

        // 10. SECURITY & DATA INTEGRITY GUARDS
        // 10a. User cannot mutate master data
        $this->postJson('/api/v1/equipment/types', ['name' => 'Illegal'])->assertStatus(403);
        $this->postJson('/api/v1/equipment/models', ['equipment_type_id' => $typeId, 'brand' => 'X', 'model_name' => 'Y'])->assertStatus(403);
        $this->postJson('/api/v1/equipment/units', ['equipment_model_id' => $modelId, 'serial_number' => 'X'])->assertStatus(403);
        $this->postJson('/api/v1/equipment/prices', ['equipment_model_id' => $modelId, 'price_type' => 'HOURLY', 'is_all_in' => false, 'base_rate' => 100000, 'minimum_hours' => 8, 'overtime_rate' => 100000, 'effective_date' => '2026-01-01'])->assertStatus(403);
        $this->postJson('/api/v1/bank-accounts', ['bank_name' => 'Illegal', 'account_number' => '000', 'account_name' => 'Illegal'])->assertStatus(403);

        // 10b. Admin cannot mutate prices (Owner-only)
        Sanctum::actingAs($admin);
        $this->postJson('/api/v1/equipment/prices', ['equipment_model_id' => $modelId, 'price_type' => 'HOURLY', 'is_all_in' => false, 'base_rate' => 100000, 'minimum_hours' => 8, 'overtime_rate' => 100000, 'effective_date' => '2026-01-01'])->assertStatus(403);
        $this->putJson("/api/v1/equipment/prices/{$priceNonAllInId}", ['base_rate' => 999999, 'minimum_hours' => 8, 'overtime_rate' => 999999, 'effective_date' => '2026-01-01'])->assertStatus(403);

        // 10c. Cannot delete Model when Units exist (FK Conflict guard)
        $this->deleteJson("/api/v1/equipment/models/{$modelId}")->assertStatus(409);

        // 10d. Cannot delete Type when Models exist (FK Conflict guard)
        $this->deleteJson("/api/v1/equipment/types/{$typeId}")->assertStatus(409);

        // 10e. Admin safely transitions Unit 1 to Maintenance and updates hour meter
        $maintRes = $this->postJson("/api/v1/equipment/units/{$unit1Id}/status", [
            'status' => 'MAINTENANCE',
            'notes' => 'Perawatan rutin 250 jam',
        ]);
        $maintRes->assertStatus(200);

        $updateUnitRes = $this->putJson("/api/v1/equipment/units/{$unit1Id}", [
            'last_hour_meter' => 258.50,
        ]);
        $updateUnitRes->assertStatus(200);
        $this->assertEquals(258.50, EquipmentUnit::find($unit1Id)->last_hour_meter);

        // 10f. Unit in active rental cannot be arbitrarily switched to AVAILABLE
        EquipmentUnit::find($unit1Id)->update(['status' => EquipmentStatus::ON_SITE]);
        $illegalStatusRes = $this->postJson("/api/v1/equipment/units/{$unit1Id}/status", [
            'status' => 'AVAILABLE',
            'notes' => 'Illegal bypass attempt',
        ]);
        $illegalStatusRes->assertStatus(409);
    }
}
