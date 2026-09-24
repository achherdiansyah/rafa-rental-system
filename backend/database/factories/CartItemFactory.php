<?php

namespace Database\Factories;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\EquipmentModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CartItem>
 */
class CartItemFactory extends Factory
{
    public function definition(): array
    {
        $start = now()->addDays(3);

        return [
            'cart_id' => Cart::factory(),
            'equipment_model_id' => EquipmentModel::factory(),
            'quantity' => fake()->numberBetween(1, 3),
            'is_all_in' => false,
            'start_date' => $start->toDateString(),
            'end_date' => $start->addDays(7)->toDateString(),
        ];
    }
}
