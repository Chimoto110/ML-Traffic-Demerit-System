<?php

namespace App\Services;

use App\Contracts\PaymentGateway;
use App\Contracts\GatewayResult;
use App\Models\Payment;
use Illuminate\Support\Facades\Log;

/**
 * AirtelMoneyGateway
 * ------------------
 * Airtel Money STK-style push. Simulation by default.
 */
class AirtelMoneyGateway implements PaymentGateway
{
    public function displayName(): string
    {
        return 'Airtel Money';
    }

    public function methodCode(): string
    {
        return 'airtel_money';
    }

    public function isSimulate(): bool
    {
        return (bool) config('payments.airtel_simulate', true);
    }

    public function initiate(Payment $payment, array $payload): GatewayResult
    {
        $phone = $payload['phone'] ?? null;
        if (! $phone) {
            return new GatewayResult(false, null, null, 'Phone number is required.', [], null);
        }

        $amount = (int) $payment->amount;

        Log::info('Airtel Money SIMULATE STK push', [
            'phone' => $phone,
            'amount' => $amount,
            'payment_id' => $payment->id,
        ]);

        return new GatewayResult(
            true,
            'AIRTEL-' . uniqid(),
            '0',
            'STK Push sent to your phone. Please enter your PIN.',
            [],
            [
                'type' => 'stk_push',
                'message' => 'Check your phone and enter your Airtel Money PIN.',
                'phone' => $phone,
                'amount' => $amount,
            ]
        );
    }

    public function query(Payment $payment): GatewayResult
    {
        if (! $payment->provider_reference) {
            return new GatewayResult(false, null, null, 'No payment reference.', [], null);
        }

        return new GatewayResult(
            true,
            $payment->provider_reference,
            '0',
            'Payment processed successfully.',
            [],
            null
        );
    }
}
