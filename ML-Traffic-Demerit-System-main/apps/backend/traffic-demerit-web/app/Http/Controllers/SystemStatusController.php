<?php

namespace App\Http\Controllers;

use App\Models\Appeal;
use App\Models\DriverProfile;
use App\Models\Payment;
use App\Models\RiskPrediction;
use App\Models\SanctionAction;
use App\Models\Station;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Violation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class SystemStatusController extends Controller
{
    /**
     * GET /api/system/status
     *
     * Live health snapshot for the admin footer: which database is being
     * served, row counts of the core tables, and whether the Python ML
     * microservice answers. Everything is read straight from MySQL so this
     * endpoint works even while the ML service is down (it just reports
     * ml.status = "down").
     */
    public function __invoke(Request $request): JsonResponse
    {
        $dbConnection = config('database.default');
        $dbName = (string) config("database.connections.{$dbConnection}.database");

        $counts = [
            'users' => $this->safeCount(fn () => User::count()),
            'drivers' => $this->safeCount(fn () => DriverProfile::count()),
            'vehicles' => $this->safeCount(fn () => Vehicle::count()),
            'stations' => $this->safeCount(fn () => Station::count()),
            'violations' => $this->safeCount(fn () => Violation::count()),
            'payments' => $this->safeCount(fn () => Payment::count()),
            'appeals' => $this->safeCount(fn () => Appeal::count()),
            'sanctions' => $this->safeCount(fn () => SanctionAction::count()),
            'risk_predictions' => $this->safeCount(fn () => RiskPrediction::count()),
            'active_tokens' => $this->safeCount(fn () => DB::table('personal_access_tokens')->count()),
            'login_audits' => $this->safeCount(fn () => DB::table('login_audits')->count()),
        ];

        return response()->json([
            'app' => [
                'name' => (string) config('app.name'),
                'env' => (string) config('app.env'),
                'url' => (string) config('app.url'),
                'time' => now()->toIso8601String(),
            ],
            'database' => [
                'connection' => $dbConnection,
                'name' => $dbName,
                'status' => $dbName !== '' ? 'connected' : 'unconfigured',
                'counts' => $counts,
            ],
            'ml' => $this->probeMlService(),
        ]);
    }

    /**
     * Pings the Python ML service. Failure is not an error: the portal is
     * designed to keep serving MySQL-backed data with ML down.
     */
    protected function probeMlService(): array
    {
        $url = rtrim((string) config('services.ml_service.url', 'http://127.0.0.1:8001'), '/');
        $started = microtime(true);

        try {
            $response = Http::timeout(2)->get($url . '/health');
            $latencyMs = round((microtime(true) - $started) * 1000, 1);

            if ($response->successful()) {
                return [
                    'url' => $url,
                    'status' => 'up',
                    'latency_ms' => $latencyMs,
                    'version' => $response->json('model_version') ?? $response->json('version') ?? null,
                    'checked_at' => now()->toIso8601String(),
                ];
            }

            return [
                'url' => $url,
                'status' => 'down',
                'latency_ms' => $latencyMs,
                'error' => 'HTTP ' . $response->status(),
                'checked_at' => now()->toIso8601String(),
            ];
        } catch (\Throwable $e) {
            return [
                'url' => $url,
                'status' => 'down',
                'latency_ms' => round((microtime(true) - $started) * 1000, 1),
                'error' => $e->getMessage(),
                'checked_at' => now()->toIso8601String(),
            ];
        }
    }

    protected function safeCount(callable $counter): ?int
    {
        try {
            return (int) $counter();
        } catch (\Throwable) {
            return null;
        }
    }
}
