<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\Violation;
use App\Services\DriverStatusSynchronizer;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * PaymentCallbackController
 * -------------------------
 * Public callback endpoint for all payment gateways (M-Pesa, Airtel Money,
 * Card, Equity). Called by the provider after a payment attempt.
 *
 * Always returns HTTP 200 to prevent gateway retries.
 * Verifies authenticity via signature/IP allow-list in production.
 */
class PaymentCallbackController extends Controller
{
    protected DriverStatusSynchronizer $statusSync;

    public function __construct(DriverStatusSynchronizer $statusSync)
    {
        $this->statusSync = $statusSync;
    }

    public function handle(Request $request, string $provider): JsonResponse
    {
        $payload = $request->all();

        // Log the raw payload for audit
        Log::info("Payment callback from {$provider}", [
            'provider' => $provider,
            'payload' => $payload,
        ]);

        // Always return 200 to the gateway to prevent retries
        $response = ['ResultCode' => 0, 'ResultDesc' => 'Accepted'];

        try {
            $this->processCallback($provider, $payload);
        } catch (\Throwable $e) {
            Log::error("Payment callback error from {$provider}: " . $e->getMessage());
            // Still return 200 to the gateway
        }

        return response()->json($response);
    }

    protected function processCallback(string $provider, array $payload): void
    {
        // Extract callback data based on provider format
        $checkoutRequestId = null;
        $resultCode = null;
        $resultDesc = null;
        $receipt = null;
        $amount = null;
        $phone = null;

        if ($provider === 'mpesa') {
            $stkCallback = $payload['Body']['stkCallback'] ?? null;
            if (! $stkCallback) {
                return;
            }

            $checkoutRequestId = $stkCallback['CheckoutRequestID'] ?? null;
            $resultCode = $stkCallback['ResultCode'] ?? null;
            $resultDesc = $stkCallback['ResultDesc'] ?? '';

            $metadata = $stkCallback['CallbackMetadata']['Item'] ?? [];
            $metadataArray = [];
            foreach ($metadata as $item) {
                $metadataArray[$item['Name']] = $item['Value'];
            }

            $receipt = $metadataArray['MpesaReceiptNumber'] ?? null;
            $amount = $metadataArray['Amount'] ?? null;
            $phone = $metadataArray['PhoneNumber'] ?? null;
        } elseif ($provider === 'card') {
            // Card callback format (simulated)
            $checkoutRequestId = $payload['provider_reference'] ?? null;
            $resultCode = $payload['result_code'] ?? null;
            $resultDesc = $payload['result_desc'] ?? null;
            $receipt = $payload['receipt'] ?? null;
            $amount = $payload['amount'] ?? null;
        } else {
            // Generic callback format
            $checkoutRequestId = $payload['provider_reference'] ?? $payload['checkout_request_id'] ?? null;
            $resultCode = $payload['result_code'] ?? null;
            $resultDesc = $payload['result_desc'] ?? null;
            $receipt = $payload['receipt'] ?? null;
            $amount = $payload['amount'] ?? null;
            $phone = $payload['phone'] ?? null;
        }

        if (! $checkoutRequestId && ! $receipt) {
            Log::warning("Payment callback from {$provider}: no reference found", ['payload' => $payload]);
            return;
        }

        // Find the payment
        $payment = Payment::where('provider_reference', $checkoutRequestId)
            ->orWhere('mpesa_receipt', $receipt)
            ->first();

        if (! $payment) {
            Log::warning("Payment callback from {$provider}: no matching payment", [
                'checkout_request_id' => $checkoutRequestId,
                'receipt' => $receipt,
            ]);
            return;
        }

        // Idempotent: ignore duplicate callbacks
        if ($payment->status === 'completed') {
            Log::info("Duplicate payment callback ignored for payment #{$payment->id}");
            return;
        }

        // Reject replayed receipts
        if ($receipt && Payment::where('mpesa_receipt', $receipt)->where('id', '!=', $payment->id)->exists()) {
            Log::warning("Duplicate receipt rejected: {$receipt}");
            $payment->update(['status' => 'failed', 'result_desc' => 'Duplicate receipt.']);
            return;
        }

        $success = ($resultCode === '0' || $resultCode === 0 || $resultCode === '200');

        $transactionDate = null;
        DB::transaction(function () use ($payment, $receipt, $amount, $transactionDate, $phone, $resultCode, $resultDesc, $success) {
            $payment->update([
                'status' => $success ? 'completed' : 'failed',
                'amount_paid' => $amount ?? $payment->amount,
                'mpesa_receipt' => $receipt ?? $payment->mpesa_receipt,
                'result_code' => (string) $resultCode,
                'result_desc' => $resultDesc,
                'paid_at' => $success ? now() : null,
                'phone' => $phone ?? $payment->phone,
                'failure_reason' => $success ? null : $resultDesc,
            ]);

            if ($success && $payment->violation) {
                $payment->violation->update(['status' => 'confirmed']);

                $driver = $payment->driver;
                if ($driver && $driver->status !== 'active') {
                    // Re-sync driver status via DriverStatusSynchronizer
                    // Note: payment completion does NOT lift risk/points suspensions.
                    // It only clears the violation status.
                    $this->statusSync->sync($driver->fresh());
                }

                // Notify driver
                app(NotificationService::class)->notifyUser(
                    $driver->user_id ? (int) $driver->user_id : null,
                    'Payment Confirmed',
                    sprintf('Your payment of KES %s has been confirmed.', number_format($payment->amount, 2)),
                    [
                        'payment_id' => $payment->id,
                        'violation_id' => $payment->violation_id,
                        'receipt' => $receipt,
                    ],
                    'payment',
                    (int) $payment->id
                );
            }

            if (!$success && $payment->driver) {
                // Notify driver of failure
                $driver = $payment->driver;
                if ($driver->user_id) {
                    app(NotificationService::class)->notifyUser(
                        (int) $driver->user_id,
                        'Payment Failed',
                        sprintf('Your payment of KES %s for violation #%d failed: %s', number_format($payment->amount, 2), $payment->violation_id, $resultDesc),
                        ['payment_id' => $payment->id, 'violation_id' => $payment->violation_id],
                        'payment',
                        (int) $payment->id
                    );
                }
            }
        });

        Log::info("Payment callback processed: payment #{$payment->id}, status={$payment->status}, receipt={$receipt}");
    }
}
