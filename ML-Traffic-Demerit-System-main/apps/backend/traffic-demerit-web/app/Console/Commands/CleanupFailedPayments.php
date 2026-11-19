<?php

namespace App\Console\Commands;

use App\Models\Payment;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class CleanupFailedPayments extends Command
{
    protected $signature = 'payments:cleanup-failed {--apply : Soft-delete duplicate failed payments and mark stale pending payments as failed}';
    protected $description = 'Mark stale pending payments older than 15 min as failed. With --apply, soft-delete duplicate failed attempts.';

    public function handle(): int
    {
        $apply = $this->option('apply');
        $staleThreshold = Carbon::now()->subMinutes(15);

        // 1. Mark stale pending payments (older than 15 min) as failed.
        $stalePending = Payment::where('status', 'pending')
            ->where('created_at', '<', $staleThreshold)
            ->get();

        $this->info("Stale pending payments older than 15 minutes: " . $stalePending->count());

        if ($apply) {
            foreach ($stalePending as $payment) {
                $payment->update([
                    'status' => 'failed',
                    'failure_reason' => 'Payment expired: no completion received within 15 minutes.',
                    'result_code' => 'EXPIRED',
                    'result_desc' => 'Payment expired: no completion received within 15 minutes.',
                ]);
            }
            $this->info("Marked " . $stalePending->count() . " stale pending payments as failed.");
        }

        // 2. Find duplicate failed payments for the same violation (keep the latest, soft-delete the rest).
        $duplicateViolations = Payment::where('status', 'failed')
            ->select('violation_id', DB::raw('COUNT(*) as cnt'))
            ->groupBy('violation_id')
            ->having('cnt', '>', 1)
            ->pluck('violation_id');

        $this->info("Violations with duplicate failed payments: " . $duplicateViolations->count());

        if ($apply) {
            $deleted = 0;
            foreach ($duplicateViolations as $violationId) {
                $failedPayments = Payment::where('violation_id', $violationId)
                    ->where('status', 'failed')
                    ->orderBy('created_at', 'desc')
                    ->get();

                // Keep the latest failed payment, soft-delete the rest.
                $latest = $failedPayments->first();
                $duplicates = $failedPayments->slice(1);

                foreach ($duplicates as $dup) {
                    $dup->delete();
                    $deleted++;
                }
            }
            $this->info("Soft-deleted {$deleted} duplicate failed payment(s).");
        }

        $this->info("Cleanup complete." . ($apply ? " Changes applied." : " Dry run — use --apply to apply changes."));

        return 0;
    }
}
