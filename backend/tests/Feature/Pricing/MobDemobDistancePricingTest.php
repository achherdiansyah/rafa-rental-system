<?php

namespace Tests\Feature\Pricing;

use App\Actions\Booking\ApproveBookingAction;
use App\Actions\Booking\CreateBookingFromCartAction;
use App\Enums\BookingStatus;
use App\Enums\EquipmentStatus;
use App\Enums\InvoiceType;
use App\Exceptions\BusinessRuleException;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\CustomerProfile;
use App\Models\EquipmentModel;
use App\Models\EquipmentPool;
use App\Models\EquipmentPrice;
use App\Models\EquipmentUnit;
use App\Models\Invoice;
use App\Models\ProjectLocation;
use App\Models\User;
use App\Services\Pricing\PricingCalculatorService;
use App\Services\Routing\RouteDistanceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MobDemobDistancePricingTest extends TestCase
{
    use RefreshDatabase;

    private function fakeProvider(float $km): RouteDistanceProvider
    {
        return new class($km) implements RouteDistanceProvider
        {
            public function __construct(private float $km) {}

            public function distanceKm(float $originLat, float $originLng, float $destinationLat, float $destinationLng): ?float
            {
                return $this->km;
            }
        };
    }

    private function setupCart(float $distanceRate = 5000.0): array
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();
        CustomerProfile::factory()->create(['user_id' => $user->id, 'verification_status' => 'VERIFIED']);

        $pool = EquipmentPool::factory()->create(['latitude' => -6.2, 'longitude' => 106.8]);
        $location = ProjectLocation::factory()->create([
            'user_id' => $user->id,
            'latitude' => -6.9,
            'longitude' => 107.6,
        ]);

        $model = EquipmentModel::factory()->create([
            'is_active' => true,
            'equipment_pool_id' => $pool->id,
        ]);
        EquipmentPrice::factory()->create([
            'equipment_model_id' => $model->id,
            'is_all_in' => true,
            'base_rate' => 300000.00,
            'overtime_rate' => 350000.00,
            'distance_rate_per_km' => $distanceRate,
            'mob_cost' => 0,
            'demob_cost' => 0,
            'effective_date' => now()->subMonth()->toDateString(),
        ]);
        EquipmentUnit::factory()->create([
            'equipment_model_id' => $model->id,
            'status' => EquipmentStatus::AVAILABLE,
        ]);

        $cart = Cart::create(['user_id' => $user->id, 'project_location_id' => $location->id]);
        CartItem::create([
            'cart_id' => $cart->id,
            'equipment_model_id' => $model->id,
            'quantity' => 1,
            'is_all_in' => true,
            'start_date' => now()->addDays(2)->toDateString(),
            'end_date' => now()->addDays(4)->toDateString(),
        ]);

        return [$admin, $user, $cart, $location, $model];
    }

    public function test_mob_demob_equals_distance_times_rate_and_total_is_sum(): void
    {
        $this->app->instance(RouteDistanceProvider::class, $this->fakeProvider(12.5));
        [, $user, $cart, , $model] = $this->setupCart(5000.0);

        $booking = app(CreateBookingFromCartAction::class)->execute($user, $cart);
        $detail = $booking->details()->first();

        $this->assertEqualsWithDelta(12.5, (float) $detail->route_distance_km, 0.01);
        $this->assertEqualsWithDelta(5000.0, (float) $detail->distance_rate_per_km, 0.01);
        $this->assertEqualsWithDelta(62500.0, (float) $detail->mob_cost_snapshot, 0.01); // 12.5 x 5000
        $this->assertEqualsWithDelta(62500.0, (float) $detail->demob_cost_snapshot, 0.01);
        $this->assertEqualsWithDelta(125000.0, (float) $detail->subtotal, 0.01);
        $this->assertEqualsWithDelta(125000.0, (float) $booking->total_amount, 0.01);
        // Destination/origin snapshots are persisted.
        $this->assertNotNull($detail->origin_name);
        $this->assertNotNull($detail->destination_name);
    }

    public function test_snapshot_is_immutable_after_master_rate_changes(): void
    {
        $this->app->instance(RouteDistanceProvider::class, $this->fakeProvider(10.0));
        [$admin, $user, $cart, , $model] = $this->setupCart(4000.0);

        $booking = app(CreateBookingFromCartAction::class)->execute($user, $cart);
        $detail = $booking->details()->first();
        $this->assertEqualsWithDelta(80000.0, (float) $detail->subtotal, 0.01); // (10*4000)*2

        // Admin raises the per-km master rate AFTER the booking exists.
        EquipmentPrice::where('equipment_model_id', $model->id)->update(['distance_rate_per_km' => 9000.0]);

        $this->assertEqualsWithDelta(4000.0, (float) $detail->fresh()->distance_rate_per_km, 0.01);
        $this->assertEqualsWithDelta(80000.0, (float) $detail->fresh()->subtotal, 0.01);

        // Initial invoice (on approval) uses the snapshot, MOB/DEMOB only.
        $booking->update([
            'status' => BookingStatus::PENDING_APPROVAL,
            'supervisor_decision' => 'VALIDATED',
            'supervisor_validated_at' => now(),
        ]);
        app(ApproveBookingAction::class)->execute($admin, $booking);

        $invoice = Invoice::where('booking_id', $booking->id)->firstOrFail();
        $this->assertEquals(InvoiceType::MOB_DEMOB, $invoice->invoice_type);
        $this->assertEqualsWithDelta(80000.0, (float) $invoice->grand_total, 0.01);
        $this->assertCount(2, $invoice->details); // mobilisasi + demobilisasi
        $this->assertFalse(
            $invoice->details()->where('description', 'like', '%Sewa Harian%')->exists(),
            'Invoice awal tidak boleh memuat tarif rental per jam.'
        );
    }

    public function test_missing_route_distance_does_not_produce_fake_price(): void
    {
        // No provider bound -> Null provider (default from AppServiceProvider).
        [, $user, $cart] = $this->setupCart(5000.0);

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('Estimasi jarak rute MOB/DEMOB belum tersedia');

        app(CreateBookingFromCartAction::class)->execute($user, $cart);
    }

    public function test_estimate_endpoint_returns_distance_and_breakdown(): void
    {
        $this->app->instance(RouteDistanceProvider::class, $this->fakeProvider(20.0));
        [, $user, , $location, $model] = $this->setupCart(4000.0);
        Sanctum::actingAs($user);

        $res = $this->getJson("/api/v1/equipment/models/{$model->id}/mob-demob-estimate?project_location_id={$location->id}")
            ->assertOk();

        $res->assertJsonPath('data.distance_available', true)
            ->assertJsonPath('data.route_distance_km', 20)
            ->assertJsonPath('data.distance_rate_per_km', 4000)
            ->assertJsonPath('data.mob_cost', 80000)
            ->assertJsonPath('data.demob_cost', 80000)
            ->assertJsonPath('data.total_mob_demob', 160000);
    }

    public function test_estimate_endpoint_without_routing_returns_unavailable_without_fake_amounts(): void
    {
        [, $user, , $location, $model] = $this->setupCart(4000.0);
        Sanctum::actingAs($user);

        $res = $this->getJson("/api/v1/equipment/models/{$model->id}/mob-demob-estimate?project_location_id={$location->id}")
            ->assertOk();

        $res->assertJsonPath('data.distance_available', false)
            ->assertJsonPath('data.mob_cost', null)
            ->assertJsonPath('data.demob_cost', null)
            ->assertJsonPath('data.total_mob_demob', null);
    }

    public function test_estimate_distance_varies_with_selected_project_location(): void
    {
        // Test-only provider: route distance depends on destination longitude.
        $this->app->instance(RouteDistanceProvider::class, new class implements RouteDistanceProvider
        {
            public function distanceKm(float $originLat, float $originLng, float $destinationLat, float $destinationLng): ?float
            {
                return round(abs($destinationLng - $originLng) * 10, 2);
            }
        });

        [, $user, , $near, $model] = $this->setupCart(3000.0);
        $far = ProjectLocation::factory()->create([
            'user_id' => $user->id,
            'latitude' => -7.5,
            'longitude' => 110.0,
        ]);
        // Align origin longitudes for deterministic comparison.
        $model->pool->update(['longitude' => 106.8]);

        Sanctum::actingAs($user);
        $nearRes = $this->getJson("/api/v1/equipment/models/{$model->id}/mob-demob-estimate?project_location_id={$near->id}")->json('data');
        $farRes = $this->getJson("/api/v1/equipment/models/{$model->id}/mob-demob-estimate?project_location_id={$far->id}")->json('data');

        $this->assertNotEquals($nearRes['route_distance_km'], $farRes['route_distance_km']);
        $this->assertNotEquals($nearRes['total_mob_demob'], $farRes['total_mob_demob']);
    }

    public function test_pricing_engine_formula_direct(): void
    {
        $model = EquipmentModel::factory()->create(['is_active' => true]);
        EquipmentPrice::factory()->create([
            'equipment_model_id' => $model->id,
            'is_all_in' => true,
            'base_rate' => 100000.00,
            'distance_rate_per_km' => 2500.00,
            'effective_date' => now()->subMonth()->toDateString(),
        ]);

        $input = \App\DTOs\Pricing\LineItemPricingInput::fromArray([
            'equipment_model_id' => $model->id,
            'quantity' => 2,
            'start_date' => now()->addDay()->toDateString(),
            'end_date' => now()->addDays(3)->toDateString(),
            'is_all_in' => true,
            'route_distance_km' => 10.0,
        ]);

        $result = app(PricingCalculatorService::class)->calculateLineItem($input);

        $this->assertEqualsWithDelta(25000.0, $result->mobRatePerUnit, 0.01); // 10 x 2500
        $this->assertEqualsWithDelta(25000.0, $result->demobRatePerUnit, 0.01);
        $this->assertEqualsWithDelta(50000.0, $result->mobSubtotal, 0.01); // x2 units
        $this->assertEqualsWithDelta(50000.0, $result->demobSubtotal, 0.01);
        $this->assertTrue($result->distanceAvailable);
    }
}