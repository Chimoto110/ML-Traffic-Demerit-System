<?php

namespace App\Contracts;

use App\Models\Payment;

/**
 * PaymentGateway interface — all payment providers implement this.
 *
 * Implementations must never store or log full card numbers or CVVs.
 * In simulation mode, validate then discard PAN/CVV; store only brand + last4.
 */
interface PaymentGateway
{
    /**
     * Human-readable display name for the UI (e.g. "M-Pesa", "Card (Visa)").
     */
    public function displayName(): string;

    /**
     * Short method code stored in payments.method (e.g. "mpesa", "card").
     */
    public function methodCode(): string;

    /**
     * Whether this gateway is available in simulation mode.
     */
    public function isSimulate(): bool;

    /**
     * Initiate a payment. Returns a GatewayResult.
     */
    public function initiate(Payment $payment, array $payload): GatewayResult;

    /**
     * Query the status of a previously initiated payment.
     */
    public function query(Payment $payment): GatewayResult;
}

/**
 * Result of a gateway operation.
 */
class GatewayResult
{
    public bool $success;
    public ?string $reference;        // gateway reference (checkout_request_id, transaction_id, etc.)
    public ?string $resultCode;       // gateway result code
    public ?string $resultDesc;       // human-readable result description
    public array $rawResponse;        // raw gateway response (for debugging, no secrets)
    public ?array $nextAction;        // UI hint: ['type' => 'stk_push', 'message' => '...', ...]

    public function __construct(
        bool $success,
        ?string $reference = null,
        ?string $resultCode = null,
        ?string $resultDesc = null,
        array $rawResponse = [],
        ?array $nextAction = null
    ) {
        $this->success = $success;
        $this->reference = $reference;
        $this->resultCode = $resultCode;
        $this->resultDesc = $resultDesc;
        $this->rawResponse = $rawResponse;
        $this->nextAction = $nextAction;
    }
}
