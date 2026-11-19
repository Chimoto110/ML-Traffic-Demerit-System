<?php

namespace App\Services;

use App\Contracts\PaymentGateway;
use App\Contracts\GatewayResult;
use App\Models\Payment;
use Illuminate\Support\Facades\Log;

/**
 * EquityGateway
 * -------------
 * Equity (Equitel / Eazzy / Jenga) payment via phone or account prompt.
 * Simulation by default.
 */
class EquityGateway implements PaymentGateway
{
    public function displayName(): string
    {
        return 'Equity Bank';
    }

    public function methodCode(): string
    {
        return 'equity';
    }

    public function isSimulate(): bool
    {
        return (bool) config('payments.equity_simulate', true);
    }

    public function initiate(Payment $payment, array $payload): GatewayResult
    {
        $phone = $payload['phone'] ?? null;
        $accountNumber = $payload['account_number'] ?? null;

        if (! $phone && ! $accountNumber) {
            return new GatewayResult(false, null, null, 'Phone number or account number is required.', [], null);
        }

        $amount = (int) $payment->amount;
        $identifier = $phone ?? $accountNumber;

        Log::info('Equity SIMULATE payment', [
            'identifier' => $identifier,
            'amount' => $amount,
            'payment_id' => $payment->id,
        ]);

        return new GatewayResult(
            true,
            'EQUITY-' . uniqid(),
            '0',
            'Payment request sent. Please confirm on your phone.',
            [],
            [
                'type' => 'stk_push',
                'message' => 'Please confirm the payment on your Equity line.',
                'identifier' => $identifier,
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
