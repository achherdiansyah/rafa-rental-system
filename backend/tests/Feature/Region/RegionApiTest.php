<?php

namespace Tests\Feature\Region;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RegionApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_load_region_hierarchy_and_cache_it(): void
    {
        Http::fake([
            '*/provinces.json' => Http::response([['id' => '32', 'name' => 'Jawa Barat']]),
            '*/regencies/32.json' => Http::response([['id' => '32.73', 'name' => 'Kota Bandung']]),
            '*/districts/32.73.json' => Http::response([['id' => '32.73.01', 'name' => 'Sukasari']]),
            '*/villages/32.73.01.json' => Http::response([['id' => '32.73.01.1001', 'name' => 'Sukarasa']]),
        ]);

        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/regions/provinces')->assertOk()->assertJsonPath('data.0.name', 'Jawa Barat');
        $this->getJson('/api/v1/regions/provinces/32/regencies')->assertOk()->assertJsonPath('data.0.id', '32.73');
        $this->getJson('/api/v1/regions/regencies/32.73/districts')->assertOk()->assertJsonPath('data.0.id', '32.73.01');
        $this->getJson('/api/v1/regions/districts/32.73.01/villages')->assertOk()->assertJsonPath('data.0.id', '32.73.01.1001');

        $this->getJson('/api/v1/regions/provinces')->assertOk();
        Http::assertSentCount(4);
    }

    public function test_region_service_failure_returns_503(): void
    {
        Http::fake(['*' => Http::response(null, 500)]);
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/regions/provinces')
            ->assertStatus(503)
            ->assertJsonPath('code', 'REGION_SERVICE_UNAVAILABLE');
    }
}
