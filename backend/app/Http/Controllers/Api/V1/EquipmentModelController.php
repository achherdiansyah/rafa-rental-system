<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Equipment\CreateEquipmentModelAction;
use App\Actions\Equipment\UpdateEquipmentModelAction;
use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Equipment\StoreEquipmentModelRequest;
use App\Http\Requests\Equipment\UpdateEquipmentModelRequest;
use App\Http\Resources\EquipmentModelResource;
use App\Models\EquipmentModel;
use App\Models\ProjectLocation;
use App\Models\User;
use App\Services\Equipment\EquipmentAvailabilityService;
use App\Services\Pricing\MobDemobEstimator;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EquipmentModelController extends ApiController
{
    /**
     * List equipment models with filters and search.
     */
    public function index(Request $request): JsonResponse
    {
        $query = EquipmentModel::with(['type', 'pool', 'attachments', 'prices'])->withCount('units')->latest();

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

        // Attach real availability status (source of truth: physical units + bookings)
        $availabilityService = app(EquipmentAvailabilityService::class);
        $modelIds = collect($models->items())->pluck('id')->all();
        [$start, $end] = $this->periodFromRequest($request);
        $summaries = $availabilityService->availabilitySummaryMap($modelIds, $start, $end);

        $items = collect($models->items())->map(function (EquipmentModel $model) use ($summaries) {
            $resource = new EquipmentModelResource($model);
            $array = $resource->resolve();
            $summary = $summaries->get($model->id, []);
            $array['available_units_count'] = (int) ($summary['available_units'] ?? 0);
            $array['total_units'] = (int) ($summary['total_units'] ?? 0);
            $array['maintenance_units'] = (int) ($summary['maintenance_units'] ?? 0);
            $array['rented_until'] = $summary['rented_until'] ?? null;
            $array['booked_from'] = $summary['booked_from'] ?? null;
            $array['availability_status'] = $summary['status'] ?? 'UNAVAILABLE';
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
    public function show(Request $request, EquipmentModel $model): JsonResponse
    {
        $model->load(['type', 'pool', 'prices', 'attachments']);
        $model->loadCount('units');

        $availService = app(EquipmentAvailabilityService::class);
        [$start, $end] = $this->periodFromRequest($request);
        $summary = $availService->availabilitySummaryMap([$model->id], $start, $end)->get($model->id, []);

        $resource = new EquipmentModelResource($model);
        $data = $resource->resolve();
        $data['available_units_count'] = (int) ($summary['available_units'] ?? 0);
        $data['total_units'] = (int) ($summary['total_units'] ?? 0);
        $data['maintenance_units'] = (int) ($summary['maintenance_units'] ?? 0);
        $data['rented_until'] = $summary['rented_until'] ?? null;
        $data['booked_from'] = $summary['booked_from'] ?? null;
        $data['availability_status'] = $summary['status'] ?? 'UNAVAILABLE';

        return $this->success($data, 'Detail model armada berhasil dimuat.');
    }

    /**
     * Estimate MOB/DEMOB for a model against the user's selected project location.
     * Distance comes from the configured road-routing provider only; when it is
     * unavailable the response explicitly says so and returns no fake amounts.
     */
    public function estimateMobDemob(
        Request $request,
        EquipmentModel $model,
        MobDemobEstimator $estimator
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();

        $validated = $request->validate([
            'project_location_id' => ['required', 'integer', 'exists:project_locations,id'],
            'is_all_in' => ['nullable', 'boolean'],
        ]);

        /** @var ProjectLocation $project */
        $project = ProjectLocation::findOrFail($validated['project_location_id']);

        if (! $user->isAdmin() && ! $user->isOwner() && (int) $project->user_id !== (int) $user->id) {
            return $this->forbidden('Lokasi proyek bukan milik akun Anda.');
        }

        $model->loadMissing('pool');

        $estimate = $estimator->estimate(
            $model,
            $project,
            (bool) ($validated['is_all_in'] ?? true)
        );

        return $this->success($estimate, $estimate['message']);
    }

    /**
     * Safely parse optional `start_date` / `end_date` query params.
     *
     * @return array{0: ?CarbonInterface, 1: ?CarbonInterface}
     */
    private function periodFromRequest(Request $request): array
    {
        $start = null;
        $end = null;

        try {
            if ($request->filled('start_date')) {
                $start = Carbon::parse((string) $request->query('start_date'))->startOfDay();
            }
            if ($request->filled('end_date')) {
                $end = Carbon::parse((string) $request->query('end_date'))->startOfDay();
            }
        } catch (\Throwable) {
            return [null, null];
        }

        if (! $start || ! $end || $end->lessThan($start)) {
            return [null, null];
        }

        return [$start, $end];
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


