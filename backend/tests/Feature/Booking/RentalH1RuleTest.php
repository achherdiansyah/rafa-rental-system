<?php

namespace Tests\Feature\Booking;

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
use App\Support\RentalDateWindow;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RentalH1RuleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Controlled "today" in Asia/Jakarta -> minimum start is 2026-10-21.
        Carbon::setTestNow(Carbon::create(2026, 10, 20, 9, 0, 0, RentalDateWindow::TIMEZONE));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function modelWithAvailability(int $units = 4): EquipmentModel
    {
        $model = EquipmentModel::factory()->create(['is_active' => true]);
        EquipmentPrice::factory()->create(['equipment_model_id' => $model->id, 'base_rate' => 250000.00]);
        EquipmentUnit::factory()->count($units)->create([
            'equipment_model_id' => $model->id,
            'status' => EquipmentStatus::AVAILABLE,
        ]);

        return $model;
    }

    private function cartPayload(EquipmentModel $model, string $start, string $end, int $qty = 1): array
    {
        return [
            'equipment_model_id' => $model->id,
            'quantity' => $qty,
            'is_all_in' => false,
            'start_date' => $start,
            'end_date' => $end,
        ];
    }

    public function test_cart_rejects_yesterday_today_and_accepts_tomorrow(): void
    {
        $user = User::factory()->create(['role' => UserRole::USER]);
        Sanctum::actingAs($user);
        $model = $this->modelWithAvailability();

        $this->postJson('/api/v1/cart/items', $this->cartPayload($model, '2026-10-19', '2026-10-25'))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['start_date'])
            ->assertJsonPath('errors.start_date.0', RentalDateWindow::START_MESSAGE);

        $this->postJson('/api/v1/cart/items', $this->cartPayload($model, '2026-10-20', '2026-10-25'))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['start_date']);

        $this->assertDatabaseCount('cart_items', 0);

        $this->postJson('/api/v1/cart/items', $this->cartPayload($model, '2026-10-21', '2026-10-25'))
            ->assertStatus(201);
        $this->assertDatabaseHas('cart_items', ['start_date' => '2026-10-21 00:00:00']);
    }

    public function test_cart_rejects_end_date_before_start_date(): void
    {
        $user = User::factory()->create(['role' => UserRole::USER]);
        Sanctum::actingAs($user);
        $model = $this->modelWithAvailability();

        $this->postJson('/api/v1/cart/items', $this->cartPayload($model, '2026-10-25', '2026-10-21'))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['end_date'])
            ->assertJsonPath('errors.end_date.0', RentalDateWindow::END_MESSAGE);
    }

    public function test_update_cart_item_rejects_today(): void
    {
        $user = User::factory()->create(['role' => UserRole::USER]);
        Sanctum::actingAs($user);
        $model = $this->modelWithAvailability();
        $cart = Cart::factory()->create(['user_id' => $user->id]);
        $item = CartItem::factory()->create([
            'cart_id' => $cart->id,
            'equipment_model_id' => $model->id,
            'quantity' => 1,
            'start_date' => '2026-10-21',
            'end_date' => '2026-10-25',
        ]);

        $this->putJson("/api/v1/cart/items/{$item->id}", ['start_date' => '2026-10-20'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['start_date']);

        $this->assertSame('2026-10-21', $item->fresh()->start_date->toDateString());
    }

    public function test_checkout_rejects_cart_item_that_became_stale_after_day_change(): void
    {
        $user = User::factory()->create(['role' => UserRole::USER]);
        CustomerProfile::factory()->verified()->create(['user_id' => $user->id]);
        $location = ProjectLocation::factory()->create(['user_id' => $user->id]);
        $model = $this->modelWithAvailability();

        $cart = Cart::factory()->create([
            'user_id' => $user->id,
            'project_location_id' => $location->id,
        ]);
        // Inserted directly (bypassing request validation) to simulate a cart saved yesterday.
        CartItem::factory()->create([
            'cart_id' => $cart->id,
            'equipment_model_id' => $model->id,
            'quantity' => 1,
            'is_all_in' => false,
            'start_date' => '2026-10-20',
            'end_date' => '2026-10-25',
        ]);

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/bookings')
            ->assertStatus(409)
            ->assertJson(['code' => 'BUSINESS_RULE_VIOLATION'])
            ->assertJsonPath('message', RentalDateWindow::START_MESSAGE);

        $this->assertDatabaseCount('bookings', 0);
        $this->assertDatabaseCount('cart_items', 1);
    }

    public function test_submit_rejects_draft_booking_with_stale_start_date(): void
    {
        $user = User::factory()->create(['role' => UserRole::USER]);
        CustomerProfile::factory()->verified()->create(['user_id' => $user->id]);
        $location = ProjectLocation::factory()->create(['user_id' => $user->id]);
        $model = $this->modelWithAvailability();

        $booking = Booking::factory()->create([
            'user_id' => $user->id,
            'project_location_id' => $location->id,
            'status' => BookingStatus::DRAFT,
        ]);
        BookingDetail::factory()->create([
            'booking_id' => $booking->id,
            'equipment_model_id' => $model->id,
            'quantity' => 1,
            'start_date' => '2026-10-19',
            'end_date' => '2026-10-25',
        ]);

        Sanctum::actingAs($user);

        $this->postJson("/api/v1/bookings/{$booking->id}/submit")
            ->assertStatus(409)
            ->assertJson(['code' => 'BUSINESS_RULE_VIOLATION'])
            ->assertJsonPath('message', RentalDateWindow::START_MESSAGE);

        $this->assertEquals(BookingStatus::DRAFT, $booking->fresh()->status);
    }

    public function test_reschedule_rejects_today_as_new_start_date(): void
    {
        $user = User::factory()->create(['role' => UserRole::USER]);
        $location = ProjectLocation::factory()->create(['user_id' => $user->id]);
        $booking = Booking::factory()->create([
            'user_id' => $user->id,
            'project_location_id' => $location->id,
            'status' => BookingStatus::PENDING_APPROVAL,
        ]);

        Sanctum::actingAs($user);

        $this->postJson("/api/v1/bookings/{$booking->id}/reschedule", [
            'new_start_date' => '2026-10-20',
            'new_end_date' => '2026-10-25',
            'reason' => 'Proyek dimajukan oleh pemilik lahan.',
        ])->assertStatus(422)->assertJsonValidationErrors(['new_start_date']);
    }

    public function test_availability_still_enforced_after_h1_passes(): void
    {
        $user = User::factory()->create(['role' => UserRole::USER]);
        Sanctum::actingAs($user);
        $model = $this->modelWithAvailability(1);

        $response = $this->postJson('/api/v1/cart/items', $this->cartPayload($model, '2026-10-21', '2026-10-25', 3))
            ->assertStatus(409)
            ->assertJson(['code' => 'BUSINESS_RULE_VIOLATION']);

        $this->assertStringContainsString('Ketersediaan unit tidak mencukupi', $response->json('message'));
        $this->assertDatabaseCount('cart_items', 0);
    }
}
