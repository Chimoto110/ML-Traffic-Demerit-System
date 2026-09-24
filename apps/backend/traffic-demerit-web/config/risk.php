<?php

return [
    'model_version' => env('RISK_MODEL_VERSION', 'rf_v1'),

    // Crossing this threshold triggers automatic profile lock (suspension).
    'sanction_risk_threshold' => (float) env('RISK_SANCTION_THRESHOLD', 0.7),

    'classification' => [
        'low_max' => (float) env('RISK_LOW_MAX', 0.3999),
        'moderate_max' => (float) env('RISK_MODERATE_MAX', 0.6999),
    ],
];
