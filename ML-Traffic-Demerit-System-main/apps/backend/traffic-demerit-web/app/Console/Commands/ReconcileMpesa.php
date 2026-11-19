<?php

namespace App\Console\Commands;

use App\Models\Payment;
use App\Services\Mpesa\DarajaClient;
use App\Services\Mpesa\PaymentCompletionService;
use Illuminate\Console\Command;

class ReconcileMpesa extends Command
{
    protected $signature = 'mpesa:reconcile {--dry-run : Run without making changes}';
    protected $description = 'Reconcile pending M-Pesa payments with Daraja via STK query. Safe to run concurrently.';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $daraja = app(DarajaClient::class);
        $completionService = app(PaymentCompletionService::class);

        // Find pending payments older than 30 seconds
        $pending = Payment::where('status', 'pending')
            ->where('created_at', '<', now()->subSeconds(30))
            ->get();

        $this->info("Found " . $pending->count() . " pending payment(s) to reconcile.");

        $reconciled = 0;
        $completed = 0;
        $failed = 0;
        $expired = 0;
        $errors = 0;

        foreach ($pending as $payment) {
            try {
                // Check if expired
                if ($payment->isExpired()) {
                    if (!$dryRun) {
                        $completionService->expire($payment);
                    }
                    $expired++;
                    $this->info("  Payment #{$payment->id}: EXPIRED (dry-run: {$dryRun})");
                    continue;
                }

                // Query Daraja
                $checkoutRequestId = $payment->checkout_request_id ?? $payment->provider_reference;
                if (! $checkoutRequestId) {
                    $this->warn("  Payment #{$payment->id}: no checkout_request_id, skipping");
                    continue;
                }

                $queryResult = $daraja->stkQuery($checkoutRequestId);

                if (! $queryResult['success']) {
                    $this->warn("  Payment #{$payment->id}: query failed - " . ($queryResult['result_desc'] ?? 'unknown'));
                    $errors++;
                    continue;
                }

                $resultCode = $queryResult['result_code'];

                if ($resultCode === '0') {
                    // Completed
                    if (!$dryRun) {
                        $receipt = $payment->mpesa_receipt ?? 'RECON-' . $payment->id;
                        $completionService->complete($payment, $receipt, now(), [
                            'source' => 'reconcile',
                            'query_result' => $queryResult['response'],
                        ]);
                    }
                    $completed++;
                    $this->info("  Payment #{$payment->id}: COMPLETED (dry-run: {$dryRun})");
                } else {
                    // Still pending or failed — leave as-is, Daraja will resolve
                    $reconciled++;
                    $this->info("  Payment #{$payment->id}: still pending (result_code={$resultCode})");
                }
            } catch (\Throwable $e) {
                $errors++;
                $this->error("  Payment #{$payment->id}: error - " . $e->getMessage());
                \Illuminate\Support\Facades\Log::warning('M-Pesa reconcile error', [
                    'payment_id' => $payment->id,
                    'exception' => get_class($e),
                    'message' => $e->getMessage(),
                ]);
            }
        }

        $this->newLine();
        $this->info("Reconcile complete: {$reconciled} checked, {$completed} completed, {$failed} failed, {$expired} expired, {$errors} errors.");
        if ($dryRun) {
            $this->info("Dry run — no changes were made.");
        }

        return 0;
    }
}
