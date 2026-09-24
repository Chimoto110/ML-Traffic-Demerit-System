<?php

namespace Tests\Feature;

use App\Models\Appeal;
use App\Models\DriverProfile;
use App\Models\SanctionAction;
use App\Models\SystemNotification;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\JwtTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class IntegrationControllerHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Keep JWT signing deterministic in tests.
        config(['app.key' => 'testing-jwt-signing-key-which-is-at-least-thirty-two-bytes']);
    }

    public function test_process_violation_rejects_missing_required_fields(): void
    {
        $officer = $this->createUser('officer');

        $response = $this->postJson(
            '/api/integration/functions/processViolation',
            [],
            $this->authHeaders($officer)
        );

        $response
            ->assertStatus(422)
            ->assertJsonPath('message', 'Validation failed for violation registration payload.')
            ->assertJsonStructure([
                'errors' => [
                    'driver_id',
                    'offence_type',
                    'vehicle_id',
                    'speed_recorded',
                    'speed_limit',
                    'zone_type',
                    'weather',
                    'location',
                    'evidence_reference',
                    'violation_date_time',
                ],
            ]);
    }

    public function test_process_violation_rejects_vehicle_not_owned_by_driver(): void
    {
        $officer = $this->createUser('officer');
        [$driver, $vehicle] = $this->createDriverAndVehicle();

        $otherOwner = $this->createUser('motorist', 'other-owner@example.com');
        $vehicle->update(['owner_user_id' => $otherOwner->id]);

        $payload = $this->validViolationPayload($driver->id, $vehicle->id);

        $response = $this->postJson(
            '/api/integration/functions/processViolation',
            $payload,
            $this->authHeaders($officer)
        );

        $response
            ->assertStatus(422)
            ->assertJsonPath('message', 'Selected vehicle does not belong to the selected driver profile.')
            ->assertJsonPath('errors.vehicle_id.0', 'Selected vehicle does not belong to the selected driver profile.');
    }

    public function test_process_violation_is_atomic_when_risk_inference_fails(): void
    {
        $officer = $this->createUser('officer');
        [$driver, $vehicle] = $this->createDriverAndVehicle();

        Http::fake([
            '*' => Http::response(['error' => 'ml unavailable'], 500),
        ]);

        $payload = $this->validViolationPayload($driver->id, $vehicle->id);

        $response = $this->postJson(
            '/api/integration/functions/processViolation',
            $payload,
            $this->authHeaders($officer)
        );

        $response->assertStatus(500);
        $this->assertDatabaseCount('violations', 0);
        $this->assertDatabaseCount('demerit_ledger_entries', 0);
        $this->assertDatabaseCount('risk_assessments', 0);
        $this->assertDatabaseCount('risk_predictions', 0);
        $this->assertDatabaseCount('sanction_actions', 0);
    }

    public function test_process_violation_creates_violation_ledger_risk_and_sanction_flow(): void
    {
        $officer = $this->createUser('officer');
        [$driver, $vehicle] = $this->createDriverAndVehicle();

        Http::fake([
            'http://127.0.0.1:8001/predict' => Http::response([
                'driver_risk_score' => 0.92,
                'risk_class' => 'high',
                'model_version' => 'rf_test_v1',
                'confidence_level' => 0.98,
                'inference_latency_ms' => 41.2,
                'explanation_top_factors' => [
                    ['feature' => 'cumulative_active_demerit_points', 'contribution' => 0.61],
                    ['feature' => 'speed_recorded', 'contribution' => 0.23],
                ],
            ], 200),
            'http://127.0.0.1:8002/mock-ntsa-webhook' => Http::response([
                'status' => 'synced',
            ], 200),
            '*' => Http::response([], 200),
        ]);

        $payload = $this->validViolationPayload($driver->id, $vehicle->id);

        $response = $this->postJson(
            '/api/integration/functions/processViolation',
            $payload,
            $this->authHeaders($officer)
        );

        $response
            ->assertStatus(200)
            ->assertJsonPath('data.demerit_points', 4)
            ->assertJsonPath('data.new_balance', 4)
            ->assertJsonPath('data.risk.classification', 'high')
            ->assertJsonPath('data.sanction_triggered', true);

        $this->assertDatabaseCount('violations', 1);
        $this->assertDatabaseHas('demerit_ledger_entries', [
            'driver_id' => $driver->id,
            'running_balance' => 4,
            'points_applied' => 4,
        ]);
        $this->assertDatabaseHas('risk_assessments', [
            'driver_id' => $driver->id,
            'risk_class' => 'high',
        ]);
        $this->assertDatabaseHas('sanction_actions', [
            'driver_id' => $driver->id,
            'status' => 'active',
            'action_type' => 'profile_suspension',
        ]);
        $this->assertDatabaseHas('review_logs', [
            'decision' => null,
        ]);
        $this->assertDatabaseHas('driver_profiles', [
            'id' => $driver->id,
            'status' => 'suspended',
        ]);
    }

    public function test_motorist_cannot_invoke_process_violation_function(): void
    {
        $motorist = $this->createUser('motorist');

        $response = $this->postJson(
            '/api/integration/functions/processViolation',
            [],
            $this->authHeaders($motorist)
        );

        $response->assertStatus(403);
    }

    public function test_motorist_can_only_create_appeal_for_own_driver_profile(): void
    {
        $motorist = $this->createUser('motorist', 'motorist-owner@example.com');
        $ownDriver = DriverProfile::create([
            'user_id' => $motorist->id,
            'license_no' => 'MOT-1001',
            'full_name' => 'Own Motorist',
            'license_issue_date' => now()->subYears(3)->toDateString(),
            'status' => 'active',
        ]);

        $otherUser = $this->createUser('motorist', 'someone-else@example.com');
        $otherDriver = DriverProfile::create([
            'user_id' => $otherUser->id,
            'license_no' => 'MOT-1002',
            'full_name' => 'Other Driver',
            'license_issue_date' => now()->subYears(2)->toDateString(),
            'status' => 'active',
        ]);

        $ok = $this->postJson(
            '/api/integration/entities/Appeal',
            [
                'driver_id' => $ownDriver->id,
                'reason' => 'Requesting review of sanction.',
            ],
            $this->authHeaders($motorist)
        );
        $ok->assertStatus(201);

        $forbidden = $this->postJson(
            '/api/integration/entities/Appeal',
            [
                'driver_id' => $otherDriver->id,
                'reason' => 'Trying to file for another user.',
            ],
            $this->authHeaders($motorist)
        );
        $forbidden->assertStatus(403);
    }

    public function test_approved_appeal_lifts_sanction_and_unlocks_driver_profile(): void
    {
        $admin = $this->createUser('admin', 'appeal-admin@example.com');
        $motorist = $this->createUser('motorist', 'appeal-owner@example.com');

        $driver = DriverProfile::create([
            'user_id' => $motorist->id,
            'license_no' => 'APL-' . random_int(1000, 9999),
            'full_name' => 'Appeal Driver',
            'license_issue_date' => now()->subYears(4)->toDateString(),
            'status' => 'suspended',
        ]);

        $sanction = SanctionAction::create([
            'driver_id' => $driver->id,
            'trigger_reason' => 'risk threshold exceeded',
            'action_type' => 'profile_suspension',
            'sanction_type' => 'profile_suspension',
            'status' => 'active',
            'webhook_status' => 'pending',
            'issued_at' => now()->subDay(),
            'effective_from' => now()->subDay(),
        ]);

        $appeal = Appeal::create([
            'driver_id' => $driver->id,
            'sanction_action_id' => $sanction->id,
            'reason' => 'I request a review of this sanction.',
            'status' => 'pending',
            'submitted_at' => now()->subHours(6),
        ]);

        $response = $this->patchJson(
            '/api/integration/entities/Appeal/' . $appeal->id,
            [
                'status' => 'approved',
                'review_notes' => 'Approved after evidence verification.',
                'reviewed_by' => 'Appeals Board',
                'reviewed_at' => now()->toIso8601String(),
            ],
            $this->authHeaders($admin)
        );

        $response
            ->assertStatus(200)
            ->assertJsonPath('status', 'approved')
            ->assertJsonPath('outcome', 'approved');

        $this->assertDatabaseHas('sanction_actions', [
            'id' => $sanction->id,
            'status' => 'lifted',
            'lifted_by' => $admin->name,
        ]);
        $this->assertDatabaseHas('driver_profiles', [
            'id' => $driver->id,
            'status' => 'active',
        ]);

        $this->assertNotNull($sanction->fresh()->lifted_at);
    }

    public function test_rejected_appeal_does_not_lift_sanction_or_unlock_driver_profile(): void
    {
        $admin = $this->createUser('admin', 'appeal-admin-reject@example.com');
        $motorist = $this->createUser('motorist', 'appeal-owner-reject@example.com');

        $driver = DriverProfile::create([
            'user_id' => $motorist->id,
            'license_no' => 'APR-' . random_int(1000, 9999),
            'full_name' => 'Rejected Appeal Driver',
            'license_issue_date' => now()->subYears(4)->toDateString(),
            'status' => 'suspended',
        ]);

        $sanction = SanctionAction::create([
            'driver_id' => $driver->id,
            'trigger_reason' => 'risk threshold exceeded',
            'action_type' => 'profile_suspension',
            'sanction_type' => 'profile_suspension',
            'status' => 'active',
            'webhook_status' => 'pending',
            'issued_at' => now()->subDay(),
            'effective_from' => now()->subDay(),
        ]);

        $appeal = Appeal::create([
            'driver_id' => $driver->id,
            'sanction_action_id' => $sanction->id,
            'reason' => 'Requesting review.',
            'status' => 'pending',
            'submitted_at' => now()->subHours(6),
        ]);

        $response = $this->patchJson(
            '/api/integration/entities/Appeal/' . $appeal->id,
            [
                'status' => 'rejected',
                'review_notes' => 'Rejected after review.',
                'reviewed_by' => 'Appeals Board',
                'reviewed_at' => now()->toIso8601String(),
            ],
            $this->authHeaders($admin)
        );

        $response
            ->assertStatus(200)
            ->assertJsonPath('status', 'rejected')
            ->assertJsonPath('outcome', 'rejected');

        $this->assertDatabaseHas('sanction_actions', [
            'id' => $sanction->id,
            'status' => 'active',
            'lifted_by' => null,
            'lifted_at' => null,
        ]);
        $this->assertDatabaseHas('driver_profiles', [
            'id' => $driver->id,
            'status' => 'suspended',
        ]);
    }

    public function test_officer_can_adjudicate_appeal_and_trigger_unlock_on_approval(): void
    {
        $officer = $this->createUser('officer', 'appeal-officer@example.com');
        $motorist = $this->createUser('motorist', 'appeal-owner-officer@example.com');

        $driver = DriverProfile::create([
            'user_id' => $motorist->id,
            'license_no' => 'APO-' . random_int(1000, 9999),
            'full_name' => 'Officer Appeal Driver',
            'license_issue_date' => now()->subYears(4)->toDateString(),
            'status' => 'suspended',
        ]);

        $sanction = SanctionAction::create([
            'driver_id' => $driver->id,
            'trigger_reason' => 'risk threshold exceeded',
            'action_type' => 'profile_suspension',
            'sanction_type' => 'profile_suspension',
            'status' => 'active',
            'webhook_status' => 'pending',
            'issued_at' => now()->subDay(),
            'effective_from' => now()->subDay(),
        ]);

        $appeal = Appeal::create([
            'driver_id' => $driver->id,
            'sanction_action_id' => $sanction->id,
            'reason' => 'Please review sanction.',
            'status' => 'pending',
            'submitted_at' => now()->subHours(3),
        ]);

        $response = $this->patchJson(
            '/api/integration/entities/Appeal/' . $appeal->id,
            [
                'status' => 'approved',
                'review_notes' => 'Approved by duty officer after review.',
                'reviewed_by' => 'Duty Officer',
                'reviewed_at' => now()->toIso8601String(),
            ],
            $this->authHeaders($officer)
        );

        $response
            ->assertStatus(200)
            ->assertJsonPath('status', 'approved')
            ->assertJsonPath('outcome', 'approved');

        $this->assertDatabaseHas('sanction_actions', [
            'id' => $sanction->id,
            'status' => 'lifted',
            'lifted_by' => $officer->name,
        ]);
        $this->assertDatabaseHas('driver_profiles', [
            'id' => $driver->id,
            'status' => 'active',
        ]);
    }

    public function test_notification_update_scope_for_motorist_and_admin_override(): void
    {
        $recipient = $this->createUser('motorist', 'recipient@example.com');
        $anotherMotorist = $this->createUser('motorist', 'other-motorist@example.com');
        $admin = $this->createUser('admin', 'admin@example.com');

        $userNotification = SystemNotification::create([
            'recipient_user_id' => $recipient->id,
            'channel' => 'in_app',
            'title' => 'Notice',
            'message' => 'Personal alert',
            'status' => 'unread',
            'sent_at' => now(),
        ]);

        $foreignAttempt = $this->patchJson(
            '/api/integration/entities/Notification/' . $userNotification->id,
            ['status' => 'read'],
            $this->authHeaders($anotherMotorist)
        );
        $foreignAttempt->assertStatus(403);

        $ownerAttempt = $this->patchJson(
            '/api/integration/entities/Notification/' . $userNotification->id,
            ['status' => 'read'],
            $this->authHeaders($recipient)
        );
        $ownerAttempt
            ->assertStatus(200)
            ->assertJsonPath('status', 'read');

        $adminReset = $this->patchJson(
            '/api/integration/entities/Notification/' . $userNotification->id,
            ['status' => 'unread'],
            $this->authHeaders($admin)
        );
        $adminReset
            ->assertStatus(200)
            ->assertJsonPath('status', 'unread');
    }

    protected function createUser(string $role, ?string $email = null): User
    {
        return User::create([
            'name' => ucfirst($role) . ' User',
            'email' => $email ?? ($role . '-' . uniqid() . '@example.com'),
            'password' => bcrypt('password'),
            'role' => $role,
            'is_active' => true,
            'registered_on' => now(),
        ]);
    }

    protected function createDriverAndVehicle(): array
    {
        $owner = $this->createUser('motorist', 'driver-owner-' . uniqid() . '@example.com');

        $driver = DriverProfile::create([
            'user_id' => $owner->id,
            'license_no' => 'DRV-' . random_int(1000, 9999),
            'full_name' => 'Driver Under Test',
            'license_issue_date' => now()->subYears(5)->toDateString(),
            'status' => 'active',
        ]);

        $vehicle = Vehicle::create([
            'owner_user_id' => $owner->id,
            'plate_number' => 'KAA' . random_int(100, 999) . 'A',
            'make_model' => 'Toyota Axio',
            'vehicle_class' => 'saloon',
            'colour' => 'white',
            'year_of_manufacture' => 2020,
            'registration_expiry' => now()->addYear()->toDateString(),
        ]);

        return [$driver, $vehicle];
    }

    protected function validViolationPayload(int $driverId, int $vehicleId): array
    {
        return [
            'driver_id' => $driverId,
            'offence_type' => 'speeding',
            'vehicle_id' => $vehicleId,
            'speed_recorded' => 112,
            'speed_limit' => 80,
            'zone_type' => 'highway',
            'weather' => 'clear',
            'location' => 'A104 Northbound',
            'evidence_reference' => 'CAM-UNIT-4451',
            'violation_date_time' => now()->toIso8601String(),
        ];
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
