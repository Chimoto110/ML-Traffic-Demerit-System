<?php

namespace App\Http\Controllers\Api;

use App\Contracts\PaymentGateway;
use App\Contracts\GatewayResult;
use App\Models\Payment;
use App\Models\Violation;
use App\Services\DriverStatusSynchronizer;
use App\Services\NotificationService;
use App\Services\PaymentGatewayManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Payments are create/void only - there is no destroy and no in-place
 * status rewrite other than the explicit void action. The ledger and
 * audit trail around payments stay append-only.
 *
 * Supports multiple payment methods via PaymentGatewayManager:
 *   mpesa, airtel_money, card, equity
 */
class PaymentController extends ResourceController
{
    protected string $modelClass = Payment::class;

    protected array $storeRules = [
        'violation_id' => 'required|exists:violations,id',
        'driver_id' => 'nullable|exists:driver_profiles,id',
        'amount' => 'required|numeric|min:1',
        'method' => 'required|in:mpesa,airtel_money,card,equity',
        'phone' => 'nullable|string|max:255',
        'reference_number' => 'nullable|string|max:255',
        'paid_at' => 'nullable|date',
        // Card fields
        'card_number' => 'nullable|string|max:255',
        'cardholder_name' => 'nullable|string|max:255',
        'expiry_month' => 'nullable|integer|min:1|max:12',
        'expiry_year' => 'nullable|integer|min:2020|max:2050',
        'cvv' => 'nullable|string|max:4',
    ];

    protected array $searchable = ['reference_number', 'transaction_ref', 'mpesa_receipt', 'status', 'method', 'card_last4'];

    protected array $sortable = ['id', 'amount', 'amount_paid', 'status', 'paid_at', 'created_at'];

    protected array $filterable = ['status', 'method', 'driver_id', 'violation_id'];

    protected array $with = ['violation'];

    protected array $showWith = ['violation.driver'];

    /**
     * Payments cannot be updated through this resource - only voided.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        abort(405, 'Payments cannot be edited. Void the payment and create a new one instead.');
    }

    /**
     * Payments cannot be deleted - only voided.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        abort(405, 'Payments cannot be deleted. Void the payment instead.');
    }

    /**
     * GET /api/payments/methods - list available payment methods.
     */
    public function methods(): JsonResponse
    {
        $gatewayManager = app(PaymentGatewayManager::class);

        return response()->json([
            'methods' => $gatewayManager->allMethods(),
            'default' => config('payments.default_method', 'mpesa'),
        ]);
    }

    /**
     * POST /api/payments - initiate a payment via the appropriate gateway.
     * Overrides ResourceController::store() because payment initiation
     * involves a third-party gateway call, not just model creation.
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorizeInstance('create');

        $validated = $request->validate($this->storeRules);
        $gatewayManager = app(PaymentGatewayManager::class);

        $result = DB::transaction(function () use ($request, $validated, $gatewayManager) {
            $violation = Violation::findOrFail($validated['violation_id']);
            $driverId = (int) ($validated['driver_id'] ?? $violation->driver_id);
            $user = $request->user();

            // Drivers may only pay for their own violations.
            if ($user && $user->role === 'motorist') {
                $ownDriverId = (int) \App\Models\DriverProfile::where('user_id', $user->id)->value('id');

                if ($ownDriverId !== (int) $violation->driver_id) {
                    $this->failValidation(
                        'You can only pay fines for your own violations.',
                        ['violation_id' => ['You can only pay fines for your own violations.']]
                    );
                }

                $driverId = $ownDriverId;
            }

            $amount = (float) $validated['amount'];
            $method = $validated['method'];
            $idempotencyKey = $request->header('Idempotency-Key');

            // Idempotency check
            if ($idempotencyKey) {
                $existing = Payment::where('idempotency_key', $idempotencyKey)->first();
                if ($existing) {
                    return [
                        'payment' => $existing->fresh(),
                        'gateway_result' => [
                            'success' => true,
                            'reference' => $existing->provider_reference,
                            'result_code' => $existing->result_code,
                            'result_desc' => $existing->result_desc,
                            'next_action' => ['type' => 'already_paid', 'message' => 'Payment already initiated.'],
                        ],
                        'idempotent_replay' => true,
                    ];
                }
            }

            $payment = Payment::create([
                'violation_id' => $validated['violation_id'],
                'driver_id' => $driverId,
                'amount' => $amount,
                'amount_paid' => null,
                'method' => $method,
                'payment_method' => $method,
                'status' => 'pending',
                'transaction_ref' => 'PAY-' . strtoupper(str_replace('-', '', substr(md5(uniqid((string) mt_rand(), true)), 0, 12))),
                'currency' => 'KES',
                'paid_at' => null,
                'idempotency_key' => $idempotencyKey,
                'phone' => $validated['phone'] ?? null,
                'is_synthetic' => $violation->is_synthetic,
            ]);

            // For card payments, validate and store card details (no PAN/CVV storage)
            if ($method === 'card') {
                $cardNumber = $validated['card_number'] ?? null;
                if ($cardNumber) {
                    $cardNumber = preg_replace('/\s+/', '', $cardNumber);
                    $brand = $this->detectCardBrand($cardNumber);
                    $last4 = substr($cardNumber, -4);

                    $payment->card_brand = $brand;
                    $payment->card_last4 = $last4;
                    $payment->save();
                }
            }

            // Initiate via gateway
            $gatewayResult = $gatewayManager->initiate($method, $payment, $validated);

            if ($gatewayResult->success) {
                $payment->update([
                    'provider_reference' => $gatewayResult->reference,
                    'result_code' => $gatewayResult->resultCode,
                    'result_desc' => $gatewayResult->resultDesc,
                ]);
            } else {
                $payment->update([
                    'status' => 'failed',
                    'result_code' => $gatewayResult->resultCode,
                    'result_desc' => $gatewayResult->resultDesc,
                    'failure_reason' => $gatewayResult->resultDesc,
                ]);
            }

            return [
                'payment' => $payment->fresh(),
                'gateway_result' => [
                    'success' => $gatewayResult->success,
                    'reference' => $gatewayResult->reference,
                    'result_code' => $gatewayResult->resultCode,
                    'result_desc' => $gatewayResult->resultDesc,
                    'next_action' => $gatewayResult->nextAction,
                ],
            ];
        });

        // Notify driver that payment was initiated — outside the transaction,
        // never fails the payment.
        try {
            $driverUserId = \App\Models\DriverProfile::where('id', $driverId)->value('user_id');
            if ($driverUserId) {
                app(NotificationService::class)->notifyUser(
                    (int) $driverUserId,
                    'Payment Initiated',
                    sprintf('A payment of KES %s for violation #%d has been initiated. Please complete the payment.', number_format($amount, 2), $validated['violation_id']),
                    ['payment_id' => $result['payment']->id, 'violation_id' => $validated['violation_id'], 'amount' => $amount],
                    'payment',
                    (int) $result['payment']->id
                );
            }
        } catch (\Throwable $e) {
            Log::warning('Payment initiation notification failed: ' . $e->getMessage());
        }

        return response()->json([
            'data' => $this->transform($result['payment']->fresh()),
            'meta' => [
                'gateway_result' => $result['gateway_result'],
                'idempotent_replay' => $result['idempotent_replay'] ?? false,
            ],
        ], 201);
    }

    /**
     * Detect card brand from card number.
     */
    protected function detectCardBrand(string $cardNumber): ?string
    {
        $cardNumber = preg_replace('/\s+/', '', $cardNumber);

        if (preg_match('/^4[0-9]{12}(?:[0-9]{3})?$/', $cardNumber)) {
            return 'visa';
        }

        if (preg_match('/^(5[1-5][0-9]{14}|2(22[1-9][0-9]{12}|2[3-9][0-9]{13}[3-6][0-9]{10}|[3-6][0-9]{14}|7[0-9]{13}))$/', $cardNumber)) {
            return 'mastercard';
        }

        return 'unknown';
    }

    /**
     * POST /api/payments/{id}/void - marks a payment voided (never deleted).
     */
    public function void(Request $request, int $id, NotificationService $notifications): JsonResponse
    {
        $payment = Payment::findOrFail($id);
        $this->authorizeInstance('delete', $payment);

        if ($payment->status === 'voided') {
            return response()->json(['data' => $this->transform($payment), 'meta' => ['already_voided' => true]]);
        }

        DB::transaction(function () use ($payment) {
            $payment->forceFill([
                'status' => 'voided',
                'amount_paid' => 0,
            ])->save();
        });

        $driverUserId = \App\Models\DriverProfile::where('id', $payment->driver_id)->value('user_id');
        if ($driverUserId) {
            $notifications->notifyUser(
                (int) $driverUserId,
                'Payment Voided',
                sprintf('Your payment of KES %s (ref %s) was voided by NTSA.', number_format((float) $payment->amount, 2), $payment->reference_number ?? '-'),
                ['payment_id' => $payment->id],
                'payment',
                (int) $payment->id
            );
        }

        return response()->json(['data' => $this->transform($payment->fresh())]);
    }

    protected function scopeToMotorist($user, $query): void
    {
        $query->where('driver_id', function ($sub) use ($user) {
            $sub->select('id')
                ->from('driver_profiles')
                ->where('user_id', $user->id);
        });
    }

    protected function transform($row): array
    {
        $payload = parent::transform($row);
        $payload['driver_name'] = $row->violation?->driver?->full_name;
        $payload['offence_type'] = $row->violation?->offense_type;
        $payload['card_last4'] = $row->card_last4;
        $payload['card_brand'] = $row->card_brand;
        $payload['provider_reference'] = $row->provider_reference;

        return $payload;
    }
}
