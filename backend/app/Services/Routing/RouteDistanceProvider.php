<?php

namespace App\Services\Routing;

/**
 * Contract for road-route distance providers (e.g. Google Routes, Mapbox,
 * OpenRouteService). Implementations must return the DRIVING route distance in
 * kilometers, or null when unavailable. Never approximate with a straight line.
 */
interface RouteDistanceProvider
{
    /**
     * @return float|null Distance in kilometers (one-way road route), or null on failure.
     */
    public function distanceKm(
        float $originLat,
        float $originLng,
        float $destinationLat,
        float $destinationLng
    ): ?float;
}