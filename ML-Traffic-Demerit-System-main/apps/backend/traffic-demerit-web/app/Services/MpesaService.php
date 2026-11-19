<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class MpesaService
{
    protected string $env;
    protected string $consumerKey;
    protected string $consumerSecret;
    protected string $shortcode;
    protected string $passkey;
    protected string $callbackUrl;
    protected bool $simulate;

    public function __construct()
    {
        $this->env = config('mpesa.env', 'sandbox');
        $this->consumerKey = config('mpesa.consumer_key', '');
        $this->consumerSecret = config('mpesa.consumer_secret', '');
        $this->shortcode = config('mpesa.shortcode', '174379');
        $this->passkey = config('mpesa.passkey', '');
        $this->callbackUrl = config('mpesa.callback_url', '');
        $this->simulate = (bool) config('mpesa.simulate', true);
    }

    /**
     * Get OAuth access token from Daraja, cached for ~55 minutes.
     */
    public function getAccessToken(): string
    {
        $cacheKey = 'mpesa_access_token_' . $this->env;

        $token = Cache::get($cacheKey);
        if ($token) {
            return $token;
        }

        if ($this->simulate) {
            // Return a fake token for simulation mode
            $token = 'simulate_token_' . uniqid();
            Cache::put($cacheKey, $token, now()->addMinutes(55));
            return $token;
        }

        $baseUrl = $this->env === 'production'
            ? 'https://api.safaricom.co.ke'
            : 'https://sandbox.safaricom.co.ke';

        try {
            $response = Http::withBasicAuth($this->consumerKey, $this->consumerSecret)
                ->timeout(10)
                ->get("{$baseUrl}/oauth/v1/generate?grant_type=client_credentials");

            if (! $response->successful()) {
                Log::error('M-Pesa token request failed: ' . $response->body());
                throw new \RuntimeException('Failed to obtain M-Pesa access token.');
            }

            $data = $response->json();
            $token = $data['access_token'] ?? '';
            Cache::put($cacheKey, $token, now()->addMinutes(55));

            return $token;
        } catch (\Throwable $e) {
            Log::error('M-Pesa token request exception: ' . $e->getMessage());
            throw new \RuntimeException('M-Pesa service unavailable: ' . $e->getMessage());
        }
    }

    /**
     * Normalize a Kenyan phone number to 254XXXXXXXX format.
     */
    public function normalizePhone(string $phone): string
    {
        $p = preg_replace('/\s+/', '', $phone);

        // Remove leading +
        if (str_starts_with($p, '+')) {
            $p = substr($p, 1);
        }

        // Convert local 07xx or 01xx to 254...
        if (preg_match('/^0(7|1)[0-9]{8}$/', $p)) {
            $p = '254' . substr($p, 1);
        }

        // Already 254...
        if (preg_match('/^254(7|1)[0-9]{8}$/', $p)) {
            return $p;
        }

        throw new \InvalidArgumentException(
            'Invalid phone number format. Expected 07xx/01xx/254xxxxxxxx, got: ' . $phone
        );
    }

    /**
     * Initiate STK Push.
     *
     * @return array ['success' => bool, 'checkout_request_id' => string|null, 'merchant_request_id' => string|null, 'response' => array]
     */
    public function stkPush(string $phone, int $amount, string $accountReference, string $description): array
    {
        if ($this->simulate) {
            return $this->simulateStkPush($phone, $amount, $accountReference, $description);
        }

        $phone = $this->normalizePhone($phone);
        $token = $this->getAccessToken();
        $timestamp = now()->format('YmdHis');
        $password = base64_encode($this->shortcode . $this->passkey . $timestamp);

        $baseUrl = config('mpesa.base_url');

        try {
            $response = Http::withToken($token)
                ->timeout(15)
                ->post("{$baseUrl}/mpesa/stkpush/v1/processrequest", [
                    'BusinessShortCode' => $this->shortcode,
                    'Password' => $password,
                    'Timestamp' => $timestamp,
                    'TransactionType' => 'CustomerPayBillOnline',
                    'Amount' => $amount,
                    'PartyA' => $phone,
                    'PartyB' => $this->shortcode,
                    'PhoneNumber' => $phone,
                    'CallBackURL' => $this->callbackUrl,
                    'AccountReference' => $accountReference,
                    'TransactionDesc' => $description,
                ]);

            if (! $response->successful()) {
                Log::error('M-Pesa STK push failed: ' . $response->body());
                return [
                    'success' => false,
                    'checkout_request_id' => null,
                    'merchant_request_id' => null,
                    'response' => ['error' => $response->body()],
                ];
            }

            $data = $response->json();
            return [
                'success' => true,
                'checkout_request_id' => $data['CheckoutRequestID'] ?? null,
                'merchant_request_id' => $data['MerchantRequestID'] ?? null,
                'response' => $data,
            ];
        } catch (\Throwable $e) {
            Log::error('M-Pesa STK push exception: ' . $e->getMessage());
            return [
                'success' => false,
                'checkout_request_id' => null,
                'merchant_request_id' => null,
                'response' => ['error' => $e->getMessage()],
            ];
        }
    }

    /**
     * Query STK push status.
     */
    public function queryStatus(string $checkoutRequestId): array
    {
        if ($this->simulate) {
            // In simulate mode, assume completed after a short delay
            return [
                'success' => true,
                'result_code' => '0',
                'result_desc' => 'The service request is processed successfully.',
                'response' => [],
            ];
        }

        $token = $this->getAccessToken();
        $timestamp = now()->format('YmdHis');
        $password = base64_encode($this->shortcode . $this->passkey . $timestamp);

        $baseUrl = config('mpesa.base_url');

        try {
            $response = Http::withToken($token)
                ->timeout(10)
                ->post("{$baseUrl}/mpesa/stkpushquery/v1/query", [
                    'BusinessShortCode' => $this->shortcode,
                    'Password' => $password,
                    'Timestamp' => $timestamp,
                    'CheckoutRequestID' => $checkoutRequestId,
                ]);

            if (! $response->successful()) {
                return [
                    'success' => false,
                    'result_code' => null,
                    'result_desc' => $response->body(),
                    'response' => [],
                ];
            }

            $data = $response->json();
            return [
                'success' => true,
                'result_code' => $data['ResultCode'] ?? null,
                'result_desc' => $data['ResultDesc'] ?? null,
                'response' => $data,
            ];
        } catch (\Throwable $e) {
            Log::warning('M-Pesa query exception: ' . $e->getMessage());
            return [
                'success' => false,
                'result_code' => null,
                'result_desc' => $e->getMessage(),
                'response' => [],
            ];
        }
    }

    /**
     * Simulate STK Push for demo mode.
     * Auto-completes after a short simulated delay.
     */
    protected function simulateStkPush(string $phone, int $amount, string $accountReference, string $description): array
    {
        $merchantRequestId = 'MOCK-' . uniqid();
        $checkoutRequestId = 'MOCK-CHECKOUT-' . uniqid();

        Log::info('M-Pesa SIMULATE STK push', [
            'phone' => $phone,
            'amount' => $amount,
            'account_reference' => $accountReference,
            'merchant_request_id' => $merchantRequestId,
            'checkout_request_id' => $checkoutRequestId,
        ]);

        return [
            'success' => true,
            'checkout_request_id' => $checkoutRequestId,
            'merchant_request_id' => $merchantRequestId,
            'response' => [
                'MerchantRequestID' => $merchantRequestId,
                'CheckoutRequestID' => $checkoutRequestId,
                'ResponseCode' => '0',
                'ResponseDesc' => 'Success. Request accepted for processing.',
                'CustomerMessage' => 'Success. Request accepted for processing.',
            ],
        ];
    }

    /**
     * Simulate a callback for offline testing.
     */
    public function simulateCallback(string $checkoutRequestId, string $resultCode = '0', string $resultDesc = 'The service request is processed successfully.'): array
    {
        $amount = 1000;
        $receipt = 'MOCK-' . uniqid();
        $transactionDate = now()->format('YmdHis');
        $phone = '254708374149';

        $callbackItems = [
            ['Name' => 'Amount', 'Value' => $amount],
            ['Name' => 'MpesaReceiptNumber', 'Value' => $receipt],
            ['Name' => 'TransactionDate', 'Value' => $transactionDate],
            ['Name' => 'PhoneNumber', 'Value' => $phone],
        ];

        return [
            'Body' => [
                'stkCallback' => [
                    'MerchantRequestID' => 'MOCK-MERCHANT-' . uniqid(),
                    'CheckoutRequestID' => $checkoutRequestId,
                    'ResultCode' => $resultCode,
                    'ResultDesc' => $resultDesc,
                    'CallbackMetadata' => [
                        'Item' => $callbackItems,
                    ],
                ],
            ],
        ];
    }
}
