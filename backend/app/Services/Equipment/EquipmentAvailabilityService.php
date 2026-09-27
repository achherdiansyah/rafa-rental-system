<?php

namespace App\Services\Equipment;

use App\Enums\EquipmentStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\EquipmentModel;
use App\Models\EquipmentUnit;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * EquipmentAvailabilityEngine — single source of truth for rental availability.
 *
 * MySQL database is the correctness source of truth (zero Redis dependency).
 * Booking state, physical unit status, and operational buffers are all read
 * directly from the database in bulk queries (no N+1).
 */
class EquipmentAvailabilityService
{
    public function __construct()
    {
        $config = config('availability.buffers', []);
        $this->mobDays = (int) ($config['mobilization_days'] ?? 0);
        $this->extDays = (int) ($config['extension_days'] ?? 3);
        $this->inspDays = (int) ($config['inspection_days'] ?? 2);
        $this->committedStatuses = config('availability.committed_statuses', []);
        $this->unavailableStatuses = config('availability.unavailable_unit_statuses', []);
    }

    protected int $mobDays;

    protected int $extDays;

    protected int $inspDays;

    /** @var string[] */
    protected array $committedStatuses;

    /** @var string[] */
    protected array $unavailableStatuses;

    // ------------------------------------------------------------------
    // Operational buffer helpers
    // ------------------------------------------------------------------

    public function bufferAfterEnd(CarbonInterface $endDate): CarbonInterface
    {
        return $endDate->copy()->addDays($this->extDays + $this->inspDays);
    }

    public function bufferBeforeStart(CarbonInterface $startDate): CarbonInterface
    {
        return $startDate->copy()->subDays($this->mobDays);
    }

    /**
     * Committed booking slots whose buffered window overlaps the requested
     * buffered window. Buffer is applied symmetrically to both parties:
     * - Committed slot occupies [start - mob, end + ext + insp]
     * - Requested window occupies [reqStart - mob, reqEnd + ext + insp]
     *
     * The SQL predicate therefore must compare the committed slot's BUFFERED end
     * (end + ext + insp) against the requested start, and its buffered start
     * against the requested end.
     *
     * @return array{string, string} [committed_start_max, committed_end_min_raw]
     */
    protected function overlapRange(CarbonInterface $start, CarbonInterface $end): array
    {
        $postBuffer = $this->extDays + $this->inspDays;
        $span = $this->mobDays + $postBuffer;

        // Condition 1: committed.start - mob <= request.end + ext + insp
        $committedStartMax = $end->copy()->addDays($span)->toDateString();

        // Condition 2: committed.end + ext + insp >= request.start - mob
        $committedEndMinRaw = $start->copy()->subDays($this->mobDays)->toDateString();

        return [$committedStartMax, $committedEndMinRaw];
    }

    // ------------------------------------------------------------------
    // Model-level (aggregate capacity) availability
    // ------------------------------------------------------------------

    /**
     * Map of [equipment_model_id => available unit count] over a buffered period.
     * Bulk-queried; no per-model queries (no N+1).
     *
     * @param  Collection<int, int>|array<int>|null  $modelIds
     * @return Collection<int, int>
     */
    public function getAvailabilityMap(
        Collection|array|null $modelIds = null,
        ?CarbonInterface $startDate = null,
        ?CarbonInterface $endDate = null
    ): Collection {
        if ($modelIds instanceof Collection) {
            $modelIds = $modelIds->all();
        }

        if (! $startDate || ! $endDate) {
            // No period: count units currently in AVAILABLE status
            $query = EquipmentUnit::query()
                ->whereNull('deleted_at')
                ->where('status', EquipmentStatus::AVAILABLE->value);

            if (! empty($modelIds)) {
                $query->whereIn('equipment_model_id', $modelIds);
            }

            $counts = $query
                ->select('equipment_model_id', DB::raw('COUNT(*) as avail_count'))
                ->groupBy('equipment_model_id')
                ->pluck('avail_count', 'equipment_model_id');

            return $this->fillMap($modelIds ?? [], $counts);
        }

        // Period-aware: total operational - committed (buffered overlap)
        $operational = EquipmentUnit::query()
            ->whereNull('deleted_at')
            ->whereNotIn('status', $this->unavailableStatuses);

        if (! empty($modelIds)) {
            $operational->whereIn('equipment_model_id', $modelIds);
        }

        $operationalCounts = $operational
            ->select('equipment_model_id', DB::raw('COUNT(*) as total_count'))
            ->groupBy('equipment_model_id')
            ->pluck('total_count', 'equipment_model_id');

        [$cStartMax, $cEndMin] = $this->overlapRange($startDate, $endDate);
        $postBuffer = $this->extDays + $this->inspDays;

        $committed = DB::table('booking_unit_assignments as bua')
            ->join('booking_details as bd', 'bua.booking_detail_id', '=', 'bd.id')
            ->join('bookings as b', 'bd.booking_id', '=', 'b.id')
            ->join('equipment_units as eu', 'bua.equipment_unit_id', '=', 'eu.id')
            ->where('bua.is_current', true)
            ->whereIn('b.status', $this->committedStatuses)
            ->whereNull('b.deleted_at')
            ->whereNull('eu.deleted_at')
            ->where('bd.start_date', '<=', $cStartMax)
            ->whereRaw('DATE_ADD(bd.end_date, INTERVAL ? DAY) >= ?', [$postBuffer, $cEndMin]);

        if (! empty($modelIds)) {
            $committed->whereIn('bd.equipment_model_id', $modelIds);
        }

        $committedCounts = $committed
            ->select('bd.equipment_model_id', DB::raw('COUNT(DISTINCT bua.equipment_unit_id) as booked_count'))
            ->groupBy('bd.equipment_model_id')
            ->pluck('booked_count', 'bd.equipment_model_id');

        $map = collect();
        $allIds = array_unique(array_merge($modelIds ?? [], $operationalCounts->keys()->all()));

        foreach ($allIds as $mId) {
            $total = (int) ($operationalCounts->get($mId, 0));
            $booked = (int) ($committedCounts->get($mId, 0));
            $map->put((int) $mId, max(0, $total - $booked));
        }

        return $map;
    }

    /**
     * @param  array<int>  $modelIds
     * @param  Collection<int, int>  $counts
     * @return Collection<int, int>
     */
    protected function fillMap(array $modelIds, Collection $counts): Collection
    {
        $map = collect();
        $allIds = array_unique(array_merge($modelIds, $counts->keys()->map(fn ($k) => (int) $k)->all()));

        foreach ($allIds as $mId) {
            $map->put((int) $mId, (int) $counts->get($mId, 0));
        }

        return $map;
    }

    public function isModelAvailable(
        EquipmentModel $model,
        ?CarbonInterface $startDate = null,
        ?CarbonInterface $endDate = null
    ): bool {
        return $this->getAvailableUnitsCount($model, $startDate, $endDate) > 0;
    }

    public function getAvailableUnitsCount(
        EquipmentModel $model,
        ?CarbonInterface $startDate = null,
        ?CarbonInterface $endDate = null
    ): int {
        return (int) $this->getAvailabilityMap([$model->id], $startDate, $endDate)->get($model->id, 0);
    }

    // ------------------------------------------------------------------
    // Physical-unit level availability
    // ------------------------------------------------------------------

    /**
     * Candidate AVAILABLE physical units for a model during a buffered period.
     * Excludes units committed to other active/approved bookings and units in
     * non-operational status (MAINTENANCE / RETURN_INSPECTION / DECOMMISSIONED).
     *
     * @return Collection<int, EquipmentUnit>
     */
    public function getCandidateUnits(
        int $modelId,
        CarbonInterface $startDate,
        CarbonInterface $endDate,
        ?int $limit = null
    ): Collection {
        [$cStartMax, $cEndMin] = $this->overlapRange($startDate, $endDate);
        $postBuffer = $this->extDays + $this->inspDays;

        $query = EquipmentUnit::query()
            ->where('equipment_model_id', $modelId)
            ->whereNull('deleted_at')
            ->where('status', EquipmentStatus::AVAILABLE->value)
            ->whereNotIn('status', $this->unavailableStatuses)
            ->whereNotExists(function ($sub) use ($cStartMax, $cEndMin, $postBuffer) {
                $sub->select(DB::raw(1))
                    ->from('booking_unit_assignments')
                    ->join('booking_details', 'booking_unit_assignments.booking_detail_id', '=', 'booking_details.id')
                    ->join('bookings', 'booking_details.booking_id', '=', 'bookings.id')
                    ->whereColumn('booking_unit_assignments.equipment_unit_id', 'equipment_units.id')
                    ->where('booking_unit_assignments.is_current', true)
                    ->whereIn('bookings.status', $this->committedStatuses)
                    ->whereNull('bookings.deleted_at')
                    ->where('booking_details.start_date', '<=', $cStartMax)
                    ->whereRaw('DATE_ADD(booking_details.end_date, INTERVAL ? DAY) >= ?', [$postBuffer, $cEndMin]);
            })
            ->orderBy('id');

        if ($limit !== null) {
            $query->limit(max(1, $limit));
        }

        return $query->get();
    }

    public function isUnitAvailable(
        EquipmentUnit $unit,
        CarbonInterface $startDate,
        CarbonInterface $endDate
    ): bool {
        return $this->getCandidateUnits($unit->equipment_model_id, $startDate, $endDate)
            ->contains(fn (EquipmentUnit $candidate) => $candidate->id === $unit->id);
    }

    /**
     * Atomically lock candidate physical units for assignment inside the current
     * transaction using pessimistic row locks (SELECT ... FOR UPDATE). Prevents
     * two concurrent transactions from double-booking the same physical unit.
     *
     * Caller is responsible for wrapping this call in DB::transaction().
     *
     * @return Collection<int, EquipmentUnit> Locked unit rows (status still AVAILABLE in this txn)
     *
     * @throws BusinessRuleException If fewer candidate units than requested quantity.
     */
    public function lockAvailableUnits(
        int $modelId,
        CarbonInterface $startDate,
        CarbonInterface $endDate,
        int $quantity
    ): Collection {
        $candidates = $this->getCandidateUnits($modelId, $startDate, $endDate, $quantity);

        if ($candidates->count() < $quantity) {
            throw new BusinessRuleException(
                "Ketersediaan unit fisik untuk model ID #{$modelId} tidak mencukupi: dibutuhkan {$quantity} unit, hanya {$candidates->count()} unit yang tersedia."
            );
        }

        $ids = $candidates->pluck('id')->all();

        // Pessimistic row lock — blocks concurrent allocation of the same units
        // until this transaction commits or rolls back.
        $locked = EquipmentUnit::query()
            ->whereIn('id', $ids)
            ->where('status', EquipmentStatus::AVAILABLE->value)
            ->lockForUpdate()
            ->orderBy('id')
            ->get();

        $lockedIds = $locked->pluck('id')->all();
        $unavailable = array_diff($ids, $lockedIds);

        if (! empty($unavailable) || $locked->count() < $quantity) {
            throw new BusinessRuleException(
                "Unit fisik untuk model ID #{$modelId} sudah diambil transaksi lain. Silakan coba kembali."
            );
        }

        return $locked;
    }
}
