<?php

namespace App\Services;

use App\Contracts\PaymentGateway;
use App\Contracts\GatewayResult;
use App\Models\Payment;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * PaymentGatewayManager
 * ---------------------
 * Resolves payment gateways by method code and delegates initiate/query calls.
 * Gateways are registered in config/payments.php.
 */
class PaymentGatewayManager
{
    /** @var array<string, PaymentGateway> */
    protected array $gateways = [];

    public function __construct()
    {
        $gatewaysConfig = config('payments.gateways', []);

        foreach ($gatewaysConfig as $methodCode => $gatewayClass) {
            if (! class_exists($gatewayClass)) {
                Log::warning("Payment gateway class not found: $gatewayClass");
                continue;
            }

            // Use Laravel's container to resolve dependencies (e.g. MpesaService)
            $gateway = app($gatewayClass);
            if (! $gateway instanceof PaymentGateway) {
                Log::warning("Payment gateway $gatewayClass does not implement PaymentGateway interface");
                continue;
            }

            $this->gateways[$methodCode] = $gateway;
        }
    }

    /**
     * Get a gateway by method code.
     */
    public function get(string $methodCode): PaymentGateway
    {
        if (! isset($this->gateways[$methodCode])) {
            throw new InvalidArgumentException("Unknown payment method: $methodCode");
        }

        return $this->gateways[$methodCode];
    }

    /**
     * Get all available gateway methods for the UI.
     */
    public function allMethods(): array
    {
        $methods = [];
        foreach ($this->gateways as $code => $gateway) {
            $methods[] = [
                'method' => $code,
                'display_name' => $gateway->displayName(),
                'simulate' => $gateway->isSimulate(),
            ];
        }

        return $methods;
    }

    /**
     * Initiate a payment through the appropriate gateway.
     */
    public function initiate(string $methodCode, Payment $payment, array $payload): GatewayResult
    {
        $gateway = $this->get($methodCode);
        return $gateway->initiate($payment, $payload);
    }

    /**
     * Query payment status through the appropriate gateway.
     */
    public function query(string $methodCode, Payment $payment): GatewayResult
    {
        $gateway = $this->get($methodCode);
        return $gateway->query($payment);
    }
}
