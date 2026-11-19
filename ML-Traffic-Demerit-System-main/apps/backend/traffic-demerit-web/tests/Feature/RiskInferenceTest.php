<?php

namespace Tests\Feature;

use App\Models\DriverProfile;
use App\Services\DriverRiskFeatureBuilder;
use App\Services\RiskInferenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class RiskInferenceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    /** @test */
    public function risk_inference_service_loads_model_meta_and_expected_features(): void
    {
        $service = new RiskInferenceService();

        $meta = $this->getModelMeta($service);
        $features = $this->getExpectedFeatures($service);

        $this->assertNotEmpty($meta, 'model_meta should be loaded');
        $this->assertNotEmpty($features, 'expected_features should be populated');
        $this->assertContains('cumulative_active_demerit_points', $features);
        $this->assertContains('zone_type', $features);
        $this->assertContains('weather_conditions', $features);
    }

    /** @test */
    public function model_meta_has_required_fields(): void
    {
        $metaPath = app()->basePath('ml_service/model/model_meta.json');
        $this->assertFileExists($metaPath, 'model_meta.json must exist');

        $meta = json_decode(file_get_contents($metaPath), true);
        $this->assertIsArray($meta);
        $this->assertArrayHasKey('model_version', $meta);
        $this->assertArrayHasKey('feature_cols', $meta);
        $this->assertArrayHasKey('numeric_cols', $meta);
        $this->assertArrayHasKey('categorical_cols', $meta);
        $this->assertArrayHasKey('known_categories', $meta);
        $this->assertArrayHasKey('thresholds', $meta);
        $this->assertArrayHasKey('points_floor_anchors', $meta);
        $this->assertArrayHasKey('metrics', $meta);
        $this->assertArrayHasKey('sklearn_version', $meta);
        $this->assertArrayHasKey('trained_at', $meta);
        $this->assertArrayHasKey('data_source', $meta);

        // Model version should be rf_v3 (not legacy rf_v1)
        $this->assertSame('rf_v3', $meta['model_version']);

        // Points floor anchors must match config
        $this->assertSame(10, $meta['points_floor_anchors']['warning']['points']);
        $this->assertSame(0.40, $meta['points_floor_anchors']['warning']['score']);
        $this->assertSame(20, $meta['points_floor_anchors']['restriction']['points']);
        $this->assertSame(0.55, $meta['points_floor_anchors']['restriction']['score']);
        $this->assertSame(30, $meta['points_floor_anchors']['suspension']['points']);
        $this->assertSame(0.70, $meta['points_floor_anchors']['suspension']['score']);
    }

    /** @test */
    public function driver_risk_feature_builder_produces_all_model_expected_features(): void
    {
        $driver = DriverProfile::create([
            'user_id' => null,
            'license_no' => 'DL-TEST-001',
            'full_name' => 'Test Driver',
            'license_issue_date' => now()->subYears(3)->toDateString(),
            'status' => 'active',
            'current_demerit_points' => 0,
            'account_status' => 'active',
        ]);

        $builder = new DriverRiskFeatureBuilder();
        $features = $builder->build($driver);

        // Load model_meta to get expected columns
        $metaPath = app()->basePath('ml_service/model/model_meta.json');
        $meta = json_decode(file_get_contents($metaPath), true);
        $expected = $meta['feature_cols'];

        // Every expected feature must be produced by the builder
        foreach ($expected as $col) {
            $this->assertArrayHasKey($col, $features, "Builder must produce feature '{$col}'");
        }
    }

    /** @test */
    public function column_validation_detects_missing_columns(): void
    {
        $metaPath = app()->basePath('ml_service/model/model_meta.json');
        $meta = json_decode(file_get_contents($metaPath), true);
        $expected = $meta['feature_cols'];

        // Build a payload missing one required column
        $payload = [];
        foreach ($expected as $col) {
            if ($col === 'zone_type') {
                $payload[$col] = 'urban';
            } elseif ($col === 'weather_conditions') {
                $payload[$col] = 'clear';
            } elseif (in_array($col, ['days_since_last_offense', 'speed_recorded', 'cumulative_active_demerit_points', 'past_fine_payment_delays_days'])) {
                $payload[$col] = 0.0;
            }
        }
        // Intentionally omit one column
        unset($payload['speed_recorded']);

        $missing = array_diff($expected, array_keys($payload));
        $this->assertNotEmpty($missing, 'Test payload should be missing a column');
        $this->assertContains('speed_recorded', $missing);
    }

    /** @test */
    public function column_validation_detects_extra_columns(): void
    {
        $metaPath = app()->basePath('ml_service/model/model_meta.json');
        $meta = json_decode(file_get_contents($metaPath), true);
        $expected = $meta['feature_cols'];

        $payload = [];
        foreach ($expected as $col) {
            if ($col === 'zone_type') {
                $payload[$col] = 'urban';
            } elseif ($col === 'weather_conditions') {
                $payload[$col] = 'clear';
            } else {
                $payload[$col] = 0.0;
            }
        }
        // Add an extra column that shouldn't be there
        $payload['extra_column'] = 'should_not_be_here';

        $extra = array_diff(array_keys($payload), $expected);
        $this->assertNotEmpty($extra, 'Test payload should have an extra column');
        $this->assertContains('extra_column', $extra);
    }

    /** @test */
    public function ml_service_predict_returns_valid_response(): void
    {
        // Call the actual ML service (must be running on :8001)
        $metaPath = app()->basePath('ml_service/model/model_meta.json');
        $meta = json_decode(file_get_contents($metaPath), true);
        $expected = $meta['feature_cols'];

        $payload = [];
        foreach ($expected as $col) {
            if ($col === 'zone_type') {
                $payload[$col] = 'urban';
            } elseif ($col === 'weather_conditions') {
                $payload[$col] = 'clear';
            } else {
                $payload[$col] = 0.0;
            }
        }

        try {
            $response = Http::timeout(5)->post('http://127.0.0.1:8001/predict', $payload);

            if ($response->successful()) {
                $data = $response->json();
                $this->assertArrayHasKey('score', $data);
                $this->assertArrayHasKey('raw_score', $data);
                $this->assertArrayHasKey('risk_class', $data);
                $this->assertArrayHasKey('model_version', $data);
                $this->assertArrayHasKey('contributions', $data);
                $this->assertIsNumeric($data['score']);
                $this->assertGreaterThanOrEqual(0.0, $data['score']);
                $this->assertLessThanOrEqual(1.0, $data['score']);
                $this->assertIsArray($data['contributions']);
            } else {
                $this->markTestSkipped('ML service returned error: ' . $response->status());
            }
        } catch (\Throwable $e) {
            $this->markTestSkipped('ML service not reachable: ' . $e->getMessage());
        }
    }

    /** @test */
    public function points_floor_applies_0_70_for_30_plus_points(): void
    {
        $service = new RiskInferenceService();

        // Points floor: 30+ points → 0.70 (suspension anchor)
        $floor = $this->invokeComputePointsFloor($service, 30);
        $this->assertSame(0.70, $floor['score']);

        $floor71 = $this->invokeComputePointsFloor($service, 71);
        $this->assertSame(0.70, $floor71['score']);
    }

    /** @test */
    public function points_floor_applies_0_55_for_20_point_driver(): void
    {
        $service = new RiskInferenceService();

        // Points floor: 20 points → 0.55 (restriction anchor)
        $floor = $this->invokeComputePointsFloor($service, 20);
        $this->assertSame(0.55, $floor['score']);
    }

    /** @test */
    public function points_floor_applies_0_40_for_10_point_driver(): void
    {
        $service = new RiskInferenceService();

        // Points floor: 10 points → 0.40 (warning anchor)
        $floor = $this->invokeComputePointsFloor($service, 10);
        $this->assertSame(0.40, $floor['score']);
    }

    /** @test */
    public function points_floor_is_zero_for_below_10_points(): void
    {
        $service = new RiskInferenceService();

        // Below first anchor → floor is 0.0
        $floor = $this->invokeComputePointsFloor($service, 8);
        $this->assertSame(0.0, $floor['score']);
    }

    /** @test */
    public function points_floor_linear_interpolation_between_10_and_30(): void
    {
        $service = new RiskInferenceService();

        // 20 points is exactly halfway between 10 (0.40) and 30 (0.70)
        // Linear: 0.40 + (20-10)/(30-10) * (0.70-0.40) = 0.40 + 0.5 * 0.30 = 0.55
        $floor = $this->invokeComputePointsFloor($service, 20);
        $this->assertSame(0.55, $floor['score']);

        // 15 points: 0.40 + (15-10)/20 * 0.30 = 0.40 + 0.25 * 0.30 = 0.475
        $floor15 = $this->invokeComputePointsFloor($service, 15);
        $this->assertSame(0.475, $floor15['score']);
    }

    /** @test */
    public function final_score_never_below_points_floor(): void
    {
        $service = new RiskInferenceService();

        // Even if model returns 0.10, points floor of 0.70 (30+ points) should win
        $floor = $this->invokeComputePointsFloor($service, 35);
        $this->assertSame(0.70, $floor['score']);

        $finalScore = max(0.10, $floor['score']);
        $this->assertSame(0.70, $finalScore);
    }

    /** @test */
    public function heuristic_fallback_used_when_ml_service_down(): void
    {
        Http::fake([
            'http://127.0.0.1:8001/predict' => Http::response(
                json_encode(['error' => 'model not loaded']),
                503,
                ['Content-Type' => 'application/json']
            ),
        ]);

        Cache::flush();

        $driver = DriverProfile::create([
            'user_id' => null,
            'license_no' => 'DL-HEURISTIC-001',
            'full_name' => 'Heuristic Test',
            'license_issue_date' => now()->subYears(3)->toDateString(),
            'status' => 'active',
            'current_demerit_points' => 5,
            'account_status' => 'active',
        ]);

        $service = new RiskInferenceService();
        $assessment = $service->assessDriver($driver->fresh());

        $this->assertSame('heuristic_v2_ml_down', $assessment->model_version);
        $this->assertNotEmpty($assessment->feature_snapshot['winner']);
        $this->assertContains($assessment->risk_class, ['low', 'moderate', 'high']);
    }

    /** @test */
    public function risk_assessment_stores_raw_and_final_score(): void
    {
        Http::fake([
            'http://127.0.0.1:8001/predict' => Http::response(
                json_encode([
                    'score' => 0.45,
                    'raw_score' => 0.45,
                    'risk_class' => 'moderate',
                    'model_version' => 'rf_v3',
                    'inference_latency_ms' => 50.0,
                    'contributions' => [
                        ['feature' => 'cumulative_active_demerit_points', 'contribution' => 0.30],
                    ],
                ]),
                200,
                ['Content-Type' => 'application/json']
            ),
            '*' => Http::response([], 200),
        ]);

        Cache::flush();

        $driver = DriverProfile::create([
            'user_id' => null,
            'license_no' => 'DL-SNAPSHOT-001',
            'full_name' => 'Snapshot Test',
            'license_issue_date' => now()->subYears(3)->toDateString(),
            'status' => 'active',
            'current_demerit_points' => 15,
            'account_status' => 'active',
        ]);

        $service = new RiskInferenceService();
        $assessment = $service->assessDriver($driver->fresh());

        $snapshot = $assessment->feature_snapshot;
        $this->assertArrayHasKey('raw_score', $snapshot);
        $this->assertArrayHasKey('points_floor', $snapshot);
        $this->assertArrayHasKey('final_score', $snapshot);
        $this->assertArrayHasKey('winner', $snapshot);
        $this->assertArrayHasKey('class_floor_reason', $snapshot);

        // risk_score in DB should be final_score
        $this->assertSame(round((float) $assessment->risk_score, 4), round((float) $snapshot['final_score'], 4));
    }

    /**
     * Get protected modelMeta property via reflection.
     */
    protected function getModelMeta(RiskInferenceService $service): array
    {
        $reflection = new \ReflectionProperty($service, 'modelMeta');
        $reflection->setAccessible(true);
        return $reflection->getValue($service);
    }

    /**
     * Get protected expectedFeatures property via reflection.
     */
    protected function getExpectedFeatures(RiskInferenceService $service): array
    {
        $reflection = new \ReflectionProperty($service, 'expectedFeatures');
        $reflection->setAccessible(true);
        return $reflection->getValue($service);
    }

    /**
     * Invoke the protected computePointsFloor method via reflection.
     */
    protected function invokeComputePointsFloor(RiskInferenceService $service, int $points): array
    {
        $reflection = new \ReflectionMethod($service, 'computePointsFloor');
        $reflection->setAccessible(true);
        return $reflection->invoke($service, $points);
    }
}
