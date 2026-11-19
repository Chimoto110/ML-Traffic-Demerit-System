<?php

namespace App\Http\Controllers;

use App\Contracts\PaymentGateway;
use App\Contracts\GatewayResult;
use App\Models\Payment;
use App\Models\Violation;
use App\Services\DriverStatusSynchronizer;
use App\Services\Mpesa\DarajaClient;
use App\Services\Mpesa\PaymentCompletionService;
use App\Services\NotificationService;
use App\Services\PaymentGatewayManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class PaymentController extends Controller
{
    protected int $finePerPoint = 500; // KES

    /**
     * Initiate an M-Pesa payment for a violation.
     *
     * State machine: initiated -> pending -> completed | failed | expired
     *
     * Auth required; motorist must own the violation.
     * Idempotency-Key header supported.
     */
    public function initiate(Request $request, Violation $violation, PaymentGatewayManager $gatewayManager)
    {
        // Rate-limit: 5 payment initiation attempts per minute per user
        $rateLimitKey = 'payment_initiate:' . $request->user()?->id . ':' . $request->ip();
        if (RateLimiter::tooManyAttempts($rateLimitKey, 5)) {
            $seconds = RateLimiter::availableIn($rateLimitKey);
            return response()->json([
                'message' => 'Too many payment attempts. Please try again later.',
                'retry_after' => $seconds,
                'code' => 'rate_limited',
            ], 429);
        }

        // Validate the violation belongs to the authenticated user
        $driver = $violation->driver;
        if (! $driver || $request->user()?->id !== $driver->user_id) {
            RateLimiter::hit($rateLimitKey, 60);
            return response()->json([
                'message' => 'This violation does not belong to your account.',
            ], 403);
        }

        if ($violation->status !== 'confirmed') {
            return response()->json([
                'message' => 'Violation is not in a payable state.',
            ], 422);
        }

        $method = $request->input('method', config('payments.default_method', 'mpesa'));
        $amount = (int) ($violation->points_assigned * $this->finePerPoint);

        if ($amount <= 0) {
            return response()->json([
                'message' => 'Fine amount must be greater than zero.',
            ], 422);
        }

        $idempotencyKey = $request->header('Idempotency-Key');

        // Validate phone for M-Pesa before creating any payment row.
        if ($method === 'mpesa') {
            $phone = $request->input('phone');
            if (! $phone) {
                return response()->json([
                    'message' => 'Phone number is required for M-Pesa payment.',
                    'errors' => ['phone' => ['Phone number is required.']],
                ], 422);
            }
            try {
                app(\App\Services\Mpesa\DarajaClient::class)->normalizePhone($phone);
            } catch (\InvalidArgumentException $e) {
                return response()->json([
                    'message' => 'Invalid phone number.',
                    'errors' => ['phone' => [$e->getMessage()]],
                ], 422);
            }
        }

        // Block if a completed payment already exists
        $existingCompleted = Payment::where('violation_id', $violation->id)
            ->where('status', 'completed')
            ->first();

        if ($existingCompleted) {
            return response()->json([
                'message' => 'This violation has already been paid.',
                'payment' => $existingCompleted->fresh(),
            ], 409);
        }

        // If a pending payment exists, return it so the UI can resume polling
        $existingPending = Payment::where('violation_id', $violation->id)
            ->where('status', 'pending')
            ->first();

        if ($existingPending) {
            return response()->json([
                'message' => 'A payment is already pending for this violation.',
                'payment' => $existingPending->fresh(),
                'idempotent_replay' => true,
            ], 200);
        }

        // Idempotency check
        if ($idempotencyKey) {
            $existing = Payment::where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                return response()->json([
                    'payment' => $existing->fresh(),
                    'message' => 'Payment already initiated.',
                    'idempotent_replay' => true,
                ], 200);
            }
        }

        // Create the payment row with status 'initiated' inside a transaction
        $payment = DB::transaction(function () use ($violation, $driver, $method, $amount, $phone, $idempotencyKey) {
            return Payment::create([
                'violation_id' => $violation->id,
                'driver_id' => $violation->driver_id,
                'amount' => $amount,
                'amount_paid' => null,
                'method' => $method,
                'payment_method' => $method,
                'status' => 'initiated',
                'transaction_ref' => 'PAY-' . strtoupper(str_replace('-', '', substr(md5(uniqid((string) mt_rand(), true)), 0, 12))),
                'currency' => 'KES',
                'paid_at' => null,
                'initiated_at' => now(),
                'expires_at' => now()->addSeconds(config('mpesa.timeout_seconds', 120)),
                'idempotency_key' => $idempotencyKey,
                'phone' => $phone ?? null,
                'environment' => config('mpesa.env', 'sandbox'),
                'is_synthetic' => $violation->is_synthetic,
            ]);
        });

        // Record initiation event
        $payment->recordEvent('initiated', null, 'user');

        // Initiate via gateway OUTSIDE the transaction
        try {
            $gatewayResult = $gatewayManager->initiate($method, $payment, $request->all());

            if ($gatewayResult->success) {
                // Daraja accepted the STK push
                $checkoutRequestId = $gatewayResult->reference;
                $merchantRequestId = $gatewayResult->rawResponse['MerchantRequestID'] ?? null;

                $payment->update([
                    'status' => 'pending',
                    'provider_reference' => $checkoutRequestId,
                    'checkout_request_id' => $checkoutRequestId,
                    'merchant_request_id' => $merchantRequestId,
                    'result_code' => $gatewayResult->resultCode,
                    'result_desc' => $gatewayResult->resultDesc,
                ]);

                $payment->recordEvent('pending', 'initiated', 'gateway');

                // Notify driver outside the critical path
                try {
                    if ($driver->user_id) {
                        app(NotificationService::class)->notifyUser(
                            (int) $driver->user_id,
                            'Payment Initiated',
                            sprintf('A payment of KES %s for violation #%d has been initiated. Please complete the payment on your phone.', number_format($amount, 2), $payment->violation_id),
                            ['payment_id' => $payment->id, 'violation_id' => $payment->violation_id, 'amount' => $amount],
                            'payment',
                            (int) $payment->id
                        );
                    }
                } catch (\Throwable $e) {
                    Log::warning('Payment initiation notification failed: ' . $e->getMessage());
                }

                return response()->json([
                    'payment' => $payment->fresh(),
                    'message' => 'Check your phone and enter your M-Pesa PIN.',
                    'requires_verification' => true,
                    'status' => 'pending',
                    'seconds_remaining' => $payment->secondsRemaining(),
                    'gateway_result' => [
                        'success' => true,
                        'reference' => $gatewayResult->reference,
                        'result_code' => $gatewayResult->resultCode,
                        'result_desc' => $gatewayResult->resultDesc,
                        'next_action' => $gatewayResult->nextAction,
                    ],
                ], 202);
            }

            // Gateway rejected the STK push
            $payment->update([
                'status' => 'failed',
                'failure_code' => 'GATEWAY_REJECTED',
                'failure_reason' => $gatewayResult->resultDesc ?? 'Gateway rejected the payment request.',
                'result_code' => $gatewayResult->resultCode,
                'result_desc' => $gatewayResult->resultDesc,
            ]);
            $payment->recordEvent('failed', 'initiated', 'gateway', 'GATEWAY_REJECTED', $gatewayResult->resultDesc);

            return response()->json([
                'message' => 'Failed to initiate payment. Please try again.',
                'error' => $gatewayResult->resultDesc,
                'code' => 'gateway_init_failed',
            ], 400);

        } catch (\InvalidArgumentException $e) {
            $payment->delete();
            return response()->json([
                'message' => $e->getMessage(),
                'code' => 'validation_error',
            ], 422);
        } catch (\Throwable $e) {
            Log::error('Payment initiation failed', [
                'exception' => get_class($e),
                'message' => $e->getMessage(),
                'violation_id' => $violation->id,
                'method' => $method,
            ]);
            return response()->json([
                'message' => 'Payment initiation failed.',
                'code' => 'server_error',
            ], 500);
        }
    }

    /**
     * Get payment status. Polls Daraja if still pending and expires_at hasn't passed.
     */
    public function status(Request $request, Payment $payment, PaymentGatewayManager $gatewayManager): JsonResponse
    {
        // Verify ownership: compare auth user id to driver's user_id
        $driver = $payment->driver;
        if (! $driver || $request->user()?->id !== $driver->user_id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        if ($payment->status === 'completed') {
            return response()->json($this->paymentResponse($payment));
        }

        // If pending, try querying Daraja (rate-limited)
        if ($payment->status === 'pending' && ! $payment->isExpired()) {
            try {
                $queryResult = $gatewayManager->query($payment->method, $payment);

                if ($queryResult->success && $queryResult->resultCode === '0') {
                    // Daraja says completed — complete via the single completion service
                    $completionService = app(PaymentCompletionService::class);
                    $paidAt = now();

                    // Find receipt from the payment's stored data or use a placeholder
                    $receipt = $payment->mpesa_receipt ?? 'QUERY-' . $payment->id;
                    $completionService->complete($payment, $receipt, $paidAt, [
                        'source' => 'status_query',
                        'query_result' => $queryResult->rawResponse,
                    ]);

                    return response()->json($this->paymentResponse($payment->fresh()));
                }
            } catch (\Throwable $e) {
                Log::warning('Payment status query failed: ' . $e->getMessage());
            }
        }

        // If expired, mark as expired
        if ($payment->status === 'pending' && $payment->isExpired()) {
            $completionService = app(PaymentCompletionService::class);
            $completionService->expire($payment);
        }

        return response()->json($this->paymentResponse($payment->fresh()));
    }

    /**
     * Build a consistent payment response array.
     */
    protected function paymentResponse(Payment $payment): array
    {
        return [
            'id' => $payment->id,
            'violation_id' => $payment->violation_id,
            'driver_id' => $payment->driver_id,
            'amount' => (float) $payment->amount,
            'amount_paid' => $payment->amount_paid ? (float) $payment->amount_paid : null,
            'method' => $payment->method,
            'status' => $payment->status,
            'transaction_ref' => $payment->transaction_ref,
            'reference_number' => $payment->reference_number,
            'currency' => $payment->currency,
            'method_display' => ucfirst($payment->method),
            'receipt' => $payment->mpesa_receipt,
            'failure_code' => $payment->failure_code,
            'failure_reason' => $payment->failure_reason,
            'seconds_remaining' => $payment->secondsRemaining(),
            'created_at' => $payment->created_at?->toIso8601String(),
            'paid_at' => $payment->paid_at?->toIso8601String(),
        ];
    }

    public function verify(Request $request, Payment $payment)
    {
        $validated = $request->validate([
            'status' => 'required|in:completed,failed',
            'reference_number' => 'nullable|string|max:255',
            'amount' => 'nullable|numeric|min:0',
            'currency' => 'nullable|string|max:10',
        ]);

        // Verify ownership
        $driver = $payment->driver;
        if (! $driver || $request->user()?->id !== $driver->user_id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $payment->fill([
            'status' => $validated['status'],
            'reference_number' => $validated['reference_number'] ?? $payment->reference_number,
            'amount_paid' => $validated['amount'] ?? $payment->amount,
            'currency' => $validated['currency'] ?? $payment->currency ?? 'KES',
        ]);

        if ($validated['status'] === 'completed') {
            $payment->paid_at = $payment->paid_at ?? now();

            if ($payment->violation) {
                $payment->violation->update(['status' => 'confirmed']);
            }

            // Re-sync driver status via DriverStatusSynchronizer.
            // A payment must NEVER lift a risk/points-based suspension.
            try {
                if ($driver) {
                    app(DriverStatusSynchronizer::class)->sync($driver->fresh());
                }
            } catch (\Throwable $e) {
                Log::warning('Driver status sync after payment verify failed: ' . $e->getMessage());
            }
        }

        $payment->save();

        return response()->json($payment->fresh());
    }

    /**
     * Legacy M-Pesa STK Push endpoint. Delegates to initiate() with method=mpesa.
     */
    public function payMpesa(Request $request, Violation $violation, PaymentGatewayManager $gatewayManager)
    {
        return $this->initiate($request, $violation, $gatewayManager);
    }
}
