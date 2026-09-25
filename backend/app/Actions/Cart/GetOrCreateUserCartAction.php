<?php

namespace App\Actions\Cart;

use App\Models\Cart;
use App\Models\User;

class GetOrCreateUserCartAction
{
    public function execute(User $user): Cart
    {
        return Cart::firstOrCreate(
            ['user_id' => $user->id],
            ['project_location_id' => null]
        );
    }
}
