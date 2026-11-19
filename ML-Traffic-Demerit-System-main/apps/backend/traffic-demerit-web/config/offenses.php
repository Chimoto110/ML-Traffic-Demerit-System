<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Offense Catalog — single source of truth for offence points and fines.
    |--------------------------------------------------------------------------
    |
    * Used by: ViolationController (store/update), Api\ViolationController,
    * DriverRiskFeatureBuilder, EnhancedDataSeeder / DemoDataSeeder,
    * ml_service ETL. Never duplicate these maps elsewhere.
    */

    'points' => [
        'speeding' => 4,
        'illegal_parking' => 2,
        'dui' => 10,
        'reckless_driving' => 8,
        'red_light' => 5,
        'no_seatbelt' => 2,
        'phone_use' => 3,
        'driving_without_license' => 8,
        'overloading' => 4,
        'no_insurance' => 6,
        'wrong_way' => 5,
        'expired_license' => 2,
        'other' => 2,
    ],

    'fines_kes' => [
        'speeding' => 5000,
        'illegal_parking' => 500,
        'dui' => 15000,
        'reckless_driving' => 7000,
        'red_light' => 3000,
        'no_seatbelt' => 1000,
        'phone_use' => 2000,
        'driving_without_license' => 12000,
        'overloading' => 4000,
        'no_insurance' => 8000,
        'wrong_way' => 3000,
        'expired_license' => 1500,
        'other' => 1000,
    ],

    // Severity weights for the risk feature builder (higher = more severe).
    'severity_weights' => [
        'dui' => 5,
        'reckless_driving' => 4,
        'red_light' => 3,
        'speeding' => 2,
        'phone_use' => 2,
        'no_seatbelt' => 1,
        'illegal_parking' => 1,
        'driving_without_license' => 4,
        'overloading' => 3,
        'no_insurance' => 3,
        'wrong_way' => 3,
        'expired_license' => 2,
        'other' => 1,
    ],

    // Severe offence types (used for severe_offence_share feature).
    'severe_offence_types' => [
        'dui',
        'reckless_driving',
        'red_light',
        'driving_without_license',
    ],

    // Points floor anchors for the risk score floor computation.
    'points_floor' => [
        'warning' => ['points' => 10, 'score' => 0.40],
        'restriction' => ['points' => 20, 'score' => 0.55],
        'suspension' => ['points' => 30, 'score' => 0.70],
    ],

];
