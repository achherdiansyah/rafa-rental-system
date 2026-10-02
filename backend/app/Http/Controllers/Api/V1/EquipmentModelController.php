<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Equipment\CreateEquipmentModelAction;
use App\Actions\Equipment\UpdateEquipmentModelAction;
use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Equipment\StoreEquipmentModelRequest;
use App\Http\Requests\Equipment\UpdateEquipmentModelRequest;
use App\Http\Resources\EquipmentModelResource;
use App\Models\EquipmentModel;
use App\Models\User;
use App\Services\Equipment\EquipmentAvailabilityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EquipmentModelController extends ApiController
{
    /**
     * List equipment models with filters and search.
     */
    public function index(Request $request): JsonResponse
    {
        $query = EquipmentModel::with(['type', 'attachments', 'prices'])->withCount('units')->latest();

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('model_name', 'like', "%{$search}%")
                    ->orWhere('brand', 'like', "%{$search}%");
            });
        }

        if ($typeId = $request->query('equipment_type_id')) {
            $query->where('equipment_type_id', $typeId);
        }

        if ($brand = $request->query('brand')) {
            $query->where('brand', $brand);
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $perPage = (int) $request->query('per_page', 15);
        $models = $query->paginate($perPage);

        // Attach real available unit counts (source of truth: physical unit status + booking buffer)
        $availabilityService = app(EquipmentAvailabilityService::class);
        $modelIds = collect($models->items())->pluck('id')->all();
        $availMap = $availabilityService->getAvailabilityMap($modelIds);

        $items = collect($models->items())->map(function (EquipmentModel $model) use ($availMap) {
            $resource = new EquipmentModelResource($model);
            $array = $resource->resolve();
            $array['available_units_count'] = (int) ($availMap->get($model->id, 0));
            return $array;
        });

        return $this->success(
            $items,
            'Daftar model armada berhasil dimuat.',
            200,
            [
                'current_page' => $models->currentPage(),
                'per_page' => $models->perPage(),
                'total' => $models->total(),
                'last_page' => $models->lastPage(),
            ]
        );
    }

    /**
     * Show single equipment model details.
     */
    public function show(EquipmentModel $model): JsonResponse
    {
        $model->load(['type', 'prices', 'attachments']);
        $model->loadCount('units');

        $availService = app(EquipmentAvailabilityService::class);
        $availableUnitsCount = $availService->getAvailableUnitsCount($model);

        $resource = new EquipmentModelResource($model);
        $data = $resource->resolve();
        $data['available_units_count'] = $availableUnitsCount;

        return $this->success($data, 'Detail model armada berhasil dimuat.');
    }

    /**
     * Store new equipment model (Admin/Owner).
     */
    public function store(StoreEquipmentModelRequest $request, CreateEquipmentModelAction $action): JsonResponse
    {
        $model = $action->execute($request->validated());

        return $this->created(new EquipmentModelResource($model), 'Model armada baru berhasil ditambahkan.');
    }

    /**
     * Update equipment model (Admin/Owner).
     */
    public function update(UpdateEquipmentModelRequest $request, EquipmentModel $model, UpdateEquipmentModelAction $action): JsonResponse
    {
        $updated = $action->execute($model, $request->validated());

        return $this->success(new EquipmentModelResource($updated), 'Model armada berhasil diperbarui.');
    }

    /**
     * Soft delete equipment model (Admin/Owner).
     */
    public function destroy(Request $request, EquipmentModel $model): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (! $user->can('manage-equipment')) {
            return $this->forbidden('Anda tidak memiliki wewenang untuk menghapus master model armada.');
        }

        if ($model->units()->exists()) {
            return $this->error('Model armada tidak dapat dihapus karena masih memiliki unit fisik aktif.', null, 409, 'CONFLICT');
        }

        $model->delete();

        return $this->success(null, 'Model armada berhasil dihapus.');
    }
}
