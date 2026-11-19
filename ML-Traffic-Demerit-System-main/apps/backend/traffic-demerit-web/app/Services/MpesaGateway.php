<?php

namespace App\Services;

use App\Contracts\PaymentGateway;
use App\Contracts\GatewayResult;
use App\Models\Payment;
use App\Services\Mpesa\DarajaClient;
use Illuminate\Support\Facades\Log;

class MpesaGateway implements PaymentGateway
{
    protected DarajaClient $daraja;

    public function __construct(DarajaClient $daraja)
    {
        $this->daraja = $daraja;
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
            $normalizedPhone = $this->daraja->normalizePhone($phone);
        } catch (\InvalidArgumentException $e) {
            return new GatewayResult(false, null, null, $e->getMessage(), [], null);
        }

        // Store normalized phone on the payment
        $payment->update(['phone' => $normalizedPhone]);

        $amount = (int) $payment->amount;
        $result = $this->daraja->stkPush($payment);

        if ($result['success']) {
            return new GatewayResult(
                true,
                $result['checkout_request_id'],
                '0',
                'STK Push sent to your phone. Please enter your PIN.',
                $result['response'] ?? [],
                [
                    'type' => 'stk_push',
                    'message' => 'Check your phone and enter your M-Pesa PIN to complete the payment.',
                    'phone' => $normalizedPhone,
                    'amount' => $amount,
                ]
            );
        }

        return new GatewayResult(
            false,
            null,
            null,
            'Failed to initiate STK Push. Please try again.',
            $result['response'] ?? [],
            null
        );
    }

    public function query(Payment $payment): GatewayResult
    {
        $checkoutRequestId = $payment->checkout_request_id ?? $payment->provider_reference;
        if (! $checkoutRequestId) {
            return new GatewayResult(false, null, null, 'No checkout request ID.', [], null);
        }

        $result = $this->daraja->stkQuery($checkoutRequestId);

        return new GatewayResult(
            $result['success'],
            $checkoutRequestId,
            $result['result_code'],
            $result['result_desc'],
            $result['response'] ?? [],
            null
        );
    }
}
