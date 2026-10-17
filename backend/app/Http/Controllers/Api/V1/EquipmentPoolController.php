<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Equipment\StoreEquipmentPoolRequest;
use App\Http\Requests\Equipment\UpdateEquipmentPoolRequest;
use App\Http\Resources\EquipmentPoolResource;
use App\Models\EquipmentPool;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EquipmentPoolController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $query = EquipmentPool::query()->latest();

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $perPage = min(max((int) $request->query('per_page', 50), 1), 100);
        $paginated = $query->paginate($perPage);

        return $this->success(
            EquipmentPoolResource::collection($paginated->items()),
            'Daftar pool armada berhasil dimuat.',
            200,
            [
                'current_page' => $paginated->currentPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
                'last_page' => $paginated->lastPage(),
            ]
        );
    }

    public function store(StoreEquipmentPoolRequest $request): JsonResponse
    {
        $pool = EquipmentPool::create($request->validated());

        return $this->created(new EquipmentPoolResource($pool), 'Pool armada berhasil ditambahkan.');
    }

    public function update(UpdateEquipmentPoolRequest $request, EquipmentPool $equipmentPool): JsonResponse
    {
        $equipmentPool->update($request->validated());

        return $this->success(new EquipmentPoolResource($equipmentPool->fresh()), 'Pool armada berhasil diperbarui.');
    }

    public function destroy(EquipmentPool $equipmentPool): JsonResponse
    {
        $used = $equipmentPool->models()->exists() || $equipmentPool->units()->exists();
        if ($used) {
            return $this->error(
                'Pool tidak dapat dihapus karena masih dipakai model/unit armada.',
                [],
                409,
                'BUSINESS_RULE_VIOLATION'
            );
        }

        $equipmentPool->delete();

        return $this->success(null, 'Pool armada berhasil dihapus.');
    }
}