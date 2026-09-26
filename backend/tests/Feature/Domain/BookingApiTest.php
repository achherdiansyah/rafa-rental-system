<?php

namespace Tests\Feature\Domain;

use App\Enums\BookingStatus;
use App\Enums\EquipmentStatus;
use App\Enums\UserRole;
use App\Models\Booking;
use App\Models\BookingDetail;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\CustomerProfile;
use App\Models\EquipmentModel;
use App\Models\EquipmentPrice;
use App\Models\EquipmentUnit;
use App\Models\ProjectLocation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BookingApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Log::spy();
    }

    private function createCartWithItems(User $user, ProjectLocation $location, int $qty = 2): Cart
    {
        $cart = Cart::factory()->create([
            'user_id' => $user->id,
            'project_location_id' => $location->id,
        ]);

        CartItem::factory()->create([
            'cart_id' => $cart->id,
            'equipment_model_id' => $this->createModelWithAvailability(4)->id,
            'quantity' => $qty,
            'is_all_in' => false,
            'start_date' => now()->addDays(3)->toDateString(),
            'end_date' => now()->addDays(5)->toDateString(),
        ]);

        return $cart;
    }

    private function createModelWithAvailability(int $units = 3): EquipmentModel
    {
        $model = EquipmentModel::factory()->create(['is_active' => true]);
        EquipmentPrice::factory()->create(['equipment_model_id' => $model->id, 'base_rate' => 250000.00]);
        EquipmentUnit::factory()->count($units)->create([
            'equipment_model_id' => $model->id,
            'status' => EquipmentStatus::AVAILABLE,
        ]);

        return $model;
    }

    public function test_user_can_create_draft_booking_from_cart(): void
    {
        $user = User::factory()->create(['role' => UserRole::USER]);
        $location = ProjectLocation::factory()->create(['user_id' => $user->id]);
        $cart = $this->createCartWithItems($user, $location);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/bookings');

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'booking_code',
                    'status',
                    'project_location_id',
                    'total_amount',
                    'details' => ['*' => ['equipment_model_id', 'quantity', 'rental_rate_snapshot', 'subtotal']],
                ],
            ]);

        // Assert DRAFT status
        $this->assertEquals(BookingStatus::DRAFT->value, $response->json('data.status'));

        // Assert cart consumed
        $this->assertDatabaseCount('cart_items', 0);
        $this->assertNull($cart->fresh()->project_location_id);
    }

    public function test_create_booking_fails_when_cart_empty(): void
    {
        $user = User::factory()->create(['role' => UserRole::USER]);
        $location = ProjectLocation::factory()->create(['user_id' => $user->id]);

        Cart::factory()->create(['user_id' => $user->id, 'project_location_id' => $location->id]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/bookings');

        $response->assertStatus(409)
            ->assertJson(['code' => 'BUSINESS_RULE_VIOLATION']);
    }

    public function test_create_booking_fails_when_location_not_set(): void
    {
        $user = User::factory()->create(['role' => UserRole::USER]);

        $cart = Cart::factory()->create(['user_id' => $user->id, 'project_location_id' => null]);
        CartItem::factory()->create([
            'cart_id' => $cart->id,
            'equipment_model_id' => $this->createModelWithAvailability()->id,
            'quantity' => 1,
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/bookings');

        $response->assertStatus(409)
            ->assertJson(['code' => 'BUSINESS_RULE_VIOLATION']);
    }

    public function test_create_booking_fails_when_availability_insufficient(): void
    {
        $user = User::factory()->create(['role' => UserRole::USER]);
        $location = ProjectLocation::factory()->create(['user_id' => $user->id]);

        // Only 1 unit available, but 3 requested
        $model = $this->createModelWithAvailability(1);

        $cart = Cart::factory()->create(['user_id' => $user->id, 'project_location_id' => $location->id]);
        CartItem::factory()->create([
            'cart_id' => $cart->id,
            'equipment_model_id' => $model->id,
            'quantity' => 3,
            'start_date' => now()->addDays(3)->toDateString(),
            'end_date' => now()->addDays(5)->toDateString(),
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/bookings');

        $response->assertStatus(409)
            ->assertJson(['code' => 'BUSINESS_RULE_VIOLATION']);
        $this->assertStringContainsString('tidak mencukupi', $response->json('message'));
    }

    public function test_user_can_list_only_own_bookings(): void
    {
        $user1 = User::factory()->create(['role' => UserRole::USER]);
        $user2 = User::factory()->create(['role' => UserRole::USER]);

        Booking::factory()->create(['user_id' => $user1->id, 'status' => BookingStatus::DRAFT]);
        $booking2 = Booking::factory()->create(['user_id' => $user2->id, 'status' => BookingStatus::DRAFT]);

        Sanctum::actingAs($user1);

        $response = $this->getJson('/api/v1/bookings');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertNotEquals($booking2->id, $response->json('data.0.id'));
    }

    public function test_user_cannot_view_other_users_booking(): void
    {
        $user1 = User::factory()->create(['role' => UserRole::USER]);
        $user2 = User::factory()->create(['role' => UserRole::USER]);
        $booking2 = Booking::factory()->create(['user_id' => $user2->id]);

        Sanctum::actingAs($user1);

        $this->getJson("/api/v1/bookings/{$booking2->id}")->assertStatus(403);
        $this->postJson("/api/v1/bookings/{$booking2->id}/submit")->assertStatus(403);
    }

    public function test_user_can_submit_draft_booking_to_pending_approval(): void
    {
        $user = User::factory()->create(['role' => UserRole::USER]);
        CustomerProfile::factory()->verified()->create(['user_id' => $user->id]);

        $location = ProjectLocation::factory()->create(['user_id' => $user->id]);
        $booking = Booking::factory()->create([
            'user_id' => $user->id,
            'project_location_id' => $location->id,
            'status' => BookingStatus::DRAFT,
        ]);
        BookingDetail::factory()->create([
            'booking_id' => $booking->id,
            'equipment_model_id' => $this->createModelWithAvailability(2)->id,
            'quantity' => 1,
            'start_date' => now()->addDays(3)->toDateString(),
            'end_date' => now()->addDays(5)->toDateString(),
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson("/api/v1/bookings/{$booking->id}/submit");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'status' => BookingStatus::PENDING_APPROVAL->value,
                ],
            ]);

        $this->assertEquals(BookingStatus::PENDING_APPROVAL, $booking->fresh()->status);
    }

    public function test_submit_fails_for_non_draft_status(): void
    {
        $user = User::factory()->create(['role' => UserRole::USER]);
        CustomerProfile::factory()->verified()->create(['user_id' => $user->id]);

        $booking = Booking::factory()->create([
            'user_id' => $user->id,
            'status' => BookingStatus::PENDING_APPROVAL,
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson("/api/v1/bookings/{$booking->id}/submit");

        $response->assertStatus(409)
            ->assertJson(['code' => 'INVALID_STATE_TRANSITION']);
    }

    public function test_submit_fails_when_profile_not_verified(): void
    {
        $user = User::factory()->create(['role' => UserRole::USER]);
        CustomerProfile::factory()->create(['user_id' => $user->id]); // UNVERIFIED

        $booking = Booking::factory()->create([
            'user_id' => $user->id,
            'status' => BookingStatus::DRAFT,
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson("/api/v1/bookings/{$booking->id}/submit");

        $response->assertStatus(409)
            ->assertJson(['code' => 'BUSINESS_RULE_VIOLATION']);
        $this->assertStringContainsString('verifikasi', strtolower($response->json('message')));
    }

    public function test_unauthenticated_requests_rejected(): void
    {
        $this->getJson('/api/v1/bookings')->assertStatus(401);
        $this->postJson('/api/v1/bookings')->assertStatus(401);
    }
}
