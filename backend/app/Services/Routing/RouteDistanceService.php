<?php

namespace App\Services\Routing;

use Illuminate\Support\Facades\Cache;

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

        // Cache key uses validated coordinates (~1 m precision) + provider class.
        $cacheKey = sprintf(
            'route_distance:%s:%s,%s:%s,%s',
            md5(get_class($this->provider)),
            round((float) $originLat, 5),
            round((float) $originLng, 5),
            round((float) $destinationLat, 5),
            round((float) $destinationLng, 5)
        );

        $ttl = (int) config('routing.cache_ttl', 86400);
        if ($ttl > 0) {
            $cached = Cache::get($cacheKey);
            if ($cached !== null) {
                return (float) $cached;
            }
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

        $distance = round($distance, 2);

        // Only successful distances are cached (failures may recover).
        if ($ttl > 0) {
            Cache::put($cacheKey, $distance, $ttl);
        }

        return $distance;
    }

    private function validCoordinates(?float $lat, ?float $lng): bool
    {
        return $lat !== null && $lng !== null
            && $lat >= -90 && $lat <= 90
            && $lng >= -180 && $lng <= 180;
    }
}