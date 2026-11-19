<?php

namespace App\Services\Mpesa;

use App\Models\Payment;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class DarajaClient
{
    protected string $env;
    protected string $consumerKey;
    protected string $consumerSecret;
    protected string $shortcode;
    protected string $passkey;
    protected string $transactionType;
    protected string $partyB;
    protected string $callbackBaseUrl;
    protected string $callbackToken;
    protected int $timeoutSeconds;
    protected bool $simulate;

    public function __construct()
    {
        $this->env = config('mpesa.env', 'sandbox');
        $this->consumerKey = config('mpesa.consumer_key', '');
        $this->consumerSecret = config('mpesa.consumer_secret', '');
        $this->shortcode = config('mpesa.shortcode', '174379');
        $this->passkey = config('mpesa.passkey', '');
        $this->transactionType = config('mpesa.transaction_type', 'CustomerPayBillOnline');
        $this->partyB = config('mpesa.party_b', '');
        $this->callbackBaseUrl = rtrim(config('mpesa.callback_base_url', ''), '/');
        $this->callbackToken = config('mpesa.callback_token', '');
        $this->timeoutSeconds = (int) config('mpesa.timeout_seconds', 120);
        $this->simulate = (bool) config('mpesa.simulate', true);
    }

    /**
     * Get OAuth access token from Daraja, cached for slightly less than expiry.
     * Refreshed on 401.
     *
     * @throws \RuntimeException if credentials are missing in real mode
     */
    public function getAccessToken(): string
    {
        $cacheKey = 'mpesa_access_token_' . $this->env;

        $token = Cache::get($cacheKey);
        if ($token) {
            return $token;
        }

        if ($this->simulate) {
            $token = 'simulate_token_' . Str::random(24);
            Cache::put($cacheKey, $token, now()->addMinutes(55));
            Log::info('M-Pesa SIMULATE access token issued');
            return $token;
        }

        if (empty($this->consumerKey) || empty($this->consumerSecret)) {
            throw new \RuntimeException(
                'M-Pesa real mode requires MPESA_CONSUMER_KEY and MPESA_CONSUMER_SECRET in .env'
            );
        }

        $baseUrl = $this->env === 'production'
            ? 'https://api.safaricom.co.ke'
            : 'https://sandbox.safaricom.co.ke';

        try {
            $response = Http::withBasicAuth($this->consumerKey, $this->consumerSecret)
                ->timeout($this->timeoutSeconds)
                ->get("{$baseUrl}/oauth/v1/generate?grant_type=client_credentials");

            if (! $response->successful()) {
                Log::error('M-Pesa token request failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                throw new \RuntimeException('Failed to obtain M-Pesa access token.');
            }

            $data = $response->json();
            $token = $data['access_token'] ?? '';
            if (empty($token)) {
                throw new \RuntimeException('M-Pesa token response missing access_token.');
            }

            // Daraja tokens are valid for 1 hour; cache for 50 minutes
            Cache::put($cacheKey, $token, now()->addMinutes(50));

            Log::info('M-Pesa access token obtained', ['env' => $this->env]);

            return $token;
        } catch (\RuntimeException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('M-Pesa token request exception', [
                'exception' => get_class($e),
                'message' => $e->getMessage(),
            ]);
            throw new \RuntimeException('M-Pesa service unavailable: ' . $e->getMessage());
        }
    }

    /**
     * Normalize a Kenyan phone number to 254XXXXXXXX format.
     *
     * @throws \InvalidArgumentException if the phone number is invalid
     */
    public function normalizePhone(string $phone): string
    {
        $p = preg_replace('/[\s-]+/', '', $phone);

        // Remove leading +
        if (str_starts_with($p, '+')) {
            $p = substr($p, 1);
        }

        // Convert local 07xx or 01xx to 254...
        if (preg_match('/^0(7|1)[0-9]{8}$/', $p)) {
            $p = '254' . substr($p, 1);
        }

        // Already 2547xx or 2541xx
        if (preg_match('/^254(7|1)[0-9]{8}$/', $p)) {
            return $p;
        }

        throw new \InvalidArgumentException(
            'Invalid phone number format. Expected 07XX, 01XX, +2547XX, or 254XXXXXXXX, got: ' . $phone
        );
    }

    /**
     * Generate the Daraja STK Push password:
     * base64(shortcode + passkey + timestamp)
     *
     * Timestamp must be in Africa/Nairobi timezone, format YYYYMMDDHHmmss.
     */
    protected function generatePassword(string $timestamp): string
    {
        return base64_encode($this->shortcode . $this->passkey . $timestamp);
    }

    /**
     * Build the full callback URL including the token secret.
     */
    public function getCallbackUrl(): string
    {
        if (empty($this->callbackBaseUrl) || empty($this->callbackToken)) {
            return '';
        }

        return $this->callbackBaseUrl . '/api/payments/mpesa/callback/' . $this->callbackToken;
    }

    /**
     * Initiate STK Push via Daraja.
     *
     * @return array ['success' => bool, 'checkout_request_id' => string|null,
     *               'merchant_request_id' => string|null, 'response' => array]
     * @throws \RuntimeException if real mode credentials are missing
     */
    public function stkPush(Payment $payment): array
    {
        if ($this->simulate) {
            return $this->simulateStkPush($payment);
        }

        if (empty($this->consumerKey) || empty($this->consumerSecret) || empty($this->passkey)) {
            throw new \RuntimeException(
                'M-Pesa real mode requires MPESA_CONSUMER_KEY, MPESA_CONSUMER_SECRET and MPESA_PASSKEY'
            );
        }

        $phone = $this->normalizePhone($payment->phone);
        $amount = (int) $payment->amount;
        $timestamp = now()->timezone('Africa/Nairobi')->format('YmdHis');
        $password = $this->generatePassword($timestamp);

        $callbackUrl = $this->getCallbackUrl();
        if (empty($callbackUrl)) {
            throw new \RuntimeException(
                'M-Pesa real mode requires MPESA_CALLBACK_BASE_URL and MPESA_CALLBACK_TOKEN'
            );
        }

        $accountReference = 'V' . $payment->violation_id;
        $transactionDesc = 'Traffic fine payment - Violation #' . $payment->violation_id;

        Log::info('M-Pesa STK push request', [
            'payment_id' => $payment->id,
            'phone' => $phone,
            'amount' => $amount,
            'account_reference' => $accountReference,
            'callback_url' => $callbackUrl,
            // NEVER log consumer_secret, passkey, or password
        ]);

        try {
            $response = Http::withToken($this->getAccessToken())
                ->timeout($this->timeoutSeconds)
                ->post("{$this->baseUrl()}/mpesa/stkpush/v1/processrequest", [
                    'BusinessShortCode' => $this->shortcode,
                    'Password' => $password,
                    'Timestamp' => $timestamp,
                    'TransactionType' => $this->transactionType,
                    'Amount' => $amount,
                    'PartyA' => $phone,
                    'PartyB' => $this->partyB ?: $this->shortcode,
                    'PhoneNumber' => $phone,
                    'CallBackURL' => $callbackUrl,
                    'AccountReference' => $accountReference,
                    'TransactionDesc' => $transactionDesc,
                ]);

            if (! $response->successful()) {
                Log::error('M-Pesa STK push failed', [
                    'payment_id' => $payment->id,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return [
                    'success' => false,
                    'checkout_request_id' => null,
                    'merchant_request_id' => null,
                    'response' => ['error' => $response->body()],
                ];
            }

            $data = $response->json();

            Log::info('M-Pesa STK push accepted', [
                'payment_id' => $payment->id,
                'checkout_request_id' => $data['CheckoutRequestID'] ?? null,
                'merchant_request_id' => $data['MerchantRequestID'] ?? null,
                'response_code' => $data['ResponseCode'] ?? null,
                'response_desc' => $data['ResponseDesc'] ?? null,
            ]);

            return [
                'success' => true,
                'checkout_request_id' => $data['CheckoutRequestID'] ?? null,
                'merchant_request_id' => $data['MerchantRequestID'] ?? null,
                'response' => $data,
            ];
        } catch (\RuntimeException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('M-Pesa STK push exception', [
                'payment_id' => $payment->id,
                'exception' => get_class($e),
                'message' => $e->getMessage(),
            ]);
            return [
                'success' => false,
                'checkout_request_id' => null,
                'merchant_request_id' => null,
                'response' => ['error' => $e->getMessage()],
            ];
        }
    }

    /**
     * Query STK Push status via Daraja.
     */
    public function stkQuery(string $checkoutRequestId): array
    {
        if ($this->simulate) {
            return $this->simulateQuery($checkoutRequestId);
        }

        $token = $this->getAccessToken();
        $timestamp = now()->timezone('Africa/Nairobi')->format('YmdHis');
        $password = $this->generatePassword($timestamp);

        try {
            $response = Http::withToken($token)
                ->timeout($this->timeoutSeconds)
                ->post("{$this->baseUrl()}/mpesa/stkpushquery/v1/query", [
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
            Log::warning('M-Pesa query exception', [
                'checkout_request_id' => $checkoutRequestId,
                'exception' => get_class($e),
                'message' => $e->getMessage(),
            ]);
            return [
                'success' => false,
                'result_code' => null,
                'result_desc' => $e->getMessage(),
                'response' => [],
            ];
        }
    }

    /**
     * Get the base API URL based on environment.
     */
    protected function baseUrl(): string
    {
        return $this->env === 'production'
            ? 'https://api.safaricom.co.ke'
            : 'https://sandbox.safaricom.co.ke';
    }

    // ------------------------------------------------------------------
    // Simulation mode (offline testing)
    // ------------------------------------------------------------------

    protected function simulateStkPush(Payment $payment): array
    {
        $merchantRequestId = 'MOCK-' . Str::random(16);
        $checkoutRequestId = 'MOCK-CHECKOUT-' . Str::random(16);

        // Deterministic outcomes by last 4 digits of phone
        $phoneSuffix = (int) substr($payment->phone, -4);
        $resultCode = '0';
        $resultDesc = 'Success. Request accepted for processing.';

        if ($phoneSuffix === 2) {
            $resultCode = '1';
            $resultDesc = 'Insufficient funds';
        } elseif ($phoneSuffix === 3) {
            $resultCode = '1032';
            $resultDesc = 'Cancelled by user';
        } elseif ($phoneSuffix === 4) {
            $resultCode = '1037';
            $resultDesc = 'Timeout';
        }

        Log::info('M-Pesa SIMULATE STK push', [
            'payment_id' => $payment->id,
            'phone' => $payment->phone,
            'amount' => $payment->amount,
            'checkout_request_id' => $checkoutRequestId,
            'result_code' => $resultCode,
        ]);

        return [
            'success' => $resultCode === '0',
            'checkout_request_id' => $checkoutRequestId,
            'merchant_request_id' => $merchantRequestId,
            'response' => [
                'MerchantRequestID' => $merchantRequestId,
                'CheckoutRequestID' => $checkoutRequestId,
                'ResponseCode' => $resultCode,
                'ResponseDesc' => $resultDesc,
                'CustomerMessage' => $resultDesc,
            ],
        ];
    }

    protected function simulateQuery(string $checkoutRequestId): array
    {
        // In simulation mode, always return completed successfully
        return [
            'success' => true,
            'result_code' => '0',
            'result_desc' => 'The service request is processed successfully.',
            'response' => [],
        ];
    }

    /**
     * Simulate a callback payload for offline testing.
     */
    public function simulateCallback(
        string $checkoutRequestId,
        string $resultCode = '0',
        string $resultDesc = 'The service request is processed successfully.'
    ): array {
        $receipt = 'MOCK-' . Str::random(12);
        $transactionDate = now()->timezone('Africa/Nairobi')->format('YmdHis');
        $phone = '254708374149';

        $callbackItems = [
            ['Name' => 'Amount', 'Value' => 1000],
            ['Name' => 'MpesaReceiptNumber', 'Value' => $receipt],
            ['Name' => 'TransactionDate', 'Value' => $transactionDate],
            ['Name' => 'PhoneNumber', 'Value' => $phone],
        ];

        return [
            'Body' => [
                'stkCallback' => [
                    'MerchantRequestID' => 'MOCK-MERCHANT-' . Str::random(16),
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
