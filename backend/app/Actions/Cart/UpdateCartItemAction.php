<?php

namespace App\Actions\Cart;

use App\Exceptions\BusinessRuleException;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class UpdateCartItemAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(User $user, CartItem $cartItem, array $data): Cart
    {
        return DB::transaction(function () use ($user, $cartItem, $data) {
            /** @var Cart $cart */
            $cart = $cartItem->cart;

            if ((int) $cart->user_id !== (int) $user->id) {
                throw new BusinessRuleException('Item keranjang bukan milik akun Anda.');
            }

            $cartItem->update($data);

            $cart->load([
                'projectLocation',
                'items.model' => function ($q) {
                    $q->with(['type', 'prices', 'attachments']);
                },
            ]);

            return $cart;
        });
    }
}
