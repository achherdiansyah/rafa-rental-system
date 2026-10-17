<?php

namespace Tests\Feature\Pricing;

use App\Actions\Booking\CreateBookingFromCartAction;
use App\Enums\BookingStatus;
use App\Enums\EquipmentStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Booking;
use App\Models\BookingDetail;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\CustomerProfile;
use App\Models\EquipmentModel;
use App\Models\EquipmentPool;
use App\Models\EquipmentPrice;
use App\Models\EquipmentUnit;
use App\Models\ProjectLocation;
use App\Models\User;
use App\Services\Routing\MapboxRouteDistanceProvider;
use App\Services\Routing\RouteDistanceProvider;
use App\Services\Routing\RouteDistanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MobDemobRoutingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_mapbox_provider_converts_meters_to_rounded_kilometers(): void
    {
        Http::fake([
            'api.mapbox.com/*' => Http::response([
                'routes' => [['distance' => 12345.0]],
            ], 200),
        ]);

        $provider = new MapboxRouteDistanceProvider('test-token');
        $km = $provider->distanceKm(-6.2, 106.8, -6.9, 107.6);

        $this->assertEqualsWithDelta(12.35, $km, 0.001);

        Http::assertSent(fn ($request) => str_contains($request->url(), '/driving/106.8,-6.2;107.6,-6.9')
            && $request['access_token'] === 'test-token'
            && $request['overview'] === 'false');
    }

    public function test_mapbox_timeout_returns_null(): void
    {
        Http::fake(function () {
            throw new ConnectionException('timeout');
        });

        $provider = new MapboxRouteDistanceProvider('test-token', 2);

        $this->assertNull($provider->distanceKm(-6.2, 106.8, -6.9, 107.6));
    }

    public function test_mapbox_no_route_found_returns_null(): void
    {
        Http::fake(['api.mapbox.com/*' => Http::response(['routes' => []], 200)]);

        $provider = new MapboxRouteDistanceProvider('test-token');

        $this->assertNull($provider->distanceKm(-6.2, 106.8, -6.9, 107.6));
    }

    public function test_mapbox_http_error_returns_null(): void
    {
        Http::fake(['api.mapbox.com/*' => Http::response(['message' => 'Unauthorized'], 401)]);

        $provider = new MapboxRouteDistanceProvider('bad-token');

        $this->assertNull($provider->distanceKm(-6.2, 106.8, -6.9, 107.6));
    }

    public function test_provider_without_token_returns_null_without_http_call(): void
    {
        Http::fake();

        $provider = new MapboxRouteDistanceProvider(null);

        $this->assertNull($provider->distanceKm(-6.2, 106.8, -6.9, 107.6));
        Http::assertNothingSent();
    }

    public function test_invalid_coordinates_are_rejected_without_http_call(): void
    {
        Http::fake();
        $this->app->instance(RouteDistanceProvider::class, new MapboxRouteDistanceProvider('test-token'));

        $service = app(RouteDistanceService::class);

        $this->assertNull($service->distanceKm(-100.0, 106.8, -6.9, 107.6));
        $this->assertNull($service->distanceKm(-6.2, 200.0, -6.9, 107.6));
        $this->assertNull($service->distanceKm(-6.2, 106.8, null, 107.6));
        Http::assertNothingSent();
    }

    public function test_successful_distance_is_cached_per_coordinate_pair(): void
    {
        Http::fake([
            'api.mapbox.com/*' => Http::response(['routes' => [['distance' => 20000.0]]], 200),
        ]);
        $this->app->instance(RouteDistanceProvider::class, new MapboxRouteDistanceProvider('test-token'));

        $service = app(RouteDistanceService::class);
        $first = $service->distanceKm(-6.2, 106.8, -6.9, 107.6);
        $second = $service->distanceKm(-6.2, 106.8, -6.9, 107.6);

        $this->assertEqualsWithDelta(20.0, $first, 0.001);
        $this->assertEqualsWithDelta(20.0, $second, 0.001);
        Http::assertSentCount(1);

        // Different destination -> new API call.
        $service->distanceKm(-6.2, 106.8, -7.5, 110.0);
        Http::assertSentCount(2);
    }

    public function test_zero_distance_rate_reports_configuration_missing(): void
    {
        Http::fake([
            'api.mapbox.com/*' => Http::response(['routes' => [['distance' => 10000.0]]], 200),
        ]);
        $this->app->instance(RouteDistanceProvider::class, new MapboxRouteDistanceProvider('test-token'));

        [$user, $model, $location] = $this->tenantWithRate(0);

        Sanctum::actingAs($user);
        $res = $this->getJson("/api/v1/equipment/models/{$model->id}/mob-demob-estimate?project_location_id={$location->id}")
            ->assertOk();

        $res->assertJsonPath('data.distance_available', false)
            ->assertJsonPath('data.mob_cost', null);

        $this->assertStringContainsString('Tarif per kilometer belum dikonfigurasi', $res->json('data.message'));
    }

    public function test_booking_with_zero_distance_rate_is_blocked_not_silently_flat(): void
    {
        Http::fake([
            'api.mapbox.com/*' => Http::response(['routes' => [['distance' => 10000.0]]], 200),
        ]);
        $this->app->instance(RouteDistanceProvider::class, new MapboxRouteDistanceProvider('test-token'));

        [$user, , , $cart] = $this->tenantWithRate(0, withCart: true);

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('Tarif per kilometer MOB/DEMOB belum dikonfigurasi');

        app(CreateBookingFromCartAction::class)->execute($user, $cart);
    }

    public function test_unit_from_different_pool_is_rejected_on_assignment(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();
        $poolA = EquipmentPool::factory()->create();
        $poolB = EquipmentPool::factory()->create();
        $location = ProjectLocation::factory()->create(['user_id' => $user->id]);
        $model = EquipmentModel::factory()->create(['is_active' => true, 'equipment_pool_id' => $poolA->id]);
        $unit = EquipmentUnit::factory()->create([
            'equipment_model_id' => $model->id,
            'equipment_pool_id' => $poolB->id,
            'status' => EquipmentStatus::AVAILABLE,
        ]);

        $booking = Booking::factory()->create([
            'user_id' => $user->id,
            'project_location_id' => $location->id,
            'status' => BookingStatus::CONFIRMED,
            'payment_met_at' => now(),
        ]);
        $detail = BookingDetail::factory()->create([
            'booking_id' => $booking->id,
            'equipment_model_id' => $model->id,
            'quantity' => 1,
            'origin_pool_id' => $poolA->id,
        ]);

        Sanctum::actingAs($admin);
        $this->postJson("/api/v1/bookings/{$booking->id}/assign-units", [
            'assignments' => [[
                'booking_detail_id' => $detail->id,
                'equipment_unit_id' => $unit->id,
            ]],
        ])->assertStatus(409)->assertJson(['code' => 'BUSINESS_RULE_VIOLATION']);

        $this->assertDatabaseMissing('booking_unit_assignments', [
            'booking_detail_id' => $detail->id,
            'equipment_unit_id' => $unit->id,
        ]);
    }

    /**
     * @return array{0: User, 1: EquipmentModel, 2: ProjectLocation, 3?: Cart}
     */
    private function tenantWithRate(float $distanceRate, bool $withCart = false): array
    {
        $user = User::factory()->create();
        CustomerProfile::factory()->create(['user_id' => $user->id, 'verification_status' => 'VERIFIED']);
        $pool = EquipmentPool::factory()->create(['latitude' => -6.2, 'longitude' => 106.8]);
        $location = ProjectLocation::factory()->create([
            'user_id' => $user->id,
            'latitude' => -6.9,
            'longitude' => 107.6,
        ]);
        $model = EquipmentModel::factory()->create(['is_active' => true, 'equipment_pool_id' => $pool->id]);
        EquipmentPrice::factory()->create([
            'equipment_model_id' => $model->id,
            'is_all_in' => true,
            'base_rate' => 300000.00,
            'distance_rate_per_km' => $distanceRate,
            'effective_date' => now()->subMonth()->toDateString(),
        ]);
        EquipmentUnit::factory()->create([
            'equipment_model_id' => $model->id,
            'status' => EquipmentStatus::AVAILABLE,
        ]);

        if (! $withCart) {
            return [$user, $model, $location];
        }

        $cart = Cart::create(['user_id' => $user->id, 'project_location_id' => $location->id]);
        CartItem::create([
            'cart_id' => $cart->id,
            'equipment_model_id' => $model->id,
            'quantity' => 1,
            'is_all_in' => true,
            'start_date' => now()->addDays(2)->toDateString(),
            'end_date' => now()->addDays(4)->toDateString(),
        ]);

        return [$user, $model, $location, $cart];
    }
}
