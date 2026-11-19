<?php

namespace App\Console\Commands;

use App\Models\DriverProfile;
use App\Services\DriverStatusSynchronizer;
use App\Services\RiskInferenceService;
use App\Services\SanctionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class RecomputeAll extends Command
{
    protected $signature = 'risk:recompute-all {--driver-id=}';
    protected $description = 'Re-score all drivers: writes fresh risk_assessments + risk_predictions';

    public function handle(): int
    {
        $driverId = $this->option('driver-id');

        $query = DriverProfile::query();
        if ($driverId) {
            $query->whereKey($driverId);
        }

        $riskService = app(RiskInferenceService::class);
        $sanctionService = app(SanctionService::class);
        $statusSync = app(DriverStatusSynchronizer::class);

        $processed = 0;
        $sanctioned = 0;
        $errors = 0;

        $query->chunkById(50, function ($drivers) use ($riskService, $sanctionService, $statusSync, &$processed, &$sanctioned, &$errors) {
            foreach ($drivers as $driver) {
                try {
                    $driver->refresh();
                    $assessment = $riskService->assessDriver($driver->fresh());
                    $sanction = $sanctionService->evaluate($driver->fresh(), $assessment);

                    // Sync driver status based on active sanctions.
                    $statusSync->sync($driver->fresh());

                    $processed++;
                    if ($sanction) {
                        $sanctioned++;
                    }
                } catch (\Throwable $e) {
                    $errors++;
                    Log::warning('Risk recompute failed for driver #' . $driver->id, [
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        });

        $this->info("Risk recompute complete: processed={$processed}, sanctions={$sanctioned}, errors={$errors}");

        return $errors > 0 ? 1 : 0;
    }
}
