<?php

return [

    /*
    |--------------------------------------------------------------------------
    | M-Pesa / Daraja Configuration
    |--------------------------------------------------------------------------
    |
    | All secrets must come from .env. Never commit real keys.
    |
    */

    'env' => env('MPESA_ENV', 'sandbox'),

    // OAuth credentials (get from Daraja developer portal)
    'consumer_key' => env('MPESA_CONSUMER_KEY', ''),
    'consumer_secret' => env('MPESA_CONSUMER_SECRET', ''),

    // Shortcode and passkey (sandbox values shown on Daraja simulator page)
    'shortcode' => env('MPESA_SHORTCODE', '174379'),
    'passkey' => env('MPESA_PASSKEY', ''),

    // Transaction type: CustomerPayBillOnline (Paybill) or CustomerBuyGoodsOnline (Till)
    'transaction_type' => env('MPESA_TRANSACTION_TYPE', 'CustomerPayBillOnline'),

    // Party B: the till/store number if different from shortcode
    'party_b' => env('MPESA_PARTY_B', ''),

    // Callback: public HTTPS URL where Daraja posts results
    // Full callback URL = {base_url}/api/payments/mpesa/callback/{token}
    'callback_base_url' => env('MPESA_CALLBACK_BASE_URL', ''),
    'callback_token' => env('MPESA_CALLBACK_TOKEN', ''),

    // Simulation mode: deterministic outcomes, no network calls
    'simulate' => (bool) env('MPESA_SIMULATE', true),

    // Timeout in seconds for gateway calls
    'timeout_seconds' => (int) env('MPESA_TIMEOUT_SECONDS', 120),

    // Base API URL chosen from MPESA_ENV
    'base_url' => function () {
        return config('mpesa.env') === 'production'
            ? 'https://api.safaricom.co.ke'
            : 'https://sandbox.safaricom.co.ke';
    },

    // Safaricom source IP allow-list (fetch from Daraja docs).
    // Set MPESA_ALLOWED_IPS as a comma-separated list in .env.
    // Leave empty to skip IP checking (sandbox/local only).
    'allowed_ips' => env('MPESA_ALLOWED_IPS', ''),
];
