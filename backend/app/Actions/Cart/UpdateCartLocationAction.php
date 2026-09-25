<?php

namespace App\Actions\Cart;

use App\Exceptions\BusinessRuleException;
use App\Models\Cart;
use App\Models\ProjectLocation;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class UpdateCartLocationAction
{
    public function __construct(
        protected GetOrCreateUserCartAction $getOrCreateCart
    ) {}

    public function execute(User $user, int $projectLocationId): Cart
    {
        return DB::transaction(function () use ($user, $projectLocationId) {
            /** @var ProjectLocation|null $location */
            $location = ProjectLocation::find($projectLocationId);
            if (! $location || (int) $location->user_id !== (int) $user->id) {
                throw new BusinessRuleException('Lokasi proyek tidak valid atau bukan milik akun Anda.');
            }

            $cart = $this->getOrCreateCart->execute($user);
            $cart->update(['project_location_id' => $location->id]);

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
