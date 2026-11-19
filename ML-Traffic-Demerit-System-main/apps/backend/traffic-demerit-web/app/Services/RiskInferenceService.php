<?php

namespace App\Services;

use App\Models\DriverProfile;
use App\Models\RiskPrediction;
use App\Models\RiskAssessment;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Cache;

/**
 * RiskInferenceService
 * ---------------------
 * The ONLY place in the Laravel app that talks to the Python ML
 * microservice. Everything else (controllers) calls this class, never
 * Http:: directly — keeps the ML integration swappable and testable.
 *
 * Risk scoring pipeline:
 *   1. DriverRiskFeatureBuilder builds the per-driver feature vector from
 *      ALL of the driver's violations (not just the latest).
 *   2. Expected columns are read from ML /v1/model-info (cached).
 *   3. ML /predict is called with ONLY the expected columns — no silent
 *      defaults, fails loudly on missing/extra columns.
 *   4. Points floor is applied: final_score = max(model_score, floor).
 *   5. heuristic_v2 fallback (from heuristic_v2.json) when ML is down
 *      or returns 503.
 *   6. Returns a stable assessment with raw_score, points_floor,
 *      final_score, winner in feature_snapshot.
 */
class RiskInferenceService
{
    protected string $baseUrl;
    protected string $fallbackModelVersion;
    protected array $expectedFeatures = [];
    protected array $modelMeta = [];
    protected array $heuristicV2 = [];

    public function __construct()
    {
        $this->baseUrl = config('services.ml_service.url', 'http://127.0.0.1:8001');
        $this->fallbackModelVersion = config('risk.model_version', 'rf_v1');
        $this->loadModelMeta();
        $this->loadHeuristicV2();
    }

    /**
     * Fetch and cache model_meta.json from /v1/model-info.
     * Falls back to local model_meta.json file if ML service is unreachable.
     */
    protected function loadModelMeta(): void
    {
        // Try cache first (5 minute TTL)
        $cached = Cache::get('ml_model_meta');
        if ($cached) {
            $this->modelMeta = $cached;
            $this->expectedFeatures = $cached['feature_cols'] ?? [];
            return;
        }

        try {
            $response = Http::timeout(5)->get("{$this->baseUrl}/v1/model-info");
            if ($response->successful()) {
                $meta = $response->json();
                $this->modelMeta = $meta;
                $this->expectedFeatures = $meta['feature_cols'] ?? [];
                Cache::put('ml_model_meta', $meta, now()->addMinutes(5));
                Log::info('ML model_meta loaded from service', [
                    'version' => $meta['model_version'] ?? 'unknown',
                    'features' => count($this->expectedFeatures),
                ]);
                return;
            }
        } catch (\Throwable $e) {
            Log::warning('Could not fetch /v1/model-info from ML service: ' . $e->getMessage());
        }

        // Fallback: read local model_meta.json file
        $metaPath = app()->basePath('ml_service/model/model_meta.json');
        if (file_exists($metaPath)) {
            $meta = json_decode(file_get_contents($metaPath), true);
            if ($meta) {
                $this->modelMeta = $meta;
                $this->expectedFeatures = $meta['feature_cols'] ?? [];
                Log::info('ML model_meta loaded from local file', [
                    'version' => $meta['model_version'] ?? 'unknown',
                    'features' => count($this->expectedFeatures),
                ]);
                return;
            }
        }

        Log::warning('No model_meta available — will send all builder features to ML service');
    }

    /**
     * Load heuristic_v2.json coefficients for ML-down fallback.
     */
    protected function loadHeuristicV2(): void
    {
        $path = app()->basePath('ml_service/model/heuristic_v2.json');
        if (file_exists($path)) {
            $this->heuristicV2 = json_decode(file_get_contents($path), true) ?? [];
            if (!empty($this->heuristicV2)) {
                Log::info('heuristic_v2 loaded', [
                    'intercept' => $this->heuristicV2['intercept'] ?? 0,
                    'coefficients' => count($this->heuristicV2['coefficients'] ?? []),
                ]);
            }
        } else {
            Log::warning('heuristic_v2.json not found — using built-in heuristic fallback');
        }
    }

    /**
     * Builds the feature vector for a driver, calls the ML service,
     * applies the points floor, persists a RiskAssessment row, and
     * returns the RiskAssessment model with an enriched
     * feature_snapshot containing raw_score, points_floor, final_score,
     * winner and class_floor_reason.
     */
    public function assessDriver(DriverProfile $driver, array $latestViolationContext = []): RiskAssessment
    {
        $featureBuilder = new DriverRiskFeatureBuilder();
        $allFeatures = $featureBuilder->build($driver);

        // Extract only the columns expected by the model, in model order.
        $modelFeatures = [];
        foreach ($this->expectedFeatures as $col) {
            if (!array_key_exists($col, $allFeatures)) {
                throw new \RuntimeException(
                    "ML model expects feature '{$col}' but DriverRiskFeatureBuilder does not produce it. "
                    . "Available: " . implode(', ', array_keys($allFeatures))
                );
            }
            $modelFeatures[$col] = $allFeatures[$col];
        }

        // Check for extra columns in builder that model doesn't expect
        // (just log, don't fail — builder may compute more than model needs)
        $extra = array_diff(array_keys($allFeatures), $this->expectedFeatures);
        if (!empty($extra)) {
            Log::debug('Builder features not used by model: ' . implode(', ', $extra));
        }

        try {
            $response = Http::timeout(5)->post("{$this->baseUrl}/predict", $modelFeatures);
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            // ML service is DOWN. Use deterministic heuristic_v2 fallback.
            Log::warning('ML service unreachable - using heuristic_v2 fallback: ' . $e->getMessage());
            return $this->heuristicAssessment($driver, $allFeatures, $modelFeatures);
        } catch (\Throwable $e) {
            Log::error('ML service error: ' . $e->getMessage());
            throw new \RuntimeException('Risk inference service is unavailable.');
        }

        if ($response->status() === 503) {
            Log::warning('ML service returned 503 - using heuristic_v2 fallback');
            return $this->heuristicAssessment($driver, $allFeatures, $modelFeatures);
        }

        if (!$response->successful()) {
            Log::error('ML service returned error: ' . $response->body());
            throw new \RuntimeException('Risk inference failed: ' . $response->body());
        }

        $data = $response->json();

        // New response format: { score, raw_score, risk_class, model_version, ... }
        $rawScore = (float) ($data['score'] ?? $data['driver_risk_score'] ?? 0);
        $riskClass = (string) ($data['risk_class'] ?? $data['class'] ?? 'low');

        // Backward compatibility for older model responses.
        if ($riskClass === 'medium') {
            $riskClass = 'moderate';
        }

        $contributions = $data['contributions'] ?? [];

        // --- Apply points floor ---
        $pointsFloor = $this->computePointsFloor((int) $allFeatures['cumulative_active_demerit_points']);
        $finalScore = max($rawScore, $pointsFloor['score']);
        $finalScore = round(min(0.99, max(0.0, $finalScore)), 4);

        $winner = $rawScore >= $pointsFloor['score'] ? 'model' : 'points_floor';
        $floorApplied = $rawScore < $pointsFloor['score'];
        $classFloorReason = $floorApplied
            ? "Points floor: balance {$allFeatures['cumulative_active_demerit_points']} >= {$pointsFloor['points']} (score {$pointsFloor['score']})"
            : null;

        $finalRiskClass = $this->classify($finalScore);

        $modelVersion = (string) ($data['model_version'] ?? $this->fallbackModelVersion);

        $assessment = DB::transaction(function () use (
            $driver, $rawScore, $finalScore, $finalRiskClass, $modelFeatures,
            $contributions, $pointsFloor, $winner, $floorApplied, $classFloorReason, $modelVersion
        ) {
            $assessment = RiskAssessment::create([
                'driver_id' => $driver->id,
                'risk_score' => $finalScore,
                'risk_class' => $finalRiskClass,
                'model_version' => $modelVersion,
                'feature_snapshot' => [
                    'feature_vector' => $modelFeatures,
                    'engine' => 'CalibratedClassifierCV',
                    'explanation_top_factors' => $contributions,
                    'inference_latency_ms' => isset($data['inference_latency_ms']) ? (float) $data['inference_latency_ms'] : null,
                    'raw_score' => round($rawScore, 4),
                    'points_floor' => $pointsFloor['score'],
                    'final_score' => $finalScore,
                    'winner' => $winner,
                    'class_floor_reason' => $classFloorReason,
                ],
                'generated_at' => now(),
            ]);

            $prediction = RiskPrediction::create([
                'driver_id' => $driver->id,
                'risk_score' => $finalScore,
                'risk_class' => $finalRiskClass,
                'model_version' => $modelVersion,
                'confidence_level' => round($data['confidence_level'] ?? $rawScore, 4),
                'predicted_at' => now(),
            ]);

            $assessment->update([
                'risk_prediction_id' => $prediction->id,
            ]);

            return $assessment;
        });

        return $assessment->fresh();
    }

    /**
     * Deterministic heuristic_v2 risk scoring used when the Python ML
     * service is unreachable or returns 503. Uses coefficients from
     * heuristic_v2.json (exported by the Colab notebook).
     *
     * Transforms days_since_last_offense and mean_gap_between_offences
     * into (365 - value) so more recent = higher risk.
     * All coefficients are non-negative.
     */
    protected function heuristicAssessment(
        DriverProfile $driver,
        array $allFeatures,
        array $modelFeatures
    ): RiskAssessment {
        $intercept = (float) ($this->heuristicV2['intercept'] ?? 0.05);
        $coefficients = $this->heuristicV2['coefficients'] ?? [];
        $featureMeans = $this->heuristicV2['feature_means'] ?? [];
        $featureScales = $this->heuristicV2['feature_scales'] ?? [];

        $score = $intercept;
        $contributions = [];

        foreach ($coefficients as $feature => $coef) {
            $coef = (float) $coef;
            // Clamp coefficient >= 0 (heuristic_v2 guarantees non-negative)
            $coef = max(0.0, $coef);

            $value = (float) ($allFeatures[$feature] ?? 0.0);

            // Transform: days_since_last_offense and mean_gap use (365 - value)
            // so that more recent offences = higher risk.
            if ($feature === 'days_since_last_offense' || $feature === 'mean_gap_between_offences') {
                $value = 365.0 - $value;
            }

            // Clamp value >= 0
            $value = max(0.0, $value);

            // Standardize using mean/scale if available
            if (isset($featureMeans[$feature], $featureScales[$feature]) && (float) $featureScales[$feature] > 0) {
                $value = ($value - (float) $featureMeans[$feature]) / (float) $featureScales[$feature];
                $value = max(0.0, $value); // Clamp standardized value >= 0
            }

            $contribution = $coef * $value;
            $score += $contribution;

            $contributions[] = [
                'feature' => $feature,
                'contribution' => round($contribution, 6),
            ];
        }

        $score = round(min(0.99, max(0.0, $score)), 4);

        $riskClass = $this->classify($score);
        $confidence = 0.55;
        $modelVersion = 'heuristic_v2_ml_down';

        // Apply points floor to heuristic too.
        $pointsFloor = $this->computePointsFloor((int) ($allFeatures['cumulative_active_demerit_points'] ?? 0));
        $finalScore = max($score, $pointsFloor['score']);
        $finalScore = round(min(0.99, max(0.0, $finalScore)), 4);
        $winner = $score >= $pointsFloor['score'] ? 'heuristic' : 'points_floor';
        $floorApplied = $score < $pointsFloor['score'];
        $classFloorReason = $floorApplied
            ? "Points floor: balance {$allFeatures['cumulative_active_demerit_points']} >= {$pointsFloor['points']} (score {$pointsFloor['score']})"
            : null;
        $finalRiskClass = $this->classify($finalScore);

        return DB::transaction(function () use (
            $driver, $score, $finalScore, $finalRiskClass, $modelVersion,
            $confidence, $allFeatures, $pointsFloor, $winner, $floorApplied, $classFloorReason, $contributions
        ) {
            $assessment = RiskAssessment::create([
                'driver_id' => $driver->id,
                'risk_score' => $finalScore,
                'risk_class' => $finalRiskClass,
                'model_version' => $modelVersion,
                'feature_snapshot' => [
                    'feature_vector' => $allFeatures,
                    'engine' => 'HeuristicV2 (ML service unreachable)',
                    'explanation_top_factors' => $contributions,
                    'inference_latency_ms' => 0,
                    'raw_score' => round($score, 4),
                    'points_floor' => $pointsFloor['score'],
                    'final_score' => $finalScore,
                    'winner' => $winner,
                    'class_floor_reason' => $classFloorReason,
                ],
                'generated_at' => now(),
            ]);

            $prediction = RiskPrediction::create([
                'driver_id' => $driver->id,
                'risk_score' => $finalScore,
                'risk_class' => $finalRiskClass,
                'model_version' => $modelVersion,
                'confidence_level' => $confidence,
                'predicted_at' => now(),
            ]);

            $assessment->update(['risk_prediction_id' => $prediction->id]);

            return $assessment;
        });
    }

    /**
     * Compute the points floor score from the driver's cumulative
     * active demerit points. Uses anchors from model_meta.json if
     * available, otherwise from config/risk.php. Config wins if they
     * differ (a warning is logged).
     */
    protected function computePointsFloor(int $cumulativePoints): array
    {
        // Try anchors from model_meta.json first
        $metaAnchors = $this->modelMeta['points_floor_anchors'] ?? null;
        $anchors = config('risk.points_floor', [
            'warning' => ['points' => 10, 'score' => 0.40],
            'restriction' => ['points' => 20, 'score' => 0.55],
            'suspension' => ['points' => 30, 'score' => 0.70],
        ]);
        $maxScore = (float) config('risk.points_floor_max', 0.99);

        // If meta anchors exist and differ from config, log warning and use config (config wins)
        if ($metaAnchors) {
            foreach ($metaAnchors as $key => $metaAnchor) {
                if (isset($anchors[$key])) {
                    $metaPoints = (int) $metaAnchor['points'];
                    $metaScore = (float) $metaAnchor['score'];
                    $cfgPoints = (int) $anchors[$key]['points'];
                    $cfgScore = (float) $anchors[$key]['score'];
                    if ($metaPoints !== $cfgPoints || $metaScore !== $cfgScore) {
                        Log::warning("Points floor anchor '{$key}' differs: meta=({$metaPoints},{$metaScore}) config=({$cfgPoints},{$cfgScore}). Using config.");
                    }
                }
            }
        }

        $points = (int) $cumulativePoints;

        // Below first anchor -> floor is 0.0.
        $warningPoints = (int) $anchors['warning']['points'];
        if ($points < $warningPoints) {
            return ['score' => 0.0, 'points' => 0];
        }

        // At or above last anchor -> floor is the max score.
        $suspensionPoints = (int) $anchors['suspension']['points'];
        if ($points >= $suspensionPoints) {
            return ['score' => (float) $anchors['suspension']['score'], 'points' => $suspensionPoints];
        }

        // Linear interpolation between warning and suspension anchors.
        $p1 = (float) $anchors['warning']['points'];
        $s1 = (float) $anchors['warning']['score'];
        $p2 = (float) $anchors['suspension']['points'];
        $s2 = (float) $anchors['suspension']['score'];

        $score = $s1 + (($points - $p1) / ($p2 - $p1)) * ($s2 - $s1);
        $score = round(min($maxScore, max(0.0, $score)), 4);

        return ['score' => $score, 'points' => $points];
    }

    /**
     * Classify a score into low/moderate/high using the config cutoffs.
     */
    protected function classify(float $score): string
    {
        $warning = (float) config('risk.warning_risk_threshold', 0.40);
        $training = (float) config('risk.training_risk_threshold', 0.55);
        $lock = (float) config('risk.sanction_risk_threshold', 0.7);

        if ($score >= $lock) {
            return 'high';
        }
        if ($score >= $warning) {
            return 'moderate';
        }
        return 'low';
    }

    /**
     * Return the tier label for a given score.
     */
    public function tierForScore(float $score): string
    {
        $tiers = config('risk.tiers', []);

        foreach ($tiers as $tierKey => $tier) {
            $min = (float) ($tier['min'] ?? 0);
            $max = (float) ($tier['max'] ?? 1.0);
            if ($score >= $min && $score < $max) {
                return $tierKey;
            }
        }

        return 'suspended';
    }
}
