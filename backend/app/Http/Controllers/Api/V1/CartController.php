<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Cart\AddCartItemAction;
use App\Actions\Cart\ClearCartAction;
use App\Actions\Cart\DeleteCartItemAction;
use App\Actions\Cart\GetOrCreateUserCartAction;
use App\Actions\Cart\UpdateCartItemAction;
use App\Actions\Cart\UpdateCartLocationAction;
use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Cart\AddCartItemRequest;
use App\Http\Requests\Cart\UpdateCartItemRequest;
use App\Http\Requests\Cart\UpdateCartLocationRequest;
use App\Http\Resources\CartResource;
use App\Models\CartItem;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class CartController extends ApiController
{
    /**
     * View current active cart for authenticated user.
     */
    public function getCart(Request $request, GetOrCreateUserCartAction $action): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $cart = $action->execute($user);
        $cart->load([
            'projectLocation',
            'items.model' => function ($q) {
                $q->with(['type', 'prices', 'attachments']);
            },
        ]);

        return $this->success(
            new CartResource($cart),
            'Keranjang sewa berhasil dimuat.'
        );
    }

    /**
     * Add line item to cart.
     */
    public function addItem(AddCartItemRequest $request, AddCartItemAction $action): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $cart = $action->execute($user, $request->validated());

        return $this->created(
            new CartResource($cart),
            'Item armada berhasil ditambahkan ke keranjang sewa.'
        );
    }

    /**
     * Update existing cart item.
     */
    public function updateItem(
        UpdateCartItemRequest $request,
        CartItem $cartItem,
        UpdateCartItemAction $action
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();

        Gate::authorize('update', $cartItem);

        $cart = $action->execute($user, $cartItem, $request->validated());

        return $this->success(
            new CartResource($cart),
            'Item keranjang sewa berhasil diperbarui.'
        );
    }

    /**
     * Remove item from cart.
     */
    public function removeItem(
        Request $request,
        CartItem $cartItem,
        DeleteCartItemAction $action
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();

        Gate::authorize('delete', $cartItem);

        $cart = $action->execute($user, $cartItem);

        return $this->success(
            new CartResource($cart),
            'Item berhasil dihapus dari keranjang sewa.'
        );
    }

    /**
     * Clear all items in cart.
     */
    public function clear(Request $request, ClearCartAction $action): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $cart = $action->execute($user);

        return $this->success(
            new CartResource($cart),
            'Keranjang sewa berhasil dikosongkan.'
        );
    }

    /**
     * Update cart project location.
     */
    public function updateLocation(
        UpdateCartLocationRequest $request,
        UpdateCartLocationAction $action
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();

        $cart = $action->execute($user, (int) $request->validated('project_location_id'));

        return $this->success(
            new CartResource($cart),
            'Lokasi proyek keranjang sewa berhasil diperbarui.'
        );
    }
}
