<?php

namespace Tests\Feature\Equipment;

use App\Models\EquipmentPool;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PoolCoordinateInputTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Pool Bandung',
            'address' => 'Jl. Contoh No. 1',
            'city' => 'Bandung',
            'latitude' => -7.3275,
            'longitude' => 112.7122,
            'is_active' => true,
        ], $overrides);
    }

    public function test_admin_can_store_negative_decimal_coordinates_and_read_them_back(): void
    {
        Sanctum::actingAs($this->admin());

        $res = $this->postJson('/api/v1/admin/equipment-pools', $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.latitude', -7.3275)
            ->assertJsonPath('data.longitude', 112.7122);

        $pool = EquipmentPool::findOrFail($res->json('data.id'));
        $this->assertEqualsWithDelta(-7.3275, (float) $pool->latitude, 0.00000001);
        $this->assertEqualsWithDelta(112.7122, (float) $pool->longitude, 0.00000001);
    }

    public function test_admin_can_update_coordinates_preserving_decimals(): void
    {
        $pool = EquipmentPool::factory()->create([
            'latitude' => -6.2,
            'longitude' => 106.8,
        ]);
        Sanctum::actingAs($this->admin());

        $this->putJson("/api/v1/admin/equipment-pools/{$pool->id}", [
            'latitude' => -8.12345678,
            'longitude' => 115.98765432,
        ])->assertOk()
            ->assertJsonPath('data.latitude', -8.12345678)
            ->assertJsonPath('data.longitude', 115.98765432);

        $this->assertEqualsWithDelta(-8.12345678, (float) $pool->fresh()->latitude, 0.00000001);
    }

    public function test_zero_zero_coordinates_are_rejected(): void
    {
        Sanctum::actingAs($this->admin());

        $this->postJson('/api/v1/admin/equipment-pools', $this->payload([
            'latitude' => 0,
            'longitude' => 0,
        ]))->assertStatus(422)
            ->assertJsonValidationErrors(['latitude', 'longitude']);

        $this->assertDatabaseCount('equipment_pools', 0);
    }

    public function test_coordinates_outside_valid_ranges_are_rejected(): void
    {
        Sanctum::actingAs($this->admin());

        $this->postJson('/api/v1/admin/equipment-pools', $this->payload([
            'latitude' => 90.0001,
            'longitude' => 180.0001,
        ]))->assertStatus(422)
            ->assertJsonValidationErrors(['latitude', 'longitude']);
    }

    public function test_empty_coordinates_are_rejected_with_clear_message(): void
    {
        Sanctum::actingAs($this->admin());

        $res = $this->postJson('/api/v1/admin/equipment-pools', $this->payload([
            'latitude' => '',
            'longitude' => '',
        ]))->assertStatus(422)
            ->assertJsonValidationErrors(['latitude', 'longitude']);

        $this->assertStringContainsString('Latitude wajib diisi', implode(' ', $res->json('errors.latitude')));
        $this->assertStringContainsString('Longitude wajib diisi', implode(' ', $res->json('errors.longitude')));
    }

    public function test_pool_can_be_stored_at_zero_latitude_with_non_zero_longitude(): void
    {
        Sanctum::actingAs($this->admin());

        $this->postJson('/api/v1/admin/equipment-pools', $this->payload([
            'latitude' => 0,
            'longitude' => 112.7122,
        ]))->assertCreated();
    }
}
