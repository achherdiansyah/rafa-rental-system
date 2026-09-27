<?php

namespace App\Actions\Cart;

use App\Models\Cart;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ClearCartAction
{
    public function __construct(
        protected GetOrCreateUserCartAction $getOrCreateCart
    ) {}

    public function execute(User $user): Cart
    {
        return DB::transaction(function () use ($user) {
            $cart = $this->getOrCreateCart->execute($user);
            $cart->items()->delete();
            $cart->update(['project_location_id' => null]);

            $cart->load(['projectLocation', 'items']);

            return $cart;
        });
    }
}
