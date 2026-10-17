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
    'provider' => env('ROUTING_PROVIDER'),

    'api_key' => env('ROUTING_API_KEY'),

    'base_url' => env('ROUTING_BASE_URL'),

    'provider_class' => env('ROUTING_PROVIDER_CLASS'),
];
