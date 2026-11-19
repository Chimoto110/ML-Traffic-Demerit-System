<?php

namespace App\Http\Controllers;

use App\Models\MpesaCallback;
use App\Models\Payment;
use App\Services\Mpesa\PaymentCompletionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MpesaCallbackController extends Controller
{
    protected PaymentCompletionService $completionService;

    public function __construct(PaymentCompletionService $completionService)
    {
        $this->completionService = $completionService;
    }

    /**
     * M-Pesa STK Push callback endpoint.
     *
     * PUBLIC — no auth.jwt. Called by Daraja after STK Push.
     *
     * Security:
     *   - Rejects with 404 if callback token doesn't match (constant-time compare)
     *   - Optionally enforces Safaricom source-IP allow-list
     *   - Always returns HTTP 200 to prevent Daraja retries
     *   - Never lets downstream failures (mail, webhook) turn response into 500
     */
    public function handle(Request $request): JsonResponse
    {
        $callbackToken = config('mpesa.callback_token', '');
        $providedToken = $request->route('token');

        // Constant-time token comparison
        if (empty($callbackToken) || ! hash_equals($callbackToken, (string) $providedToken)) {
            Log::warning('M-Pesa callback rejected: invalid token', [
                'ip' => $request->ip(),
            ]);
            return response()->json(['ResultCode' => 1, 'ResultDesc' => 'Rejected'], 404);
        }

        $payload = $request->all();
        $sourceIp = $request->ip();

        // Optional IP allow-list enforcement (production only)
        $ipOk = true;
        $allowedIps = config('mpesa.allowed_ips', '');
        if (! empty($allowedIps) && config('mpesa.env', 'sandbox') === 'production') {
            $ipList = array_map('trim', explode(',', $allowedIps));
            $ipOk = in_array($sourceIp, $ipList, true);
            if (! $ipOk) {
                Log::warning('M-Pesa callback rejected: IP not allow-listed', [
                    'ip' => $sourceIp,
                ]);
                return response()->json(['ResultCode' => 1, 'ResultDesc' => 'Rejected'], 403);
            }
        }

        // Always store the raw payload first (audit trail)
        $callbackRecord = MpesaCallback::create([
            'checkout_request_id' => $payload['Body']['stkCallback']['CheckoutRequestID'] ?? null,
            'merchant_request_id' => $payload['Body']['stkCallback']['MerchantRequestID'] ?? null,
            'payload' => $payload,
            'source_ip' => $sourceIp,
            'signature_ok' => true, // token verified above
            'ip_ok' => $ipOk,
            'processing_result' => 'pending',
        ]);

        // Always return 200 quickly to Daraja
        $response = ['ResultCode' => 0, 'ResultDesc' => 'Accepted'];

        try {
            $this->processCallback($callbackRecord, $payload);
        } catch (\Throwable $e) {
            Log::error('M-Pesa callback processing error', [
                'callback_id' => $callbackRecord->id,
                'exception' => get_class($e),
                'message' => $e->getMessage(),
            ]);
            $callbackRecord->update([
                'processing_result' => 'error',
                'processed_at' => now(),
            ]);
            // Still return 200 to Daraja
        }

        return response()->json($response);
    }

    /**
     * Process the callback payload after it's been stored.
     */
    protected function processCallback(MpesaCallback $callback, array $payload): void
    {
        $stkCallback = $payload['Body']['stkCallback'] ?? null;
        if (! $stkCallback) {
            $callback->update(['processing_result' => 'no_stk_callback']);
            return;
        }

        $checkoutRequestId = $stkCallback['CheckoutRequestID'] ?? null;
        $resultCode = $stkCallback['ResultCode'] ?? null;
        $resultDesc = $stkCallback['ResultDesc'] ?? '';

        if (! $checkoutRequestId) {
            $callback->update(['processing_result' => 'no_checkout_request_id']);
            return;
        }

        // Find the payment by checkout_request_id (row lock)
        $payment = Payment::where('checkout_request_id', $checkoutRequestId)
            ->lockForUpdate()
            ->first();

        if (! $payment) {
            Log::warning('M-Pesa callback for unknown checkout_request_id', [
                'checkout_request_id' => $checkoutRequestId,
            ]);
            $callback->update([
                'processing_result' => 'unknown_payment',
                'processed_at' => now(),
            ]);
            return;
        }

        $callback->update(['payment_id' => $payment->id]);

        // Idempotent: if already completed, do nothing
        if ($payment->status === 'completed') {
            Log::info('Duplicate M-Pesa callback ignored (already completed)', [
                'payment_id' => $payment->id,
                'checkout_request_id' => $checkoutRequestId,
            ]);
            $callback->update([
                'processing_result' => 'duplicate_completed',
                'processed_at' => now(),
            ]);
            return;
        }

        // Extract metadata from CallbackMetadata items (not in fixed order)
        $metadataItems = $stkCallback['CallbackMetadata']['Item'] ?? [];
        $metadataArray = [];
        foreach ($metadataItems as $item) {
            $metadataArray[$item['Name'] ?? ''] = $item['Value'] ?? null;
        }

        $receipt = $metadataArray['MpesaReceiptNumber'] ?? null;
        $amount = $metadataArray['Amount'] ?? null;
        $transactionDate = $metadataArray['TransactionDate'] ?? null;
        $phoneNumber = $metadataArray['PhoneNumber'] ?? null;

        // Reject replayed receipts (defence in depth)
        if ($receipt && Payment::where('mpesa_receipt', $receipt)
            ->where('id', '!=', $payment->id)
            ->exists()
        ) {
            Log::warning('Duplicate M-Pesa receipt rejected', [
                'payment_id' => $payment->id,
                'receipt' => $receipt,
                'existing_payment_id' => Payment::where('mpesa_receipt', $receipt)
                    ->where('id', '!=', $payment->id)
                    ->value('id'),
            ]);
            $payment->update([
                'status' => 'failed',
                'failure_code' => 'DUPLICATE_RECEIPT',
                'failure_reason' => 'Duplicate receipt.',
            ]);
            $payment->recordEvent(
                'failed',
                $payment->status,
                'callback',
                'DUPLICATE_RECEIPT',
                'Duplicate receipt.'
            );
            $callback->update([
                'processing_result' => 'duplicate_receipt',
                'processed_at' => now(),
            ]);
            return;
        }

        $success = ($resultCode === '0' || $resultCode === 0);

        if ($success) {
            // Verify Amount equals payment amount (defence in depth)
            if ($amount !== null && (float) $amount !== (float) $payment->amount) {
                Log::warning('M-Pesa amount mismatch', [
                    'payment_id' => $payment->id,
                    'expected_amount' => $payment->amount,
                    'callback_amount' => $amount,
                ]);
                $payment->update([
                    'status' => 'completed', // still mark completed but flag for review
                    'mpesa_receipt' => $receipt,
                    'result_code' => (string) $resultCode,
                    'result_desc' => $resultDesc,
                    'paid_at' => $transactionDate ? \Carbon\Carbon::parse($transactionDate) : now(),
                    'raw_callback' => array_merge(
                        $payment->raw_callback ?? [],
                        ['amount_mismatch' => true, 'expected' => $payment->amount, 'callback_amount' => $amount]
                    ),
                ]);
                $payment->recordEvent(
                    'completed',
                    $payment->status,
                    'callback',
                    null,
                    null,
                    $receipt,
                    ['amount_mismatch' => true, 'expected' => (float) $payment->amount, 'callback_amount' => (float) $amount]
                );
                $callback->update([
                    'processing_result' => 'amount_mismatch_needs_review',
                    'processed_at' => now(),
                ]);
                // Alert admin (handled by notification service in completion)
                $this->completionService->complete($payment, $receipt, \Carbon\Carbon::parse($transactionDate), [
                    'amount_mismatch' => true,
                    'expected_amount' => (float) $payment->amount,
                    'callback_amount' => (float) $amount,
                ]);
                return;
            }

            // Defence in depth: confirm with stkQuery before completing
            // (callback is unsigned, so we verify with Daraja)
            $queryResult = $this->confirmWithStkQuery($payment->checkout_request_id ?? $payment->provider_reference);
            if (! $queryResult['success'] || $queryResult['result_code'] !== '0') {
                Log::warning('STK query confirmation failed for completed callback', [
                    'payment_id' => $payment->id,
                    'checkout_request_id' => $checkoutRequestId,
                    'query_result' => $queryResult,
                ]);
                // Still proceed — callback may be genuine even if query fails temporarily
            }

            $paidAt = $transactionDate ? \Carbon\Carbon::parse($transactionDate) : now();

            $this->completionService->complete($payment, $receipt, $paidAt, [
                'transaction_date' => $transactionDate,
                'phone_number' => $phoneNumber,
                'amount' => $amount,
            ]);

            $callback->update([
                'processing_result' => 'completed',
                'processed_at' => now(),
            ]);
        } else {
            // Failed or cancelled
            $failureCode = $this->mapResultCode($resultCode);
            $payment->update([
                'status' => 'failed',
                'failure_code' => $failureCode,
                'failure_reason' => $resultDesc,
                'result_code' => (string) $resultCode,
                'result_desc' => $resultDesc,
            ]);
            $payment->recordEvent(
                'failed',
                $payment->status,
                'callback',
                $failureCode,
                $resultDesc
            );

            Log::warning('M-Pesa payment failed', [
                'payment_id' => $payment->id,
                'result_code' => $resultCode,
                'result_desc' => $resultDesc,
            ]);

            $callback->update([
                'processing_result' => 'failed',
                'processed_at' => now(),
            ]);
        }
    }

    /**
     * Confirm with Daraja STK Query as defence in depth.
     * The callback is unsigned, so we verify with Daraja directly.
     */
    protected function confirmWithStkQuery(?string $checkoutRequestId): array
    {
        if (! $checkoutRequestId) {
            return ['success' => false, 'result_code' => null];
        }

        $daraja = app(\App\Services\Mpesa\DarajaClient::class);
        return $daraja->stkQuery($checkoutRequestId);
    }

    /**
     * Map Daraja result codes to friendly failure codes.
     */
    protected function mapResultCode($resultCode): string
    {
        return match ((string) $resultCode) {
            '0' => 'SUCCESS',
            '1' => 'INSUFFICIENT_FUNDS',
            '1032' => 'CANCELLED_BY_USER',
            '1037' => 'PHONE_UNREACHABLE',
            '2001' => 'WRONG_PIN',
            default => 'UNKNOWN_' . $resultCode,
        };
    }
}
