<?php

namespace App\Actions\Cart;

use App\Exceptions\BusinessRuleException;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\User;
use App\Services\Equipment\EquipmentAvailabilityService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class UpdateCartItemAction
{
    public function __construct(
        protected EquipmentAvailabilityService $availability
    ) {}

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

            $cartItem->loadMissing('model');
            $quantity = (int) ($data['quantity'] ?? $cartItem->quantity);
            $start = $data['start_date'] ?? $cartItem->start_date?->toDateString();
            $end = $data['end_date'] ?? $cartItem->end_date?->toDateString();

            if ($cartItem->model && $start && $end) {
                $available = $this->availability->getAvailableUnitsCount(
                    $cartItem->model,
                    Carbon::parse($start),
                    Carbon::parse($end)
                );

                if ($available < $quantity) {
                    throw new BusinessRuleException(
                        "Ketersediaan unit tidak mencukupi untuk periode ini: diminta {$quantity} unit, tersedia {$available} unit."
                    );
                }
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
