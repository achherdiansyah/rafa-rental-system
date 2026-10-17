<?php

namespace App\Services\Routing;

class RouteDistanceService
{
    public function __construct(
        private readonly RouteDistanceProvider $provider
    ) {}

    public function isConfigured(): bool
    {
        return ! $this->provider instanceof NullRouteDistanceProvider;
    }

    /**
     * Resolve the one-way driving distance between origin and destination.
     * Returns null when coordinates are invalid or the provider fails/returns
     * an unusable value — callers must NOT substitute an invented distance.
     */
    public function distanceKm(
        ?float $originLat,
        ?float $originLng,
        ?float $destinationLat,
        ?float $destinationLng
    ): ?float {
        if (! $this->isConfigured()) {
            return null;
        }

        if (! $this->validCoordinates($originLat, $originLng) || ! $this->validCoordinates($destinationLat, $destinationLng)) {
            return null;
        }

        try {
            $distance = $this->provider->distanceKm(
                (float) $originLat,
                (float) $originLng,
                (float) $destinationLat,
                (float) $destinationLng
            );
        } catch (\Throwable) {
            return null;
        }

        if ($distance === null || ! is_finite($distance) || $distance <= 0) {
            return null;
        }

        return round($distance, 2);
    }

    private function validCoordinates(?float $lat, ?float $lng): bool
    {
        return $lat !== null && $lng !== null
            && $lat >= -90 && $lat <= 90
            && $lng >= -180 && $lng <= 180;
    }
}