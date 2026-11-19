<?php

use App\Models\DriverProfile;
use App\Models\RiskAssessment;
use App\Models\RiskPrediction;
use App\Models\SanctionAction;
use App\Models\Appeal;
use App\Models\ReviewLog;
use App\Services\RiskInferenceService;
use App\Services\SanctionService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Schema;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('risk:batch-infer {--driver-id=}', function (
    RiskInferenceService $riskService,
    SanctionService $sanctionService
) {
    $driverId = $this->option('driver-id');

    $query = DriverProfile::query();
    if ($driverId) {
        $query->whereKey($driverId);
    }

    $processed = 0;
    $sanctioned = 0;

    $query->chunkById(50, function ($drivers) use ($riskService, $sanctionService, &$processed, &$sanctioned) {
        foreach ($drivers as $driver) {
            try {
                $assessment = $riskService->assessDriver($driver);
                $sanction = $sanctionService->evaluate($driver->fresh(), $assessment);

                $processed++;
                if ($sanction) {
                    $sanctioned++;
                }
            } catch (\Throwable $e) {
                Log::warning('Batch risk inference failed', [
                    'driver_id' => $driver->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    });

    $this->info("Batch risk inference complete: processed={$processed}, sanctions={$sanctioned}");
})->purpose('Periodically rescore drivers and apply automated sanctions based on Random Forest risk.');

Schedule::command('risk:batch-infer')->hourly();

Artisan::command('system:readiness-check', function () {
    $startedAt = microtime(true);

    $dbOk = false;
    $dbError = null;
    try {
        DB::connection()->getPdo();
        $dbOk = true;
    } catch (\Throwable $e) {
        $dbError = $e->getMessage();
    }

    $mlHealthy = false;
    $mlStatusCode = null;
    $mlError = null;
    $mlLatencyMs = 0.0;
    $mlStartedAt = microtime(true);
    try {
        $health = Http::timeout(4)->get(config('services.ml_service.url', 'http://127.0.0.1:8001') . '/health');
        $mlHealthy = $health->successful();
        $mlStatusCode = $health->status();
        if (! $mlHealthy) {
            $mlError = $health->body();
        }
    } catch (\Throwable $e) {
        $mlError = $e->getMessage();
    }
    $mlLatencyMs = round((microtime(true) - $mlStartedAt) * 1000, 2);

    $latestPredictionAt = RiskPrediction::max('predicted_at');
    $batchLagMinutes = $latestPredictionAt
        ? max(0, (int) now()->diffInMinutes(\Illuminate\Support\Carbon::parse($latestPredictionAt), false))
        : null;

    $sanctionTotals = SanctionAction::query()
        ->selectRaw('COUNT(*) as total')
        ->selectRaw("SUM(CASE WHEN webhook_status = 'failed' THEN 1 ELSE 0 END) as failed")
        ->first();

    $totalSanctions = (int) ($sanctionTotals?->total ?? 0);
    $failedSanctions = (int) ($sanctionTotals?->failed ?? 0);
    $webhookFailureRate = $totalSanctions > 0 ? $failedSanctions / $totalSanctions : 0;

    $latencies = RiskAssessment::query()
        ->whereNotNull('feature_snapshot')
        ->get()
        ->map(fn (RiskAssessment $assessment) => (float) ($assessment->feature_snapshot['inference_latency_ms'] ?? 0))
        ->filter(fn (float $value) => $value > 0)
        ->sort()
        ->values();

    $p50 = 0.0;
    $p95 = 0.0;
    if ($latencies->count() > 0) {
        $p50Index = max(0, min($latencies->count() - 1, (int) ceil(0.50 * $latencies->count()) - 1));
        $p95Index = max(0, min($latencies->count() - 1, (int) ceil(0.95 * $latencies->count()) - 1));
        $p50 = round((float) $latencies[$p50Index], 3);
        $p95 = round((float) $latencies[$p95Index], 3);
    }

    $report = [
        'status' => $dbOk && $mlHealthy ? 'healthy' : 'degraded',
        'generated_at' => now()->toIso8601String(),
        'environment' => app()->environment(),
        'database' => [
            'connection' => config('database.default'),
            'ok' => $dbOk,
            'error' => $dbError,
        ],
        'ml_service' => [
            'url' => config('services.ml_service.url', 'http://127.0.0.1:8001'),
            'healthy' => $mlHealthy,
            'status_code' => $mlStatusCode,
            'health_latency_ms' => $mlLatencyMs,
            'error' => $mlError,
        ],
        'scheduler' => [
            'latest_prediction_at' => $latestPredictionAt,
            'batch_lag_minutes' => $batchLagMinutes,
            'stale_threshold_minutes' => 120,
        ],
        'reliability' => [
            'queue_backlog_jobs' => Schema::hasTable('jobs') ? (int) DB::table('jobs')->count() : null,
            'failed_jobs' => Schema::hasTable('failed_jobs') ? (int) DB::table('failed_jobs')->count() : null,
            'pending_appeals' => (int) Appeal::where('status', 'pending')->count(),
            'pending_review_logs' => (int) ReviewLog::whereNull('decision')->count(),
            'sanction_webhook_failure_rate' => round((float) $webhookFailureRate, 4),
        ],
        'performance' => [
            'inference_latency_p50_ms' => $p50,
            'inference_latency_p95_ms' => $p95,
            'sample_size' => $latencies->count(),
        ],
        'postgresql_ready' => [
            'config_available' => array_key_exists('pgsql', config('database.connections', [])),
            'default_connection' => config('database.default'),
        ],
        'report_latency_ms' => round((microtime(true) - $startedAt) * 1000, 2),
    ];

    $this->line(json_encode($report, JSON_PRETTY_PRINT));
})->purpose('Run production-readiness diagnostics (DB, ML health, scheduler freshness, reliability and latency metrics).');

Artisan::command('drivers:sync-pexels-photos {--limit=30} {--force}', function () {
    $apiKey = env('PEXELS_API_KEY');
    if (! $apiKey) {
        $this->error('Missing PEXELS_API_KEY in .env');
        return 1;
    }

    $limit = max(1, (int) $this->option('limit'));
    $force = (bool) $this->option('force');

    $queries = [
        'Kenyan person portrait Nairobi',
        'Kenya commuter portrait',
        'Kenyan adult portrait natural light',
        'Kenyan people street portrait',
    ];

    $photos = collect();
    foreach ($queries as $query) {
        $response = Http::withHeaders([
            'Authorization' => $apiKey,
        ])->timeout(20)->get('https://api.pexels.com/v1/search', [
            'query' => $query,
            'orientation' => 'portrait',
            'per_page' => 40,
            'page' => 1,
        ]);

        if (! $response->successful()) {
            $this->warn("Pexels query failed for: {$query}");
            continue;
        }

        $result = collect($response->json('photos', []));
        $photos = $photos->merge($result);
    }

    $photos = $photos->unique('id')->values();
    if ($photos->isEmpty()) {
        $this->error('No photos returned by Pexels. Check API key and internet connectivity.');
        return 1;
    }

    $query = DriverProfile::query()->orderBy('id');
    if (! $force) {
        $query->whereNull('profile_photo_path');
    }

    $drivers = $query->limit($limit)->get();
    if ($drivers->isEmpty()) {
        $this->info('No drivers found to update.');
        return 0;
    }

    $dir = public_path('driver-photos');
    if (! File::exists($dir)) {
        File::makeDirectory($dir, 0755, true);
    }

    $updated = 0;
    foreach ($drivers as $index => $driver) {
        $photo = $photos[$index % $photos->count()];
        $source = $photo['src']['large2x'] ?? $photo['src']['large'] ?? $photo['src']['medium'] ?? null;

        if (! $source) {
            continue;
        }

        $download = Http::timeout(30)->get($source);
        if (! $download->successful()) {
            continue;
        }

        $ext = pathinfo(parse_url($source, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION);
        if (! $ext) {
            $ext = 'jpg';
        }

        $filename = "driver-{$driver->id}.{$ext}";
        File::put($dir . DIRECTORY_SEPARATOR . $filename, $download->body());

        $driver->update([
            'profile_photo_path' => 'driver-photos/' . $filename,
        ]);

        $updated++;
    }

    $this->info("Synced {$updated} driver profile photos from Pexels.");
    $this->warn('Manual review recommended: Pexels query tags are used, but nationality cannot be guaranteed automatically.');

    return 0;
})->purpose('Download Kenya-tagged portraits from Pexels and assign them to driver profiles.');
