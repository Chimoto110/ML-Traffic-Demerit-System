<?php

namespace App\Services\Mpesa;

use App\Contracts\PaymentGateway;
use App\Contracts\GatewayResult;
use App\Models\Payment;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * PythonMpesaGateway
 * ------------------
 * Wraps the external Python MPesa service (FastAPI, port 8003) for STK Push
 * and STK Query. Implements the PaymentGateway interface so Laravel can call
 * the Python service instead of calling Daraja directly from PHP.
 *
 * Config:
 *   MPESA_SERVICE_URL   - Base URL of the Python service (default http://127.0.0.1:8003)
 *   INTERNAL_API_KEY    - Shared secret sent as X-Internal-Key header
 *   MPESA_TIMEOUT_SECONDS - HTTP timeout (default 10)
 *
 * Simulation mode (MPESA_SIMULATE=true) is handled by the Python service itself;
 * this gateway simply forwards requests and returns the result.
 */
class PythonMpesaGateway implements PaymentGateway
{
    protected string $serviceUrl;
    protected string $internalApiKey;
    protected int $timeoutSeconds;

    public function __construct()
    {
        $this->serviceUrl = rtrim(config('mpesa.service_url', 'http://127.0.0.1:8003'), '/');
        $this->internalApiKey = config('services.mpesa.internal_api_key', '');
        $this->timeoutSeconds = (int) config('mpesa.timeout_seconds', 120);
    }

    public function displayName(): string
    {
        return 'M-Pesa';
    }

    public function methodCode(): string
    {
        return 'mpesa';
    }

    public function isSimulate(): bool
    {
        return (bool) config('mpesa.simulate', true);
    }

    public function initiate(Payment $payment, array $payload): GatewayResult
    {
        $phone = $payload['phone'] ?? $payment->phone;
        if (! $phone) {
            return new GatewayResult(false, null, null, 'Phone number is required.', [], null);
        }

        try {
            // Normalize phone using DarajaClient logic (same validation)
            $normalizedPhone = $this->normalizePhone($phone);
        } catch (\InvalidArgumentException $e) {
            return new GatewayResult(false, null, null, $e->getMessage(), [], null);
        }

        // Store normalized phone on the payment
        $payment->update(['phone' => $normalizedPhone]);

        $amount = (int) $payment->amount;
        $reference = 'V' . $payment->violation_id;
        $description = 'Traffic fine payment - Violation #' . $payment->violation_id;

        // Simulation mode: deterministic outcomes by last 4 digits of phone
        if ($this->isSimulate()) {
            $phoneSuffix = (int) substr($normalizedPhone, -4);
            $resultCode = '0';
            $resultDesc = 'Success. Request accepted for processing.';

            if ($phoneSuffix % 10 === 2) {
                $resultCode = '1';
                $resultDesc = 'Insufficient funds';
            } elseif ($phoneSuffix % 10 === 3) {
                $resultCode = '1032';
                $resultDesc = 'Cancelled by user';
            } elseif ($phoneSuffix % 10 === 4) {
                $resultCode = '1037';
                $resultDesc = 'Timeout';
            }

            $checkoutRequestId = 'MOCK-CHECKOUT-' . Str::random(16);
            $merchantRequestId = 'MOCK-MERCHANT-' . Str::random(16);

            Log::info('PythonMpesaGateway SIMULATE STK push', [
                'payment_id' => $payment->id,
                'phone' => $normalizedPhone,
                'amount' => $amount,
                'checkout_request_id' => $checkoutRequestId,
                'result_code' => $resultCode,
            ]);

            return new GatewayResult(
                $resultCode === '0',
                $checkoutRequestId,
                $resultCode,
                $resultDesc,
                [
                    'MerchantRequestID' => $merchantRequestId,
                    'CheckoutRequestID' => $checkoutRequestId,
                    'ResponseCode' => $resultCode,
                    'ResponseDesc' => $resultDesc,
                    'CustomerMessage' => $resultDesc,
                ],
                [
                    'type' => 'stk_push',
                    'message' => 'Check your phone and enter your M-Pesa PIN to complete the payment.',
                    'phone' => $normalizedPhone,
                    'amount' => $amount,
                ]
            );
        }

        try {
            $response = Http::withToken($this->internalApiKey)
                ->timeout($this->timeoutSeconds)
                ->post("{$this->serviceUrl}/stk/push", [
                    'payment_id' => (string) $payment->id,
                    'phone' => $normalizedPhone,
                    'amount' => $amount,
                    'reference' => $reference,
                    'description' => $description,
                ], [
                    'X-Internal-Key' => $this->internalApiKey,
                ]);
        } catch (\Throwable $e) {
            Log::error('Python MPesa service request failed', [
                'payment_id' => $payment->id,
                'exception' => get_class($e),
                'message' => $e->getMessage(),
            ]);
            return new GatewayResult(
                false,
                null,
                null,
                'Payment service unavailable. Please try again.',
                [],
                null
            );
        }

        if ($response->failed()) {
            $status = $response->status();
            $body = $response->body();
            Log::error('Python MPesa service returned error', [
                'payment_id' => $payment->id,
                'status' => $status,
                'body' => $body,
            ]);

            if ($status === 422) {
                return new GatewayResult(
                    false,
                    null,
                    null,
                    'Invalid phone number or amount.',
                    [],
                    null
                );
            }

            return new GatewayResult(
                false,
                null,
                null,
                'Payment service error. Please try again.',
                [],
                null
            );
        }

        $data = $response->json();

        if (! $data['ok'] ?? false) {
            return new GatewayResult(
                false,
                null,
                null,
                $data['customer_message'] ?? 'Failed to initiate STK Push. Please try again.',
                $data,
                null
            );
        }

        return new GatewayResult(
            true,
            $data['checkout_request_id'] ?? null,
            '0',
            'STK Push sent to your phone. Please enter your PIN.',
            $data,
            [
                'type' => 'stk_push',
                'message' => 'Check your phone and enter your M-Pesa PIN to complete the payment.',
                'phone' => $normalizedPhone,
                'amount' => $amount,
            ]
        );
    }

    public function query(Payment $payment): GatewayResult
    {
        $checkoutRequestId = $payment->checkout_request_id ?? $payment->provider_reference;
        if (! $checkoutRequestId) {
            return new GatewayResult(false, null, null, 'No checkout request ID.', [], null);
        }

        // Simulation mode: always return completed successfully
        if ($this->isSimulate()) {
            return new GatewayResult(
                true,
                $checkoutRequestId,
                '0',
                'The service request is processed successfully.',
                [],
                null
            );
        }

        try {
            $response = Http::withToken($this->internalApiKey)
                ->timeout($this->timeoutSeconds)
                ->post("{$this->serviceUrl}/stk/query", [
                    'checkout_request_id' => $checkoutRequestId,
                ], [
                    'X-Internal-Key' => $this->internalApiKey,
                ]);
        } catch (\Throwable $e) {
            Log::error('Python MPesa query failed', [
                'payment_id' => $payment->id,
                'exception' => get_class($e),
                'message' => $e->getMessage(),
            ]);
            return new GatewayResult(
                false,
                $checkoutRequestId,
                null,
                'Payment service unavailable.',
                [],
                null
            );
        }

        if ($response->failed()) {
            Log::warning('Python MPesa query returned error', [
                'payment_id' => $payment->id,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            return new GatewayResult(
                false,
                $checkoutRequestId,
                null,
                'Query failed.',
                [],
                null
            );
        }

        $data = $response->json();

        return new GatewayResult(
            $data['ok'] ?? false,
            $checkoutRequestId,
            $data['result_code'] ?? null,
            $data['result_desc'] ?? null,
            $data,
            null
        );
    }

    /**
     * Normalize a Kenyan phone number to 254XXXXXXXX format.
     *
     * @throws \InvalidArgumentException if the phone number is invalid
     */
    protected function normalizePhone(string $phone): string
    {
        $p = preg_replace('/[\s-]+/', '', $phone);

        if (str_starts_with($p, '+')) {
            $p = substr($p, 1);
        }

        if (preg_match('/^0(7|1)[0-9]{8}$/', $p)) {
            $p = '254' . substr($p, 1);
        }

        if (preg_match('/^254(7|1)[0-9]{8}$/', $p)) {
            return $p;
        }

        throw new \InvalidArgumentException(
            'Invalid phone number format. Expected 07XX, 01XX, +2547XX, or 254XXXXXXXX, got: ' . $phone
        );
    }
}
