<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Recommendation System Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for rule-based equipment recommendation scoring engine.
    | Weights and thresholds are configurable values as decision support foundation.
    |
    */

    'default_max_results' => 5,

    'weights' => [
        'capacity_match' => 0.40,     // 40% weight for capacity suitability
        'terrain_suitability' => 0.30, // 30% weight for ground/terrain compatibility
        'project_suitability' => 0.20, // 20% weight for project type compatibility
        'price_suitability' => 0.10,   // 10% weight for budget range suitability
    ],

    'min_score_threshold' => 50.00,
];
