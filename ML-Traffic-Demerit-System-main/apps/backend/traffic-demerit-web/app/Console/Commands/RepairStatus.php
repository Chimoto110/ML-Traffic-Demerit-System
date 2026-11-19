<?php

namespace App\Console\Commands;

use App\Models\DriverProfile;
use App\Models\SanctionAction;
use App\Models\ReviewLog;
use App\Services\DriverStatusSynchronizer;
use App\Services\RiskInferenceService;
use App\Services\SanctionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RepairStatus extends Command
{
    protected $signature = 'drivers:repair-status {--apply}';
    protected $description = 'Repair driver status: list drivers locked without active sanction; with --apply, set them active and create suspensions for score >= 0.70';

    public function handle(): int
    {
        $apply = $this->option('apply');

        $riskService = app(RiskInferenceService::class);
        $sanctionService = app(SanctionService::class);
        $statusSync = app(DriverStatusSynchronizer::class);

        // Find drivers locked without active sanction
        $lockedNoSanction = DriverProfile::where('status', '!=', 'active')
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('sanction_actions as sa')
                    ->whereColumn('sa.driver_id', 'driver_profiles.id')
                    ->where('sa.status', 'active');
            })
            ->get();

        $this->info("=== Driver Status Repair ===");
        $this->info("Drivers locked without active sanction: " . $lockedNoSanction->count());

        if ($lockedNoSanction->isNotEmpty()) {
            $this->newLine();
            $this->info("Driver ID | Name | Status | Risk Score");
            $this->info("----------|------|--------|------------");

            foreach ($lockedNoSanction as $driver) {
                $assessment = $riskService->assessDriver($driver->fresh());
                $finalScore = (float) $assessment->risk_score;

                $this->info(sprintf(
                    "  #%-8d | %-20s | %-10s | %.4f",
                    $driver->id,
                    substr($driver->full_name, 0, 18),
                    $driver->status,
                    $finalScore
                ));
            }
        }

        if (!$apply) {
            $this->newLine();
            $this->info("Run with --apply to fix unlocked drivers and create missing suspensions.");
            return 0;
        }

        // --apply: set unlocked drivers to active
        $fixedCount = 0;
        foreach ($lockedNoSanction as $driver) {
            $driver->update(['status' => 'active']);
            $fixedCount++;
        }

        $this->newLine();
        $this->info("Set $fixedCount locked drivers (without active sanction) to active.");

        // --apply: create suspensions for drivers with score >= 0.70 and no active suspension
        $sanctionedCount = 0;
        $drivers = DriverProfile::where('status', 'active')->get();

        foreach ($drivers as $driver) {
            try {
                $driver->refresh();
                $assessment = $riskService->assessDriver($driver->fresh());
                $finalScore = (float) $assessment->risk_score;

                if ($finalScore >= 0.70) {
                    try {
                        $existingSuspension = $driver->sanctionActions()
                            ->where('action_type', 'profile_suspension')
                            ->where('status', 'active')
                            ->exists();

                        if (!$existingSuspension) {
                            $sanction = $sanctionService->evaluate($driver->fresh(), $assessment);
                            $statusSync->sync($driver->fresh());
                            $sanctionedCount++;
                        }
                    } catch (\Throwable $e) {
                        \Illuminate\Support\Facades\Log::warning('Repair status failed for driver #' . $driver->id, [
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('Repair status failed for driver #' . $driver->id, [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->info("Created $sanctionedCount new suspensions for drivers with score >= 0.70.");

        return 0;
    }
}
