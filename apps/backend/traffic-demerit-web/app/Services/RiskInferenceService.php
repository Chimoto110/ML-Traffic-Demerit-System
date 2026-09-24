<?php

namespace App\Services;

use App\Models\DriverProfile;
use App\Models\RiskPrediction;
use App\Models\RiskAssessment;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

/**
 * RiskInferenceService
 * ---------------------
 * The ONLY place in the Laravel app that talks to the Python ML
 * microservice. Everything else (controllers) calls this class, never
 * Http:: directly — keeps the ML integration swappable and testable.
 */
class RiskInferenceService
{
    protected string $baseUrl;
    protected string $fallbackModelVersion;

    public function __construct()
    {
        $this->baseUrl = config('services.ml_service.url', 'http://127.0.0.1:8001');
        $this->fallbackModelVersion = config('risk.model_version', 'rf_v1');
    }

    /**
     * Builds the feature payload for a driver from their current ledger
     * + violation history, calls the ML service, and persists a
     * RiskAssessment row. Returns the RiskAssessment model.
     */
    public function assessDriver(DriverProfile $driver, array $latestViolationContext = []): RiskAssessment
    {
        $features = $this->buildFeatureSnapshot($driver, $latestViolationContext);

        try {
            $response = Http::timeout(5)->post("{$this->baseUrl}/predict", $features);
        } catch (\Throwable $e) {
            Log::error('ML service unreachable: ' . $e->getMessage());
            throw new \RuntimeException('Risk inference service is unavailable.');
        }

        if (! $response->successful()) {
            Log::error('ML service returned error: ' . $response->body());
            throw new \RuntimeException('Risk inference failed: ' . $response->body());
        }

        $data = $response->json();

        $riskScore = (float) ($data['driver_risk_score'] ?? 0);
        $riskClass = (string) ($data['risk_class'] ?? 'low');

        // Backward compatibility for older model responses.
        if ($riskClass === 'medium') {
            $riskClass = 'moderate';
        }

        $explanationFactors = $data['explanation_top_factors'] ?? [];

        $confidence = isset($data['confidence_level'])
            ? (float) $data['confidence_level']
            : min(1, max(0, abs(($riskScore * 2) - 1)));

        $assessment = DB::transaction(function () use ($driver, $riskScore, $riskClass, $data, $features, $explanationFactors, $confidence) {
            $assessment = RiskAssessment::create([
                'driver_id' => $driver->id,
                'risk_score' => $riskScore,
                'risk_class' => $riskClass,
                'model_version' => (string) ($data['model_version'] ?? $this->fallbackModelVersion),
                'feature_snapshot' => [
                    'feature_vector' => $features,
                    'engine' => (string) ($data['engine'] ?? 'RandomForestClassifier'),
                    'explanation_top_factors' => $explanationFactors,
                    'inference_latency_ms' => isset($data['inference_latency_ms']) ? (float) $data['inference_latency_ms'] : null,
                ],
                'generated_at' => now(),
            ]);

            $prediction = RiskPrediction::create([
                'driver_id' => $driver->id,
                'risk_score' => $riskScore,
                'risk_class' => $riskClass,
                'model_version' => (string) ($data['model_version'] ?? $this->fallbackModelVersion),
                'confidence_level' => round($confidence, 4),
                'predicted_at' => now(),
            ]);

            $assessment->update([
                'risk_prediction_id' => $prediction->id,
            ]);

            return $assessment;
        });

        return $assessment->fresh();
    }

    protected function buildFeatureSnapshot(DriverProfile $driver, array $latestViolationContext): array
    {
        $currentViolation = $driver->violations()->latest('occurred_at')->first();
        $lastViolation = $driver->violations()->latest('occurred_at')->skip(1)->first();

        $daysSinceLastOffense = $lastViolation && $currentViolation
            ? $lastViolation->occurred_at->diffInDays($currentViolation->occurred_at)
            : 365;

        $paymentDelayDays = $driver->violations()
            ->whereHas('payment', fn ($query) => $query->whereNotNull('paid_at'))
            ->with('payment')
            ->get()
            ->avg(function ($violation) {
                if (! $violation->payment || ! $violation->payment->paid_at) {
                    return 0;
                }

                return $violation->occurred_at->diffInDays($violation->payment->paid_at);
            }) ?? 0;

        return [
            // Deliberately excludes demographics: no gender, region, occupation.
            'days_since_last_offense' => $daysSinceLastOffense,
            'speed_recorded' => $latestViolationContext['speed_recorded']
                ?? $currentViolation->speed_recorded
                ?? 0,
            'zone_type' => $latestViolationContext['zone_type']
                ?? $currentViolation->zone_type
                ?? 'urban',
            'weather_conditions' => $latestViolationContext['weather_conditions']
                ?? $currentViolation->weather_conditions
                ?? 'clear',
            'cumulative_active_demerit_points' => $driver->currentBalance(),
            'past_fine_payment_delays_days' => round((float) $paymentDelayDays, 2),
        ];
    }
}
