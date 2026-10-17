<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Road Routing Provider (MOB/DEMOB distance)
    |--------------------------------------------------------------------------
    | Distance MUST be a real road-route distance. No straight-line fallback is
    | used. Leave ROUTING_PROVIDER empty until a provider is chosen, contracted
    | and its API key configured. Supported values are documented in
    | docs/testing/mob-demob-distance-plan.md.
    |
    | The provider class must implement App\Services\Routing\RouteDistanceProvider.
    */
    // 'mapbox' activates MapboxRouteDistanceProvider when ROUTING_API_KEY is set.
    'provider' => env('ROUTING_PROVIDER'),

    'api_key' => env('ROUTING_API_KEY'),

    'base_url' => env('ROUTING_BASE_URL'),

    'provider_class' => env('ROUTING_PROVIDER_CLASS'),

    // Per-request timeout (seconds) for the routing HTTP call.
    'timeout' => (int) env('ROUTING_TIMEOUT', 8),

    // Cache successful route distances (seconds). 0 disables caching.
    'cache_ttl' => (int) env('ROUTING_CACHE_TTL', 86400),
];
