<?php

namespace App\DTOs\Pricing;

use Carbon\Carbon;

readonly class LineItemPricingInput
{
    public function __construct(
        public int $equipmentModelId,
        public int $quantity,
        public Carbon $startDate,
        public Carbon $endDate,
        public bool $isAllIn,
        public float $mobRatePerUnit = 0.00,
        public float $demobRatePerUnit = 0.00,
        public ?float $routeDistanceKm = null,
        public ?float $distanceRatePerKm = null,
        public ?int $originPoolId = null,
        public ?float $originLatitude = null,
        public ?float $originLongitude = null,
        public ?int $destinationProjectLocationId = null,
        public ?float $destinationLatitude = null,
        public ?float $destinationLongitude = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            equipmentModelId: (int) $data['equipment_model_id'],
            quantity: max(1, (int) ($data['quantity'] ?? 1)),
            startDate: Carbon::parse($data['start_date'])->startOfDay(),
            endDate: Carbon::parse($data['end_date'])->startOfDay(),
            isAllIn: (bool) ($data['is_all_in'] ?? false),
            mobRatePerUnit: (float) ($data['mob_rate_per_unit'] ?? 0.00),
            demobRatePerUnit: (float) ($data['demob_rate_per_unit'] ?? 0.00),
            routeDistanceKm: isset($data['route_distance_km']) ? (float) $data['route_distance_km'] : null,
            distanceRatePerKm: isset($data['distance_rate_per_km']) ? (float) $data['distance_rate_per_km'] : null,
            originPoolId: isset($data['origin_pool_id']) ? (int) $data['origin_pool_id'] : null,
            originLatitude: isset($data['origin_latitude']) ? (float) $data['origin_latitude'] : null,
            originLongitude: isset($data['origin_longitude']) ? (float) $data['origin_longitude'] : null,
            destinationProjectLocationId: isset($data['destination_project_location_id']) ? (int) $data['destination_project_location_id'] : null,
            destinationLatitude: isset($data['destination_latitude']) ? (float) $data['destination_latitude'] : null,
            destinationLongitude: isset($data['destination_longitude']) ? (float) $data['destination_longitude'] : null,
        );
    }
}