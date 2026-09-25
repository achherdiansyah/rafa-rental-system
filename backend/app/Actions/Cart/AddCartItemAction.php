<?php

namespace App\Actions\Cart;

use App\Exceptions\BusinessRuleException;
use App\Models\Cart;
use App\Models\EquipmentModel;
use App\Models\ProjectLocation;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AddCartItemAction
{
    public function __construct(
        protected GetOrCreateUserCartAction $getOrCreateCart
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(User $user, array $data): Cart
    {
        return DB::transaction(function () use ($user, $data) {
            // 1. Verify equipment model is active
            /** @var EquipmentModel|null $model */
            $model = EquipmentModel::find($data['equipment_model_id']);
            if (! $model || ! $model->is_active) {
                throw new BusinessRuleException(
                    'Model alat berat yang dipilih tidak ditemukan atau sedang tidak aktif.'
                );
            }

            // 2. Get or initialize user's cart
            $cart = $this->getOrCreateCart->execute($user);

            // 3. Handle project location if passed
            if (! empty($data['project_location_id'])) {
                /** @var ProjectLocation|null $location */
                $location = ProjectLocation::find($data['project_location_id']);
                if (! $location || (int) $location->user_id !== (int) $user->id) {
                    throw new BusinessRuleException(
                        'Lokasi proyek tidak valid atau bukan milik akun Anda.'
                    );
                }

                $cart->update(['project_location_id' => $location->id]);
            }

            // 4. Check for duplicate/identical cart item
            $existingItem = $cart->items()
                ->where('equipment_model_id', $data['equipment_model_id'])
                ->where('is_all_in', $data['is_all_in'])
                ->where('start_date', $data['start_date'])
                ->where('end_date', $data['end_date'])
                ->first();

            if ($existingItem) {
                $existingItem->increment('quantity', $data['quantity']);
            } else {
                $cart->items()->create([
                    'equipment_model_id' => $data['equipment_model_id'],
                    'quantity' => $data['quantity'],
                    'is_all_in' => $data['is_all_in'],
                    'start_date' => $data['start_date'],
                    'end_date' => $data['end_date'],
                ]);
            }

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
