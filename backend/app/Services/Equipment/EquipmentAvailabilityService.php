<?php

namespace App\Services\Equipment;

use App\Enums\BookingStatus;
use App\Enums\EquipmentStatus;
use App\Models\EquipmentModel;
use App\Models\EquipmentUnit;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class EquipmentAvailabilityService
{
    /**
     * Get a map of [equipment_model_id => available_units_count] for a given set of models and date period.
     * Fully bulk-queried to eliminate N+1 queries.
     *
     * @param  Collection<int, int>|array<int>|null  $modelIds
     * @return Collection<int, int> Map of model_id to available unit count
     */
    public function getAvailabilityMap(
        Collection|array|null $modelIds = null,
        ?CarbonInterface $startDate = null,
        ?CarbonInterface $endDate = null
    ): Collection {
        if ($modelIds instanceof Collection) {
            $modelIds = $modelIds->all();
        }

        $availabilityMap = collect();

        if ($startDate && $endDate) {
            // 1. Total operational units per model (excluding DECOMMISSIONED & MAINTENANCE)
            $operationalQuery = EquipmentUnit::query()
                ->whereNull('deleted_at')
                ->whereNotIn('status', [
                    EquipmentStatus::DECOMMISSIONED->value,
                    EquipmentStatus::MAINTENANCE->value,
                ]);

            if (! empty($modelIds)) {
                $operationalQuery->whereIn('equipment_model_id', $modelIds);
            }

            /** @var Collection<int, int> $operationalCounts */
            $operationalCounts = $operationalQuery
                ->select('equipment_model_id', DB::raw('COUNT(*) as total_count'))
                ->groupBy('equipment_model_id')
                ->pluck('total_count', 'equipment_model_id');

            // 2. Count active assigned units during the [startDate, endDate] overlapping period
            $startStr = $startDate->toDateString();
            $endStr = $endDate->toDateString();

            $nonActiveStatuses = [
                BookingStatus::CANCELLED->value,
                BookingStatus::REJECTED->value,
                BookingStatus::EXPIRED->value,
            ];

            $bookedQuery = DB::table('booking_unit_assignments as bua')
                ->join('booking_details as bd', 'bua.booking_detail_id', '=', 'bd.id')
                ->join('bookings as b', 'bd.booking_id', '=', 'b.id')
                ->join('equipment_units as eu', 'bua.equipment_unit_id', '=', 'eu.id')
                ->where('bua.is_current', true)
                ->whereNotIn('b.status', $nonActiveStatuses)
                ->whereNull('b.deleted_at')
                ->whereNull('eu.deleted_at')
                ->where('bd.start_date', '<=', $endStr)
                ->where('bd.end_date', '>=', $startStr);

            if (! empty($modelIds)) {
                $bookedQuery->whereIn('bd.equipment_model_id', $modelIds);
            }

            /** @var Collection<int, int> $bookedCounts */
            $bookedCounts = $bookedQuery
                ->select('bd.equipment_model_id', DB::raw('COUNT(DISTINCT bua.equipment_unit_id) as booked_count'))
                ->groupBy('bd.equipment_model_id')
                ->pluck('booked_count', 'bd.equipment_model_id');

            $allIds = array_unique(array_merge(
                $modelIds ?? [],
                $operationalCounts->keys()->all()
            ));

            foreach ($allIds as $mId) {
                $total = (int) ($operationalCounts->get($mId, 0));
                $booked = (int) ($bookedCounts->get($mId, 0));
                $available = max(0, $total - $booked);

                $availabilityMap->put((int) $mId, $available);
            }
        } else {
            // No period specified: directly count units currently in AVAILABLE status
            $availableQuery = EquipmentUnit::query()
                ->whereNull('deleted_at')
                ->where('status', EquipmentStatus::AVAILABLE->value);

            if (! empty($modelIds)) {
                $availableQuery->whereIn('equipment_model_id', $modelIds);
            }

            /** @var Collection<int, int> $availableCounts */
            $availableCounts = $availableQuery
                ->select('equipment_model_id', DB::raw('COUNT(*) as avail_count'))
                ->groupBy('equipment_model_id')
                ->pluck('avail_count', 'equipment_model_id');

            $allIds = array_unique(array_merge(
                $modelIds ?? [],
                $availableCounts->keys()->all()
            ));

            foreach ($allIds as $mId) {
                $count = (int) ($availableCounts->get($mId, 0));
                $availabilityMap->put((int) $mId, $count);
            }
        }

        return $availabilityMap;
    }

    /**
     * Check if a specific model has at least one unit available during a given period.
     */
    public function isModelAvailable(
        EquipmentModel $model,
        ?CarbonInterface $startDate = null,
        ?CarbonInterface $endDate = null
    ): bool {
        $map = $this->getAvailabilityMap([$model->id], $startDate, $endDate);

        return ((int) $map->get($model->id, 0)) > 0;
    }

    /**
     * Get available unit count for a single model.
     */
    public function getAvailableUnitsCount(
        EquipmentModel $model,
        ?CarbonInterface $startDate = null,
        ?CarbonInterface $endDate = null
    ): int {
        $map = $this->getAvailabilityMap([$model->id], $startDate, $endDate);

        return (int) $map->get($model->id, 0);
    }
}
