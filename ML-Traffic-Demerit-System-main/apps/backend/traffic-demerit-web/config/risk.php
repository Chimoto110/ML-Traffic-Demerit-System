<?php

return [
    'model_version' => env('RISK_MODEL_VERSION', 'rf_v3'),

    // Graduated sanction score thresholds (applied to FINAL score).
    'warning_risk_threshold' => (float) env('RISK_WARNING_THRESHOLD', 0.40),
    'training_risk_threshold' => (float) env('RISK_TRAINING_THRESHOLD', 0.55),

    // Crossing this threshold triggers automatic profile suspension.
    'sanction_risk_threshold' => (float) env('RISK_SANCTION_THRESHOLD', 0.7),

    // Tier labels mapped from score bands.
    'tiers' => [
        'good_standing' => ['min' => 0.0, 'max' => 0.40, 'label' => 'Good standing', 'action' => 'none'],
        'warning' => ['min' => 0.40, 'max' => 0.55, 'label' => 'Warning', 'action' => 'notify'],
        'restricted' => ['min' => 0.55, 'max' => 0.70, 'label' => 'Restricted', 'action' => 'mandatory_training'],
        'suspended' => ['min' => 0.70, 'max' => 1.0, 'label' => 'Suspended', 'action' => 'profile_suspension'],
    ],

    // Points floor anchors (linear interpolation between anchors).
    'points_floor' => [
        'warning' => ['points' => 10, 'score' => 0.40],
        'restriction' => ['points' => 20, 'score' => 0.55],
        'suspension' => ['points' => 30, 'score' => 0.70],
    ],

    // Maximum floor score (cap).
    'points_floor_max' => 0.99,

    'classification' => [
        'low_max' => (float) env('RISK_LOW_MAX', 0.3999),
        'moderate_max' => (float) env('RISK_MODERATE_MAX', 0.6999),
    ],
];
