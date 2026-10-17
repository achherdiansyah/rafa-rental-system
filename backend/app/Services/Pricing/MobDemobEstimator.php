<?php

namespace App\Services\Pricing;

use App\Models\EquipmentModel;
use App\Models\EquipmentPrice;
use App\Models\ProjectLocation;
use App\Services\Routing\RouteDistanceService;

/**
 * Single source for MOB/DEMOB distance estimates used by catalog/detail/cart.
 * Uses the model's default origin pool, the user-selected project location and
 * the Admin-managed per-km tariff. Never invents a distance or a zero price.
 */
class MobDemobEstimator
{
    public function __construct(
        private readonly RouteDistanceService $routeDistance
    ) {}

    /**
     * @return array{
     *   distance_available: bool,
     *   route_distance_km: ?float,
     *   distance_rate_per_km: ?float,
     *   mob_cost: ?float,
     *   demob_cost: ?float,
     *   total_mob_demob: ?float,
     *   origin_pool: ?array{id:int,name:string,address:string,latitude:?float,longitude:?float},
     *   destination: ?array{id:int,name:string,address:string,latitude:?float,longitude:?float},
     *   message: string
     * }
     */
    public function estimate(EquipmentModel $model, ?ProjectLocation $destination, bool $isAllIn = true): array
    {
        $origin = $model->pool;

        $price = EquipmentPrice::query()
            ->where('equipment_model_id', $model->id)
            ->where('is_all_in', $isAllIn)
            ->where('effective_date', '<=', now()->toDateString())
            ->latest('effective_date')
            ->first();

        $rate = $price ? (float) $price->distance_rate_per_km : 0.0;

        $distance = null;
        if ($origin && $destination) {
            $distance = $this->routeDistance->distanceKm(
                $origin->latitude !== null ? (float) $origin->latitude : null,
                $origin->longitude !== null ? (float) $origin->longitude : null,
                $destination->latitude !== null ? (float) $destination->latitude : null,
                $destination->longitude !== null ? (float) $destination->longitude : null
            );
        }

        $available = $distance !== null && $rate > 0;
        $mob = $available ? round($distance * $rate, 2) : null;
        $demob = $available ? round($distance * $rate, 2) : null;

        return [
            'distance_available' => $available,
            'route_distance_km' => $distance,
            'distance_rate_per_km' => $available ? $rate : null,
            'mob_cost' => $mob,
            'demob_cost' => $demob,
            'total_mob_demob' => $available ? round($mob + $demob, 2) : null,
            'origin_pool' => $origin ? [
                'id' => $origin->id,
                'name' => $origin->name,
                'address' => $origin->address,
                'latitude' => $origin->latitude !== null ? (float) $origin->latitude : null,
                'longitude' => $origin->longitude !== null ? (float) $origin->longitude : null,
            ] : null,
            'destination' => $destination ? [
                'id' => $destination->id,
                'name' => $destination->project_name,
                'address' => $destination->address,
                'latitude' => $destination->latitude !== null ? (float) $destination->latitude : null,
                'longitude' => $destination->longitude !== null ? (float) $destination->longitude : null,
            ] : null,
            'message' => $available
                ? 'Estimasi MOB/DEMOB berdasarkan jarak rute dan tarif per kilometer.'
                : 'Estimasi jarak rute belum tersedia (layanan routing belum dikonfigurasi atau koordinat tidak lengkap).',
        ];
    }
}