<?php

namespace Tests\Feature\Domain;

use App\Enums\UserRole;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\EquipmentModel;
use App\Models\ProjectLocation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CartApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_view_own_cart_and_it_auto_creates_if_missing(): void
    {
        $user = User::factory()->create(['role' => UserRole::USER]);
        Sanctum::actingAs($user);

        // First call should create the cart
        $response = $this->getJson('/api/v1/cart');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'user_id' => $user->id,
                    'items' => [],
                ],
            ]);

        $this->assertDatabaseHas('carts', ['user_id' => $user->id]);
    }

    public function test_user_can_add_item_to_cart_and_increment_quantity_on_duplicate(): void
    {
        $user = User::factory()->create(['role' => UserRole::USER]);
        Sanctum::actingAs($user);

        $model = EquipmentModel::factory()->create(['is_active' => true]);

        $payload = [
            'equipment_model_id' => $model->id,
            'quantity' => 2,
            'is_all_in' => false,
            'start_date' => now()->addDays(2)->toDateString(),
            'end_date' => now()->addDays(5)->toDateString(),
        ];

        // 1. Add item
        $response1 = $this->postJson('/api/v1/cart/items', $payload);
        $response1->assertStatus(201);
        $this->assertCount(1, $response1->json('data.items'));
        $this->assertEquals(2, $response1->json('data.items.0.quantity'));

        // 2. Add exact identical item again
        $response2 = $this->postJson('/api/v1/cart/items', $payload);
        $response2->assertStatus(201);

        // Quantity should be 4 now
        $this->assertCount(1, $response2->json('data.items'));
        $this->assertEquals(4, $response2->json('data.items.0.quantity'));
    }

    public function test_user_can_update_cart_item(): void
    {
        $user = User::factory()->create(['role' => UserRole::USER]);
        Sanctum::actingAs($user);

        $cart = Cart::factory()->create(['user_id' => $user->id]);
        $item = CartItem::factory()->create([
            'cart_id' => $cart->id,
            'quantity' => 1,
            'is_all_in' => false,
        ]);

        $response = $this->putJson("/api/v1/cart/items/{$item->id}", [
            'quantity' => 5,
            'is_all_in' => true,
        ]);

        $response->assertStatus(200);
        $this->assertEquals(5, $response->json('data.items.0.quantity'));
        $this->assertTrue($response->json('data.items.0.is_all_in'));
    }

    public function test_user_can_delete_cart_item(): void
    {
        $user = User::factory()->create(['role' => UserRole::USER]);
        Sanctum::actingAs($user);

        $cart = Cart::factory()->create(['user_id' => $user->id]);
        $item = CartItem::factory()->create(['cart_id' => $cart->id]);

        $this->deleteJson("/api/v1/cart/items/{$item->id}")->assertStatus(200);
        $this->assertDatabaseMissing('cart_items', ['id' => $item->id]);
    }

    public function test_user_can_clear_entire_cart(): void
    {
        $user = User::factory()->create(['role' => UserRole::USER]);
        Sanctum::actingAs($user);

        $cart = Cart::factory()->create(['user_id' => $user->id]);
        CartItem::factory()->count(3)->create(['cart_id' => $cart->id]);

        $response = $this->deleteJson('/api/v1/cart');

        $response->assertStatus(200);
        $this->assertCount(0, $response->json('data.items'));
        $this->assertDatabaseCount('cart_items', 0);
    }

    public function test_user_can_update_cart_location(): void
    {
        $user = User::factory()->create(['role' => UserRole::USER]);
        Sanctum::actingAs($user);

        $loc = ProjectLocation::factory()->create(['user_id' => $user->id]);

        $response = $this->putJson('/api/v1/cart/location', [
            'project_location_id' => $loc->id,
        ]);

        $response->assertStatus(200);
        $this->assertEquals($loc->id, $response->json('data.project_location_id'));
    }

    public function test_user_cannot_access_other_users_cart_items(): void
    {
        $user1 = User::factory()->create(['role' => UserRole::USER]);
        $user2 = User::factory()->create(['role' => UserRole::USER]);

        $cart2 = Cart::factory()->create(['user_id' => $user2->id]);
        $item2 = CartItem::factory()->create(['cart_id' => $cart2->id]);

        Sanctum::actingAs($user1);

        $this->putJson("/api/v1/cart/items/{$item2->id}", ['quantity' => 10])->assertStatus(403);
        $this->deleteJson("/api/v1/cart/items/{$item2->id}")->assertStatus(403);
    }

    public function test_validation_fails_for_inactive_equipment(): void
    {
        $user = User::factory()->create(['role' => UserRole::USER]);
        Sanctum::actingAs($user);

        $inactiveModel = EquipmentModel::factory()->create(['is_active' => false]);

        $response = $this->postJson('/api/v1/cart/items', [
            'equipment_model_id' => $inactiveModel->id,
            'quantity' => 1,
            'is_all_in' => false,
            'start_date' => now()->addDays(2)->toDateString(),
            'end_date' => now()->addDays(5)->toDateString(),
        ]);

        $response->assertStatus(409)->assertJson(['code' => 'BUSINESS_RULE_VIOLATION']);
    }

    public function test_validation_fails_for_other_users_project_location(): void
    {
        $user1 = User::factory()->create(['role' => UserRole::USER]);
        $user2 = User::factory()->create(['role' => UserRole::USER]);

        $loc2 = ProjectLocation::factory()->create(['user_id' => $user2->id]);

        Sanctum::actingAs($user1);

        $response = $this->putJson('/api/v1/cart/location', [
            'project_location_id' => $loc2->id,
        ]);

        $response->assertStatus(409)->assertJson(['code' => 'BUSINESS_RULE_VIOLATION']);
    }
}
