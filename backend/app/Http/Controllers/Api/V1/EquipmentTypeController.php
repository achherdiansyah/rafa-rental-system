<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Equipment\CreateEquipmentTypeAction;
use App\Actions\Equipment\UpdateEquipmentTypeAction;
use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Equipment\StoreEquipmentTypeRequest;
use App\Http\Requests\Equipment\UpdateEquipmentTypeRequest;
use App\Http\Resources\EquipmentTypeResource;
use App\Models\EquipmentType;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EquipmentTypeController extends ApiController
{
    /**
     * List equipment types with optional search.
     */
    public function index(Request $request): JsonResponse
    {
        $query = EquipmentType::withCount('models')->latest();

        if ($search = $request->query('search')) {
            $query->where('name', 'like', "%{$search}%");
        }

        if ($request->boolean('all')) {
            $types = $query->get();

            return $this->success(EquipmentTypeResource::collection($types), 'Daftar tipe alat berhasil dimuat.');
        }

        $perPage = (int) $request->query('per_page', 15);
        $types = $query->paginate($perPage);

        return $this->success(
            EquipmentTypeResource::collection($types->items()),
            'Daftar tipe alat berhasil dimuat.',
            200,
            [
                'current_page' => $types->currentPage(),
                'per_page' => $types->perPage(),
                'total' => $types->total(),
                'last_page' => $types->lastPage(),
            ]
        );
    }

    /**
     * Show single equipment type details.
     */
    public function show(EquipmentType $type): JsonResponse
    {
        $type->loadCount('models');

        return $this->success(new EquipmentTypeResource($type), 'Detail tipe alat berhasil dimuat.');
    }

    /**
     * Store new equipment type (Admin/Owner).
     */
    public function store(StoreEquipmentTypeRequest $request, CreateEquipmentTypeAction $action): JsonResponse
    {
        $type = $action->execute($request->validated());

        return $this->created(new EquipmentTypeResource($type), 'Tipe alat baru berhasil ditambahkan.');
    }

    /**
     * Update equipment type (Admin/Owner).
     */
    public function update(UpdateEquipmentTypeRequest $request, EquipmentType $type, UpdateEquipmentTypeAction $action): JsonResponse
    {
        $updated = $action->execute($type, $request->validated());

        return $this->success(new EquipmentTypeResource($updated), 'Tipe alat berhasil diperbarui.');
    }

    /**
     * Soft delete equipment type (Admin/Owner).
     */
    public function destroy(Request $request, EquipmentType $type): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (! $user->can('manage-equipment')) {
            return $this->forbidden('Anda tidak memiliki wewenang untuk menghapus master tipe alat.');
        }

        if ($type->models()->exists()) {
            return $this->error('Tipe alat tidak dapat dihapus karena masih memiliki model armada terikat.', null, 409, 'CONFLICT');
        }

        $type->delete();

        return $this->success(null, 'Tipe alat berhasil dihapus.');
    }
}
