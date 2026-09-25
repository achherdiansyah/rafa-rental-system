<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Recommendation System Configuration (Weighted Rule-Based Engine)
    |--------------------------------------------------------------------------
    |
    | Configuration for rule-based equipment recommendation scoring engine.
    | Weights and thresholds are configurable values as decision support foundation.
    |
    */

    'default_max_results' => 5,

    'min_score_threshold' => 50.00,

    'criteria' => [
        'capacity_match' => [
            'code' => 'CAPACITY_MATCH',
            'name' => 'Kesesuaian Kapasitas Muat',
            'weight' => 0.40,
            'is_active' => true,
            'order' => 1,
        ],
        'terrain_suitability' => [
            'code' => 'TERRAIN_SUITABILITY',
            'name' => 'Kesesuaian Karakteristik Medan',
            'weight' => 0.30,
            'is_active' => true,
            'order' => 2,
        ],
        'project_suitability' => [
            'code' => 'PROJECT_SUITABILITY',
            'name' => 'Kesesuaian Jenis Pekerjaan Proyek',
            'weight' => 0.20,
            'is_active' => true,
            'order' => 3,
        ],
        'price_suitability' => [
            'code' => 'PRICE_SUITABILITY',
            'name' => 'Kesiapan Komersial & Tarif Sewa',
            'weight' => 0.10,
            'is_active' => true,
            'order' => 4,
        ],
    ],
];
