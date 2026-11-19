<?php

namespace Tests\Feature;

use App\Models\Appeal;
use App\Models\DemeritLedgerEntry;
use App\Models\DriverProfile;
use App\Models\OfficerAssignment;
use App\Models\RiskAssessment;
use App\Models\RiskPrediction;
use App\Models\Role;
use App\Models\ReviewLog;
use App\Models\SanctionAction;
use App\Models\Station;
use App\Models\SystemNotification;
use App\Models\User;
use App\Models\Violation;
use App\Services\JwtTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class IntegrationReportingHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.key' => 'testing-jwt-signing-key-which-is-at-least-thirty-two-bytes']);
    }

    public function test_officer_cannot_generate_compliance_report(): void
    {
        $officer = $this->createUser('officer', 'officer-rbac@example.com');

        $response = $this->postJson(
            '/api/integration/functions/generateComplianceReport',
            [],
            $this->authHeaders($officer)
        );

        $response->assertStatus(403);
    }

    public function test_admin_can_generate_compliance_report_with_expected_summary(): void
    {
        $admin = $this->createUser('admin', 'admin-report@example.com');
        $motorist = $this->createUser('motorist', 'driver-report@example.com');

        $driver = DriverProfile::create([
            'user_id' => $motorist->id,
            'license_no' => 'REPORT-1001',
            'full_name' => 'Report Driver',
            'license_issue_date' => now()->subYears(4)->toDateString(),
            'status' => 'active',
        ]);

        $violation = Violation::create([
            'driver_id' => $driver->id,
            'offense_type' => 'speeding',
            'speed_recorded' => 102,
            'posted_speed_limit' => 80,
            'zone_type' => 'highway',
            'weather_conditions' => 'clear',
            'location' => 'A104',
            'evidence_reference' => 'REP-1',
            'points_assigned' => 4,
            'status' => 'confirmed',
            'occurred_at' => now(),
        ]);

        DemeritLedgerEntry::create([
            'driver_id' => $driver->id,
            'violation_id' => $violation->id,
            'points_applied' => 4,
            'days_since_last_offence' => 0,
            'running_balance' => 4,
            'reason' => 'violation',
            'reason_code' => 'violation_auto_post',
        ]);

        $prediction = RiskPrediction::create([
            'driver_id' => $driver->id,
            'risk_score' => 0.87,
            'risk_class' => 'high',
            'model_version' => 'rf_test_v1',
            'confidence_level' => 0.95,
            'predicted_at' => now(),
        ]);

        $assessment = RiskAssessment::create([
            'driver_id' => $driver->id,
            'risk_prediction_id' => $prediction->id,
            'risk_score' => 0.87,
            'risk_class' => 'high',
            'model_version' => 'rf_test_v1',
            'feature_snapshot' => ['inference_latency_ms' => 33.2],
            'generated_at' => now(),
        ]);

        SanctionAction::create([
            'driver_id' => $driver->id,
            'risk_assessment_id' => $assessment->id,
            'risk_prediction_id' => $prediction->id,
            'trigger_reason' => 'risk threshold exceeded',
            'action_type' => 'profile_suspension',
            'sanction_type' => 'profile_suspension',
            'status' => 'active',
            'webhook_status' => 'sent',
            'issued_at' => now(),
            'effective_from' => now(),
        ]);

        Appeal::create([
            'driver_id' => $driver->id,
            'violation_id' => $violation->id,
            'reason' => 'Please review this violation.',
            'status' => 'pending',
            'submitted_at' => now(),
        ]);

        SystemNotification::create([
            'recipient_user_id' => $motorist->id,
            'channel' => 'in_app',
            'title' => 'First Notice',
            'message' => 'Pending appeal recorded',
            'status' => 'unread',
            'sent_at' => now(),
        ]);

        SystemNotification::create([
            'recipient_user_id' => $motorist->id,
            'channel' => 'in_app',
            'title' => 'Second Notice',
            'message' => 'Review queue update',
            'status' => 'read',
            'sent_at' => now(),
            'read_at' => now(),
        ]);

        $response = $this->postJson(
            '/api/integration/functions/generateComplianceReport',
            [],
            $this->authHeaders($admin)
        );

        $response
            ->assertStatus(200)
            ->assertJsonPath('data.total_drivers', 1)
            ->assertJsonPath('data.total_violations', 1)
            ->assertJsonPath('data.active_sanctions', 1)
            ->assertJsonPath('data.high_risk_drivers', 1)
            ->assertJsonPath('data.pending_appeals', 1)
            ->assertJsonPath('data.notifications.total', 2)
            ->assertJsonPath('data.notifications.unread', 1)
            ->assertJsonPath('data.notifications.read', 1)
            ->assertJsonStructure([
                'data' => [
                    'violations_by_offence',
                    'violations_by_zone',
                    'violations_by_weather',
                    'sanctions_by_status',
                    'appeals_by_status',
                    'appeal_resolution' => ['avg_resolution_hours', 'max_resolution_hours'],
                    'risk_distribution',
                    'prediction_quality' => ['avg_confidence', 'avg_inference_latency_ms', 'total_predictions'],
                    'monthly_violations',
                    'generated_at',
                ],
            ]);
    }

    public function test_readiness_report_is_degraded_when_ml_health_check_fails(): void
    {
        $admin = $this->createUser('admin', 'admin-ready-degraded@example.com');

        Http::fake([
            'http://127.0.0.1:8001/health' => Http::response(['status' => 'down'], 500),
            '*' => Http::response([], 200),
        ]);

        $response = $this->postJson(
            '/api/integration/functions/generateSystemReadinessReport',
            [],
            $this->authHeaders($admin)
        );

        $response
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'degraded')
            ->assertJsonPath('data.ml_service.healthy', false)
            ->assertJsonPath('data.ml_service.status_code', 500);
    }

    public function test_readiness_report_is_healthy_with_ml_up_and_fresh_predictions(): void
    {
        $admin = $this->createUser('admin', 'admin-ready-healthy@example.com');
        $motorist = $this->createUser('motorist', 'driver-ready@example.com');

        $driver = DriverProfile::create([
            'user_id' => $motorist->id,
            'license_no' => 'READY-1001',
            'full_name' => 'Readiness Driver',
            'license_issue_date' => now()->subYears(3)->toDateString(),
            'status' => 'active',
        ]);

        RiskPrediction::create([
            'driver_id' => $driver->id,
            'risk_score' => 0.32,
            'risk_class' => 'low',
            'model_version' => 'rf_test_v1',
            'confidence_level' => 0.8,
            'predicted_at' => now()->subMinutes(5),
        ]);

        Http::fake([
            'http://127.0.0.1:8001/health' => Http::response(['status' => 'ok'], 200),
            '*' => Http::response([], 200),
        ]);

        $response = $this->postJson(
            '/api/integration/functions/generateSystemReadinessReport',
            [],
            $this->authHeaders($admin)
        );

        $response
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'healthy')
            ->assertJsonPath('data.ml_service.healthy', true)
            ->assertJsonPath('data.reliability.failed_jobs', 0);
    }

    public function test_only_admin_can_update_role_entity(): void
    {
        $admin = $this->createUser('admin', 'admin-role-update@example.com');
        $officer = $this->createUser('officer', 'officer-role-update@example.com');
        $motorist = $this->createUser('motorist', 'motorist-role-update@example.com');

        $role = Role::create([
            'role_name' => 'supervisor',
            'permission_level' => 60,
            'role_description' => 'Regional supervisor role',
        ]);

        $officerAttempt = $this->patchJson(
            '/api/integration/entities/Role/' . $role->id,
            ['permission_level' => 61],
            $this->authHeaders($officer)
        );
        $officerAttempt->assertStatus(403);

        $motoristAttempt = $this->patchJson(
            '/api/integration/entities/Role/' . $role->id,
            ['permission_level' => 62],
            $this->authHeaders($motorist)
        );
        $motoristAttempt->assertStatus(403);

        $adminAttempt = $this->patchJson(
            '/api/integration/entities/Role/' . $role->id,
            ['permission_level' => 65],
            $this->authHeaders($admin)
        );

        $adminAttempt
            ->assertStatus(200)
            ->assertJsonPath('permission_level', 65);
    }

    public function test_only_admin_can_update_review_log_entity(): void
    {
        $admin = $this->createUser('admin', 'admin-review-update@example.com');
        $officer = $this->createUser('officer', 'officer-review-update@example.com');
        $motorist = $this->createUser('motorist', 'motorist-review-update@example.com');
        $driverUser = $this->createUser('motorist', 'driver-review-target@example.com');

        $driver = DriverProfile::create([
            'user_id' => $driverUser->id,
            'license_no' => 'RVW-1001',
            'full_name' => 'Review Driver',
            'license_issue_date' => now()->subYears(3)->toDateString(),
            'status' => 'active',
        ]);

        $station = Station::create([
            'station_name' => 'Nairobi Central',
            'region' => 'Nairobi',
            'physical_address' => 'Central Business District',
            'contact_phone' => '0700000000',
        ]);

        $assignment = OfficerAssignment::create([
            'officer_user_id' => $officer->id,
            'station_id' => $station->id,
            'badge_number' => 'NTSA-123',
            'shift_type' => 'day',
            'assignment_start_date' => now()->subDays(7)->toDateString(),
        ]);

        Violation::create([
            'driver_id' => $driver->id,
            'officer_id' => $officer->id,
            'officer_assignment_id' => $assignment->id,
            'offense_type' => 'speeding',
            'speed_recorded' => 99,
            'posted_speed_limit' => 80,
            'zone_type' => 'urban',
            'weather_conditions' => 'clear',
            'location' => 'Mombasa Road',
            'evidence_reference' => 'RVW-REF-1',
            'points_assigned' => 4,
            'status' => 'confirmed',
            'occurred_at' => now(),
        ]);

        $assessment = RiskAssessment::create([
            'driver_id' => $driver->id,
            'risk_score' => 0.85,
            'risk_class' => 'high',
            'model_version' => 'rf_test_v1',
            'feature_snapshot' => ['inference_latency_ms' => 40],
            'generated_at' => now(),
        ]);

        $sanction = SanctionAction::create([
            'driver_id' => $driver->id,
            'risk_assessment_id' => $assessment->id,
            'trigger_reason' => 'risk threshold exceeded',
            'action_type' => 'profile_suspension',
            'sanction_type' => 'profile_suspension',
            'status' => 'active',
            'webhook_status' => 'pending',
            'issued_at' => now(),
            'effective_from' => now(),
        ]);

        $reviewLog = ReviewLog::create([
            'sanction_action_id' => $sanction->id,
            'officer_assignment_id' => $assignment->id,
            'reviewed_by_user_id' => null,
            'decision' => null,
            'notes' => 'Pending human review.',
            'reviewed_at' => null,
        ]);

        $officerAttempt = $this->patchJson(
            '/api/integration/entities/ReviewLog/' . $reviewLog->id,
            ['decision' => 'upheld'],
            $this->authHeaders($officer)
        );
        $officerAttempt->assertStatus(403);

        $motoristAttempt = $this->patchJson(
            '/api/integration/entities/ReviewLog/' . $reviewLog->id,
            ['decision' => 'reversed'],
            $this->authHeaders($motorist)
        );
        $motoristAttempt->assertStatus(403);

        $adminAttempt = $this->patchJson(
            '/api/integration/entities/ReviewLog/' . $reviewLog->id,
            [
                'officer_assignment_id' => $assignment->id,
                'reviewed_by_user_id' => $admin->id,
                'decision' => 'amended',
                'notes' => 'Adjusted by admin review panel.',
                'reviewed_at' => now()->toIso8601String(),
            ],
            $this->authHeaders($admin)
        );

        $adminAttempt
            ->assertStatus(200)
            ->assertJsonPath('decision', 'amended')
            ->assertJsonPath('reviewed_by_user_id', (string) $admin->id);
    }

    protected function createUser(string $role, string $email): User
    {
        return User::create([
            'name' => ucfirst($role) . ' User',
            'email' => $email,
            'password' => bcrypt('password'),
            'role' => $role,
            'is_active' => true,
            'registered_on' => now(),
        ]);
    }

    protected function authHeaders(User $user): array
    {
        /** @var JwtTokenService $jwt */
        $jwt = app(JwtTokenService::class);

        return [
            'Authorization' => 'Bearer ' . $jwt->issueAccessToken($user),
            'Accept' => 'application/json',
        ];
    }
}
