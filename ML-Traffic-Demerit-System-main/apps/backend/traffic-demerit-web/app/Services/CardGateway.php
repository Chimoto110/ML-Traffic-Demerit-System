<?php

namespace App\Services;

use App\Contracts\PaymentGateway;
use App\Contracts\GatewayResult;
use App\Models\Payment;
use Illuminate\Support\Facades\Log;

/**
 * CardGateway
 * -----------
 * Card payments (Visa / Mastercard) with cardholder name, number (Luhn,
 * brand auto-detect with logo), expiry MM/YY, CVV.
 *
 * SECURITY: Never stores or logs full card number or CVV. In simulation
 * mode, validates then discards PAN/CVV; stores only brand + last4.
 */
class CardGateway implements PaymentGateway
{
    public function displayName(): string
    {
        return 'Card (Visa / Mastercard)';
    }

    public function methodCode(): string
    {
        return 'card';
    }

    public function isSimulate(): bool
    {
        return (bool) config('payments.card_simulate', true);
    }

    public function initiate(Payment $payment, array $payload): GatewayResult
    {
        $cardNumber = $payload['card_number'] ?? null;
        $cardholderName = $payload['cardholder_name'] ?? null;
        $expiryMonth = $payload['expiry_month'] ?? null;
        $expiryYear = $payload['expiry_year'] ?? null;
        $cvv = $payload['cvv'] ?? null;

        // Validate
        if (! $cardNumber) {
            return new GatewayResult(false, null, null, 'Card number is required.', [], null);
        }

        $cardNumber = preg_replace('/\s+/', '', $cardNumber);

        if (! $this->validateLuhn($cardNumber)) {
            return new GatewayResult(false, null, null, 'Invalid card number.', [], null);
        }

        $brand = $this->detectBrand($cardNumber);
        if (! $brand) {
            return new GatewayResult(false, null, null, 'Unsupported card brand. Visa and Mastercard accepted.', [], null);
        }

        if (! $expiryMonth || ! $expiryYear) {
            return new GatewayResult(false, null, null, 'Expiry date is required.', [], null);
        }

        if (! $this->isExpiryValid($expiryMonth, $expiryYear)) {
            return new GatewayResult(false, null, null, 'Card has expired.', [], null);
        }

        if (! $cvv || ! preg_match('/^\d{3,4}$/', $cvv)) {
            return new GatewayResult(false, null, null, 'CVV is required.', [], null);
        }

        if ($this->isSimulate()) {
            // Simulation: deterministic based on card number
            $last4 = substr($cardNumber, -4);
            $success = $last4 !== '0002'; // 4242 4242 4242 4242 succeeds, 4000 0000 0000 0002 declines

            Log::info('Card SIMULATE payment', [
                'last4' => $last4,
                'brand' => $brand,
                'amount' => $payment->amount,
                'success' => $success,
            ]);

            if ($success) {
                return new GatewayResult(
                    true,
                    'CARD-' . uniqid(),
                    '0',
                    'Payment successful.',
                    [],
                    ['type' => 'card_success', 'message' => 'Payment processed successfully.']
                );
            }

            return new GatewayResult(
                false,
                null,
                'declined',
                'Card was declined. Please try another payment method.',
                [],
                ['type' => 'card_declined', 'message' => 'Card declined.']
            );
        }

        // Real mode: tokenize via gateway hosted fields (not implemented here).
        // Backend receives only a token, never the PAN or CVV.
        return new GatewayResult(
            false,
            null,
            null,
            'Card gateway not configured for live mode.',
            [],
            null
        );
    }

    public function query(Payment $payment): GatewayResult
    {
        // Card payments are synchronous - the result is known at initiate time.
        // Query just returns the current payment status.
        return new GatewayResult(
            $payment->status === 'completed',
            $payment->provider_reference,
            $payment->result_code,
            $payment->result_desc,
            [],
            null
        );
    }

    /**
     * Luhn algorithm validation.
     */
    protected function validateLuhn(string $number): bool
    {
        $sum = 0;
        $alternate = false;
        for ($i = strlen($number) - 1; $i >= 0; $i--) {
            $digit = (int) $number[$i];
            if ($alternate) {
                $digit *= 2;
                if ($digit > 9) {
                    $digit -= 9;
                }
            }
            $sum += $digit;
            $alternate = !$alternate;
        }

        return $sum % 10 === 0;
    }

    /**
     * Detect card brand from number prefix.
     */
    protected function detectBrand(string $number): ?string
    {
        // Visa: starts with 4, 13 or 16 digits
        if (preg_match('/^4[0-9]{12}(?:[0-9]{3})?$/', $number)) {
            return 'visa';
        }

        // Mastercard: starts with 51-55 or 2221-2720, 16 digits
        if (preg_match('/^(5[1-5][0-9]{14}|2(22[1-9][0-9]{12}|2[3-9][0-9]{13}[3-6][0-9]{10}|[3-6][0-9]{14}|7[0-9]{13}))$/', $number)) {
            return 'mastercard';
        }

        return null;
    }

    /**
     * Validate expiry date is in the future.
     */
    protected function isExpiryValid(int $month, int $year): bool
    {
        if ($month < 1 || $month > 12) {
            return false;
        }

        $expiry = \Carbon\Carbon::create($year, $month, 1)->endOfMonth();
        $now = \Carbon\Carbon::now();

        return $expiry->isAfter($now);
    }
}
