<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Equipment\Pricing\CreateEquipmentPriceAction;
use App\Actions\Equipment\Pricing\UpdateEquipmentPriceAction;
use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Equipment\Pricing\StoreEquipmentPriceRequest;
use App\Http\Requests\Equipment\Pricing\UpdateEquipmentPriceRequest;
use App\Http\Resources\EquipmentPriceResource;
use App\Models\EquipmentPrice;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EquipmentPriceController extends ApiController
{
    /**
     * List all prices for a given model or generally.
     */
    public function index(Request $request): JsonResponse
    {
        $query = EquipmentPrice::with(['model.type', 'versions.changedByUser'])->latest('effective_date');

        if ($modelId = $request->query('equipment_model_id')) {
            $query->where('equipment_model_id', $modelId);
        }

        if ($request->has('is_all_in')) {
            $query->where('is_all_in', $request->boolean('is_all_in'));
        }

        $perPage = (int) $request->query('per_page', 15);
        $prices = $query->paginate($perPage);

        return $this->success(
            EquipmentPriceResource::collection($prices->items()),
            'Daftar master harga armada berhasil dimuat.',
            200,
            [
                'current_page' => $prices->currentPage(),
                'per_page' => $prices->perPage(),
                'total' => $prices->total(),
                'last_page' => $prices->lastPage(),
            ]
        );
    }

    /**
     * Show single price details with full version history.
     */
    public function show(EquipmentPrice $price): JsonResponse
    {
        $price->load(['model.type', 'versions.changedByUser']);

        return $this->success(new EquipmentPriceResource($price), 'Detail master harga berhasil dimuat.');
    }

    /**
     * Store new master pricing entry (Owner exclusive).
     */
    public function store(StoreEquipmentPriceRequest $request, CreateEquipmentPriceAction $action): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $price = $action->execute($request->validated(), $user);

        return $this->created(new EquipmentPriceResource($price), 'Master harga baru berhasil ditetapkan.');
    }

    /**
     * Update master pricing entry and record historical version (Owner exclusive).
     */
    public function update(UpdateEquipmentPriceRequest $request, EquipmentPrice $price, UpdateEquipmentPriceAction $action): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $updated = $action->execute($price, $request->validated(), $user);

        return $this->success(new EquipmentPriceResource($updated), 'Tarif harga berhasil diperbarui dan versi baru tercatat.');
    }
}
