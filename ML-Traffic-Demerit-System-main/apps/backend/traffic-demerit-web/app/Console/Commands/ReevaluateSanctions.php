<?php

namespace App\Console\Commands;

use App\Models\DriverProfile;
use App\Services\DriverStatusSynchronizer;
use App\Services\RiskInferenceService;
use App\Services\SanctionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ReevaluateSanctions extends Command
{
    protected $signature = 'sanctions:reevaluate {--driver-id=}';
    protected $description = 'Re-evaluate all drivers for graduated sanctions based on current risk scores';

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

        $this->info("Re-evaluating sanctions for all drivers...");

        $query->chunkById(50, function ($drivers) use ($riskService, $sanctionService, $statusSync, &$processed, &$sanctioned, &$errors) {
            foreach ($drivers as $driver) {
                try {
                    $driver->refresh();
                    $assessment = $riskService->assessDriver($driver->fresh());
                    $sanction = $sanctionService->evaluate($driver->fresh(), $assessment);

                    $statusSync->sync($driver->fresh());

                    $processed++;
                    if ($sanction) {
                        $sanctioned++;
                        $this->info("  Sanctioned driver #{$driver->id} ({$driver->full_name}): score={$assessment->risk_score}, type={$sanction->action_type}");
                    }
                } catch (\Throwable $e) {
                    $errors++;
                    Log::warning('Sanction re-evaluation failed for driver #' . $driver->id, [
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        });

        $this->info("Sanction re-evaluation complete: processed={$processed}, sanctions={$sanctioned}, errors={$errors}");

        return $errors > 0 ? 1 : 0;
    }
}
