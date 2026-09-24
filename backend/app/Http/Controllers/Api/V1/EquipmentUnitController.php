<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Equipment\CreateEquipmentUnitAction;
use App\Actions\Equipment\UpdateEquipmentUnitAction;
use App\Actions\Equipment\UpdateEquipmentUnitStatusAction;
use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Equipment\StoreEquipmentUnitRequest;
use App\Http\Requests\Equipment\UpdateEquipmentUnitRequest;
use App\Http\Requests\Equipment\UpdateEquipmentUnitStatusRequest;
use App\Http\Resources\EquipmentUnitResource;
use App\Models\EquipmentUnit;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EquipmentUnitController extends ApiController
{
    /**
     * List physical units with filters and search.
     */
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (! $user->can('manage-equipment')) {
            return $this->forbidden('Hanya Admin dan Owner yang dapat melihat daftar unit fisik secara langsung.');
        }

        $query = EquipmentUnit::with('model.type')->latest();

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('serial_number', 'like', "%{$search}%")
                    ->orWhere('plate_number', 'like', "%{$search}%");
            });
        }

        if ($modelId = $request->query('equipment_model_id')) {
            $query->where('equipment_model_id', $modelId);
        }

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        $perPage = (int) $request->query('per_page', 15);
        $units = $query->paginate($perPage);

        return $this->success(
            EquipmentUnitResource::collection($units->items()),
            'Daftar unit fisik berhasil dimuat.',
            200,
            [
                'current_page' => $units->currentPage(),
                'per_page' => $units->perPage(),
                'total' => $units->total(),
                'last_page' => $units->lastPage(),
            ]
        );
    }

    /**
     * Show single physical unit details.
     */
    public function show(Request $request, EquipmentUnit $unit): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (! $user->can('manage-equipment')) {
            return $this->forbidden('Hanya Admin dan Owner yang dapat melihat detail unit fisik.');
        }

        $unit->load('model.type');

        return $this->success(new EquipmentUnitResource($unit), 'Detail unit fisik berhasil dimuat.');
    }

    /**
     * Store new physical unit (Admin/Owner).
     */
    public function store(StoreEquipmentUnitRequest $request, CreateEquipmentUnitAction $action): JsonResponse
    {
        $unit = $action->execute($request->validated());

        return $this->created(new EquipmentUnitResource($unit), 'Unit fisik baru berhasil didaftarkan.');
    }

    /**
     * Update physical unit (Admin/Owner).
     */
    public function update(UpdateEquipmentUnitRequest $request, EquipmentUnit $unit, UpdateEquipmentUnitAction $action): JsonResponse
    {
        $updated = $action->execute($unit, $request->validated());

        return $this->success(new EquipmentUnitResource($updated), 'Data unit fisik berhasil diperbarui.');
    }

    /**
     * Manually update physical unit status (e.g. Maintenance/Decommission).
     */
    public function updateStatus(UpdateEquipmentUnitStatusRequest $request, EquipmentUnit $unit, UpdateEquipmentUnitStatusAction $action): JsonResponse
    {
        $updated = $action->execute(
            $unit,
            (string) $request->validated('status'),
            $request->validated('notes')
        );

        return $this->success(new EquipmentUnitResource($updated), 'Status unit fisik berhasil diperbarui.');
    }

    /**
     * Soft delete physical unit (Admin/Owner).
     */
    public function destroy(Request $request, EquipmentUnit $unit): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (! $user->can('manage-equipment')) {
            return $this->forbidden('Anda tidak memiliki wewenang untuk menghapus unit fisik.');
        }

        // Check if unit is in active state or active assignment
        if ($unit->status->value !== 'AVAILABLE' && $unit->status->value !== 'DECOMMISSIONED') {
            return $this->error('Unit tidak dapat dihapus saat sedang tidak berstatus AVAILABLE atau DECOMMISSIONED.', null, 409, 'CONFLICT');
        }

        $unit->delete();

        return $this->success(null, 'Unit fisik berhasil dihapus.');
    }
}
