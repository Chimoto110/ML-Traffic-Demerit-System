<?php

namespace App\Services\Mpesa;

use App\Models\DriverProfile;
use App\Models\MpesaCallback;
use App\Models\Payment;
use App\Models\PaymentEvent;
use App\Services\DriverStatusSynchronizer;
use App\Services\NotificationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaymentCompletionService
{
    protected DriverStatusSynchronizer $statusSync;
    protected NotificationService $notifications;

    public function __construct(
        DriverStatusSynchronizer $statusSync,
        NotificationService $notifications
    ) {
        $this->statusSync = $statusSync;
        $this->notifications = $notifications;
    }

    /**
     * Complete a payment in one DB transaction.
     * This is the SINGLE place where a payment transitions to completed.
     * Used by: callback, reconciliation, simulation.
     *
     * A payment must NEVER lift a risk/points-based suspension —
     * DriverStatusSynchronizer derives status from active sanctions only.
     */
    public function complete(
        Payment $payment,
        string $receipt,
        \DateTimeInterface $paidAt,
        array $callbackMetadata = []
    ): Payment {
        $oldStatus = $payment->status;

        DB::transaction(function () use ($payment, $receipt, $paidAt, $callbackMetadata) {
            $payment->update([
                'status' => 'completed',
                'amount_paid' => $payment->amount,
                'mpesa_receipt' => $receipt,
                'result_code' => '0',
                'result_desc' => 'The service request is processed successfully.',
                'paid_at' => $paidAt,
                'completed_at' => $paidAt,
                'raw_callback' => array_merge(
                    $payment->raw_callback ?? [],
                    ['completion' => $callbackMetadata, 'completed_at' => $paidAt->format(\DateTimeInterface::ATOM)]
                ),
            ]);

            if ($payment->violation) {
                $payment->violation->update(['status' => 'confirmed']);
            }
        });

        // Record audit event
        $payment->recordEvent(
            eventType: 'completed',
            oldStatus: $oldStatus,
            changedBy: 'callback',
            receipt: $receipt,
            metadata: $callbackMetadata
        );

        // AFTER commit: notify driver and admins, re-sync status.
        // These are outside the transaction and wrapped in try/catch so
        // they can never fail a completed payment.
        try {
            $driver = $payment->driver;
            if ($driver) {
                // Re-sync status via DriverStatusSynchronizer (never lifts suspension)
                $this->statusSync->sync($driver->fresh());

                // Notify driver
                if ($driver->user_id) {
                    $this->notifications->notifyUser(
                        (int) $driver->user_id,
                        'Payment Received',
                        'Your payment of KES ' . number_format($payment->amount, 2) . ' has been confirmed.',
                        [
                            'payment_id' => $payment->id,
                            'violation_id' => $payment->violation_id,
                            'receipt' => $receipt,
                        ],
                        'payment',
                        (int) $payment->id
                    );
                }

                // Notify admins
                $this->notifyAdmins($payment, $receipt);
            }
        } catch (\Throwable $e) {
            Log::warning('Post-completion notification failed', [
                'payment_id' => $payment->id,
                'exception' => get_class($e),
                'message' => $e->getMessage(),
            ]);
        }

        Log::info('Payment completed', [
            'payment_id' => $payment->id,
            'receipt' => $receipt,
            'amount' => $payment->amount,
        ]);

        return $payment->fresh();
    }

    /**
     * Mark a payment as failed.
     */
    public function fail(
        Payment $payment,
        string $failureCode,
        string $failureReason,
        array $metadata = []
    ): Payment {
        $oldStatus = $payment->status;

        $payment->update([
            'status' => 'failed',
            'failure_code' => $failureCode,
            'failure_reason' => $failureReason,
            'raw_callback' => array_merge(
                $payment->raw_callback ?? [],
                $metadata
            ),
        ]);

        $payment->recordEvent(
            eventType: 'failed',
            oldStatus: $oldStatus,
            changedBy: 'callback',
            failureCode: $failureCode,
            failureReason: $failureReason,
            metadata: $metadata
        );

        // Notify driver of failure
        try {
            $driver = $payment->driver;
            if ($driver && $driver->user_id) {
                $this->notifications->notifyUser(
                    (int) $driver->user_id,
                    'Payment Failed',
                    sprintf(
                        'Your payment of KES %s for violation #%d failed: %s',
                        number_format((float) $payment->amount, 2),
                        $payment->violation_id,
                        $failureReason
                    ),
                    [
                        'payment_id' => $payment->id,
                        'violation_id' => $payment->violation_id,
                    ],
                    'payment',
                    (int) $payment->id
                );
            }
        } catch (\Throwable $e) {
            Log::warning('Payment failure notification failed', [
                'payment_id' => $payment->id,
                'exception' => get_class($e),
                'message' => $e->getMessage(),
            ]);
        }

        Log::warning('Payment failed', [
            'payment_id' => $payment->id,
            'failure_code' => $failureCode,
            'failure_reason' => $failureReason,
        ]);

        return $payment->fresh();
    }

    /**
     * Mark a payment as expired.
     */
    public function expire(Payment $payment): Payment
    {
        $oldStatus = $payment->status;

        $payment->update([
            'status' => 'expired',
            'failure_code' => 'EXPIRED',
            'failure_reason' => 'Payment expired: no completion received within timeout.',
        ]);

        $payment->recordEvent(
            eventType: 'expired',
            oldStatus: $oldStatus,
            changedBy: 'system'
        );

        Log::info('Payment expired', [
            'payment_id' => $payment->id,
        ]);

        return $payment->fresh();
    }

    /**
     * Notify admins about a completed payment (defence in depth alerting).
     */
    protected function notifyAdmins(Payment $payment, string $receipt): void
    {
        $driver = $payment->driver;
        $adminUserIds = \App\Models\User::where('role', 'admin')
            ->where('is_active', true)
            ->pluck('id')
            ->toArray();

        foreach ($adminUserIds as $adminId) {
            $this->notifications->notifyUser(
                (int) $adminId,
                'Payment Received',
                sprintf(
                    'Driver %s (%s) paid KES %s for violation #%d. Receipt: %s',
                    $driver ? $driver->full_name : 'Unknown',
                    $driver ? $driver->license_no : 'Unknown',
                    number_format((float) $payment->amount, 2),
                    $payment->violation_id,
                    $receipt
                ),
                [
                    'payment_id' => $payment->id,
                    'violation_id' => $payment->violation_id,
                    'receipt' => $receipt,
                    'driver_id' => $driver ? $driver->id : null,
                ],
                'payment',
                (int) $payment->id
            );
        }
    }
}
