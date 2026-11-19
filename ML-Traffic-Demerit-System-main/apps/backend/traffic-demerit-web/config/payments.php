<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Payment Gateway Configuration
    |--------------------------------------------------------------------------
    */

    'simulate' => env('PAYMENT_SIMULATE', true),

    'card_simulate' => env('CARD_SIMULATE', true),
    'airtel_simulate' => env('AIRTEL_SIMULATE', true),
    'equity_simulate' => env('EQUITY_SIMULATE', true),

    'gateways' => [
        'mpesa' => App\Services\Mpesa\PythonMpesaGateway::class,
        'airtel_money' => App\Services\AirtelMoneyGateway::class,
        'card' => App\Services\CardGateway::class,
        'equity' => App\Services\EquityGateway::class,
    ],

    'default_method' => env('PAYMENT_DEFAULT_METHOD', 'mpesa'),

    /*
    |--------------------------------------------------------------------------
    | Card gateway settings
    |--------------------------------------------------------------------------
    */
    'card' => [
        'simulate' => env('CARD_SIMULATE', true),
        'test_success_card' => '4242424242424242',
        'test_decline_card' => '4000000000000002',
    ],

    /*
    |--------------------------------------------------------------------------
    | M-Pesa settings (see config/mpesa.php for Daraja-specific settings)
    |--------------------------------------------------------------------------
    */
    'mpesa' => [
        'simulate' => env('MPESA_SIMULATE', true),
    ],

];
