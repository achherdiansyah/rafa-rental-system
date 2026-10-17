<?php

namespace App\Services\Routing;

/**
 * Default provider used until a road-routing service is configured & approved.
 * It intentionally returns null: the system must NOT invent distances (nor use
 * straight-line approximations). Callers surface "estimasi belum tersedia".
 */
class NullRouteDistanceProvider implements RouteDistanceProvider
{
    public function distanceKm(float $originLat, float $originLng, float $destinationLat, float $destinationLng): ?float
    {
        return null;
    }
}