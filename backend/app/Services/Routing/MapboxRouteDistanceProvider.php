<?php

namespace App\Services\Routing;

use Illuminate\Support\Facades\Http;

/**
 * Mapbox Directions API — driving profile.
 * Docs: https://docs.mapbox.com/api/navigation/directions/
 *
 * The access token is read from backend config (ROUTING_API_KEY) and never
 * exposed to the frontend, logs, or Git.
 */
class MapboxRouteDistanceProvider implements RouteDistanceProvider
{
    private const BASE_URL = 'https://api.mapbox.com/directions/v5/mapbox/driving';

    public function __construct(
        private readonly ?string $accessToken,
        private readonly int $timeoutSeconds = 8
    ) {}

    public function distanceKm(
        float $originLat,
        float $originLng,
        float $destinationLat,
        float $destinationLng
    ): ?float {
        if (! $this->accessToken) {
            return null;
        }

        $trim = static fn (float $v): string => rtrim(rtrim(number_format($v, 6, '.', ''), '0'), '.');
        $coordinates = sprintf(
            '%s,%s;%s,%s',
            $trim($originLng),
            $trim($originLat),
            $trim($destinationLng),
            $trim($destinationLat)
        );

        try {
            $response = Http::timeout($this->timeoutSeconds)
                ->acceptJson()
                ->get(self::BASE_URL.'/'.$coordinates, [
                    'access_token' => $this->accessToken,
                    'overview' => 'false',
                    'alternatives' => 'false',
                    'steps' => 'false',
                ]);
        } catch (\Throwable) {
            // Timeout, DNS, connection reset, etc. → unavailable (no fake price).
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        $distanceMeters = $response->json('routes.0.distance');

        if (! is_numeric($distanceMeters) || (float) $distanceMeters <= 0) {
            // No route found / malformed response.
            return null;
        }

        return round(((float) $distanceMeters) / 1000, 2);
    }
}