<?php

namespace Tests\Feature;

use App\Models\DemeritLedgerEntry;
use App\Models\DriverProfile;
use App\Models\Payment;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Violation;
use App\Services\JwtTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Covers the new portal-auth, charts, CRUD and closed-loop endpoints.
 * Runs against MySQL (traffic_demerit_test) - see tests/TestCase.php guard.
 */
class PortalApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.key' => 'testing-jwt-signing-key-please-ignore']);
    }

    /* ------------------------------------------------------------------ */
    /* Portal logins                                                       */
    /* ------------------------------------------------------------------ */

    public function test_admin_portal_login_returns_token_and_persists_it(): void
    {
        $user = $this->makeUser('admin', 'Admin@123');

        $response = $this->postJson('/api/auth/admin/login', [
            'email' => $user->email,
            'password' => 'Admin@123',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['token', 'user' => ['id', 'email', 'role'], 'redirect'])
            ->assertJsonPath('user.role', 'admin')
            ->assertJsonPath('redirect', '/admin');

        $this->assertNotSame('', $response->json('token'));
        $this->assertDatabaseHas('personal_access_tokens', ['tokenable_id' => $user->id]);
        $this->assertDatabaseHas('login_audits', ['email' => $user->email, 'successful' => true]);
    }

    public function test_officer_and_driver_portal_logins_redirect_to_their_portals(): void
    {
        $officer = $this->makeUser('officer', 'Officer@123');
        $driver = $this->makeUser('motorist', 'Driver@123');

        $this->postJson('/api/auth/officer/login', ['email' => $officer->email, 'password' => 'Officer@123'])
            ->assertOk()
            ->assertJsonPath('user.role', 'officer')
            ->assertJsonPath('redirect', '/officer');

        $this->postJson('/api/auth/driver/login', ['email' => $driver->email, 'password' => 'Driver@123'])
            ->assertOk()
            ->assertJsonPath('user.role', 'driver')
            ->assertJsonPath('redirect', '/driver');
    }

    public function test_role_mismatch_returns_generic_portal_error(): void
    {
        $driver = $this->makeUser('motorist', 'Driver@123');

        // Driver credentials used on the ADMIN portal -> generic message.
        $this->postJson('/api/auth/admin/login', ['email' => $driver->email, 'password' => 'Driver@123'])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Invalid credentials for this portal');

        $this->assertDatabaseHas('login_audits', [
            'email' => $driver->email,
            'successful' => false,
            'failure_reason' => 'portal_role_mismatch',
        ]);
    }

    public function test_bad_password_returns_the_same_generic_error(): void
    {
        $admin = $this->makeUser('admin', 'Admin@123');

        $this->postJson('/api/auth/admin/login', ['email' => $admin->email, 'password' => 'nope'])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Invalid credentials for this portal');
    }

    public function test_five_failures_lock_the_identity_for_15_minutes(): void
    {
        $admin = $this->makeUser('admin', 'Admin@123');

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/auth/admin/login', ['email' => $admin->email, 'password' => 'wrong'])
                ->assertStatus(422);
        }

        // 6th attempt (even with the CORRECT password) is locked out -> 429.
        $this->postJson('/api/auth/admin/login', ['email' => $admin->email, 'password' => 'Admin@123'])
            ->assertStatus(429)
            ->assertJsonStructure(['message', 'retry_after']);

        $this->assertDatabaseHas('login_audits', ['email' => $admin->email, 'failure_reason' => 'locked_out']);
    }

    public function test_unknown_portal_returns_404(): void
    {
        $this->postJson('/api/auth/superuser/login', ['email' => 'a@b.c', 'password' => 'x'])
            ->assertStatus(404);
    }

    public function test_registration_is_driver_only_and_creates_a_profile(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'New Driver',
            'email' => 'new.driver@example.com',
            'password' => 'Password123',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('user.role', 'driver')
            ->assertJsonPath('redirect', '/driver');

        $user = User::where('email', 'new.driver@example.com')->firstOrFail();
        $this->assertSame('motorist', $user->role);
        $this->assertDatabaseHas('driver_profiles', ['user_id' => $user->id]);
    }

    public function test_registration_cannot_provision_an_officer(): void
    {
        // Even when a role is requested, self-registration coerces to motorist.
        $this->postJson('/api/auth/register', [
            'email' => 'sneaky@example.com',
            'password' => 'Password123',
            'role' => 'officer',
        ])->assertStatus(201);

        $this->assertSame('motorist', User::where('email', 'sneaky@example.com')->value('role'));
    }

    /* ------------------------------------------------------------------ */
    /* System status + charts                                              */
    /* ------------------------------------------------------------------ */

    public function test_system_status_reports_database_and_counts(): void
    {
        $admin = $this->makeUser('admin', 'Admin@123');

        $response = $this->bearer($this->login($admin, 'Admin@123'))
            ->getJson('/api/system/status');

        $response->assertOk()
            ->assertJsonPath('database.name', 'traffic_demerit_test')
            ->assertJsonPath('database.status', 'connected')
            ->assertJsonStructure(['ml' => ['url', 'status'], 'database' => ['counts' => ['users', 'violations']]]);
    }

    public function test_admin_charts_are_mysql_aggregates(): void
    {
        $admin = $this->makeUser('admin', 'Admin@123');

        $response = $this->bearer($this->login($admin, 'Admin@123'))
            ->getJson('/api/charts/admin');

        $response->assertOk()->assertJsonStructure([
            'risk_distribution',
            'monthly_violations_fines',
            'violations_by_type',
            'violations_by_station',
            'payments_summary' => ['by_status', 'totals'],
            'sanctions_by_status',
            'appeals_by_status',
            'watch_list',
            'leaderboard',
        ]);
    }

    public function test_driver_cannot_access_admin_charts(): void
    {
        $driver = $this->makeUser('motorist', 'Driver@123');

        $this->bearer($this->login($driver, 'Driver@123'))
            ->getJson('/api/charts/admin')
            ->assertStatus(403);
    }

    public function test_ml_insights_serve_elbow_and_silhouette_curves(): void
    {
        $admin = $this->makeUser('admin', 'Admin@123');

        // Simulate what the ML step persists (mirrors train_kmeans.py output).
        DB::table('segment_models')->insert([
            'model_version' => 'kmeans_v1',
            'k' => 3,
            'centroids' => json_encode([[0.1, 0.2], [0.5, 0.6], [0.9, 1.0]]),
            'scaler' => json_encode(['mean' => [0, 0], 'scale' => [1, 1]]),
            'wcss' => 1781.46,
            'silhouette' => 0.218,
            'elbow_data' => json_encode(['1' => 2800.0, '2' => 2181.6, '3' => 1781.46]),
            'wcss_by_k' => json_encode(['1' => 2800.0, '2' => 2181.6, '3' => 1781.46]),
            'silhouette_by_k' => json_encode(['2' => 0.2167, '3' => 0.218, '4' => 0.2096]),
            'trained_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->bearer($this->login($admin, 'Admin@123'))
            ->getJson('/api/charts/ml-insights');

        $response->assertOk()->assertJsonStructure([
            'elbow',
            'silhouette',
            'pca_scatter',
            'segment_profiles',
            'association_rules',
            'rules_bubble',
        ]);

        $this->assertCount(3, $response->json('elbow'));
        $this->assertCount(3, $response->json('silhouette'));
    }

    /* ------------------------------------------------------------------ */
    /* CRUD + closed loop                                                  */
    /* ------------------------------------------------------------------ */

    public function test_violation_create_posts_ledger_balance_score_and_notification(): void
    {
        [$officer, $driver, $vehicle] = $this->seedOfficerDriverVehicle();
        $token = $this->login($officer, 'password-123');

        $before = $driver->currentBalance();
        $ledgerBefore = DemeritLedgerEntry::where('driver_id', $driver->id)->count();

        $response = $this->bearer($token)->postJson('/api/violations', [
            'driver_id' => $driver->id,
            'vehicle_id' => $vehicle->id,
            'offence_type' => 'speeding',
            'speed_recorded' => 118,
            'speed_limit' => 80,
            'zone_type' => 'urban',
            'weather' => 'clear',
            'location' => 'Test Road',
            'evidence_reference' => 'EVID-TEST',
            'violation_date_time' => '2026-10-17 10:00:00',
        ]);

        $response->assertStatus(201)->assertJsonStructure([
            'data' => ['id', 'points_assigned'],
            'meta' => ['demerit_points', 'new_balance', 'risk' => ['score', 'classification', 'model_version']],
        ]);

        $violationId = $response->json('data.id');

        // Ledger +1 (speeding = 4 points), balance updated, risk scored.
        $this->assertSame($before + 4, $driver->fresh()->currentBalance());
        $this->assertSame($ledgerBefore + 1, DemeritLedgerEntry::where('driver_id', $driver->id)->count());
        $this->assertDatabaseHas('demerit_ledger_entries', [
            'driver_id' => $driver->id,
            'violation_id' => $violationId,
            'points_applied' => 4,
        ]);

        // Risk prediction persisted (heuristic when the ML service is down).
        $this->assertDatabaseHas('risk_predictions', ['driver_id' => $driver->id]);

        // The driver was notified.
        $this->assertDatabaseHas('system_notifications', [
            'recipient_user_id' => $driver->user_id,
            'related_type' => 'violation',
            'related_id' => $violationId,
        ]);
    }

    public function test_violation_edit_reverses_and_reposts_the_ledger(): void
    {
        [$officer, $driver, $vehicle] = $this->seedOfficerDriverVehicle();
        $token = $this->login($officer, 'password-123');

        $violation = Violation::create([
            'driver_id' => $driver->id,
            'vehicle_id' => $vehicle->id,
            'officer_id' => $officer->id,
            'offense_type' => 'speeding',
            'speed_recorded' => 100,
            'posted_speed_limit' => 80,
            'zone_type' => 'urban',
            'weather_conditions' => 'clear',
            'location' => 'Old Road',
            'evidence_reference' => 'EVID-EDIT',
            'points_assigned' => 4,
            'status' => 'confirmed',
            'occurred_at' => now(),
        ]);

        DemeritLedgerEntry::create([
            'driver_id' => $driver->id,
            'violation_id' => $violation->id,
            'points_applied' => 4,
            'running_balance' => $driver->currentBalance() + 4,
            'reason' => 'violation',
            'reason_code' => 'violation_auto_post',
        ]);
        $driver->forceFill(['current_demerit_points' => $driver->currentBalance() + 4])->save();

        $before = $driver->fresh()->currentBalance(); // +4

        // Officer edits own violation within 24h: speeding(4) -> reckless(8).
        $this->bearer($token)->patchJson("/api/violations/{$violation->id}", [
            'offense_type' => 'reckless_driving',
        ])->assertOk();

        // Reversal -4 and re-post +8 appended (append-only ledger).
        $this->assertSame($before + 4, $driver->fresh()->currentBalance());
        $this->assertDatabaseHas('demerit_ledger_entries', [
            'violation_id' => $violation->id,
            'reason_code' => 'violation_edit_reversal',
            'points_applied' => -4,
        ]);
        $this->assertDatabaseHas('demerit_ledger_entries', [
            'violation_id' => $violation->id,
            'reason_code' => 'violation_edit_repost',
            'points_applied' => 8,
        ]);
        $this->assertSame(8, $violation->fresh()->points_assigned);
    }

    public function test_officer_cannot_edit_someone_elses_violation(): void
    {
        [$officer, $driver, $vehicle] = $this->seedOfficerDriverVehicle();
        $otherOfficer = $this->makeUser('officer', 'password-123');

        $violation = Violation::create([
            'driver_id' => $driver->id,
            'vehicle_id' => $vehicle->id,
            'officer_id' => $officer->id,
            'offense_type' => 'speeding',
            'points_assigned' => 4,
            'status' => 'confirmed',
            'occurred_at' => now(),
        ]);

        $this->bearer($this->login($otherOfficer, 'password-123'))
            ->patchJson("/api/violations/{$violation->id}", ['location' => 'Hijacked'])
            ->assertStatus(403);
    }

    public function test_officer_cannot_edit_violations_older_than_24_hours(): void
    {
        [$officer, $driver, $vehicle] = $this->seedOfficerDriverVehicle();

        $violation = Violation::create([
            'driver_id' => $driver->id,
            'vehicle_id' => $vehicle->id,
            'officer_id' => $officer->id,
            'offense_type' => 'speeding',
            'points_assigned' => 4,
            'status' => 'confirmed',
            'occurred_at' => now()->subDays(3),
        ]);

        // created_at is not mass assignable - backdate the row directly so the
        // 24 hour officer edit window genuinely does not apply.
        DB::table('violations')
            ->where('id', $violation->id)
            ->update([
                'created_at' => now()->subDays(3),
                'updated_at' => now()->subDays(3),
            ]);

        $this->bearer($this->login($officer, 'password-123'))
            ->patchJson("/api/violations/{$violation->id}", ['location' => 'Too late'])
            ->assertStatus(403);
    }

    public function test_admin_delete_soft_deletes_and_reverses_the_ledger(): void
    {
        [$officer, $driver, $vehicle] = $this->seedOfficerDriverVehicle();
        $admin = $this->makeUser('admin', 'password-123');

        $violation = Violation::create([
            'driver_id' => $driver->id,
            'vehicle_id' => $vehicle->id,
            'officer_id' => $officer->id,
            'offense_type' => 'speeding',
            'points_assigned' => 4,
            'status' => 'confirmed',
            'occurred_at' => now(),
        ]);

        DemeritLedgerEntry::create([
            'driver_id' => $driver->id,
            'violation_id' => $violation->id,
            'points_applied' => 4,
            'running_balance' => $driver->currentBalance() + 4,
            'reason' => 'violation',
            'reason_code' => 'violation_auto_post',
        ]);
        $driver->forceFill(['current_demerit_points' => $driver->currentBalance() + 4])->save();

        $before = $driver->fresh()->currentBalance();

        $this->bearer($this->login($admin, 'password-123'))
            ->deleteJson("/api/violations/{$violation->id}")
            ->assertOk();

        // Soft-deleted (row remains), reversal entry appended, balance restored.
        $this->assertSoftDeleted('violations', ['id' => $violation->id]);
        $this->assertSame($before - 4, $driver->fresh()->currentBalance());
        $this->assertDatabaseHas('demerit_ledger_entries', [
            'violation_id' => $violation->id,
            'reason_code' => 'violation_delete_reversal',
            'points_applied' => -4,
        ]);
    }

    public function test_officer_cannot_delete_violations(): void
    {
        [$officer, $driver, $vehicle] = $this->seedOfficerDriverVehicle();

        $violation = Violation::create([
            'driver_id' => $driver->id,
            'vehicle_id' => $vehicle->id,
            'officer_id' => $officer->id,
            'offense_type' => 'speeding',
            'points_assigned' => 4,
            'status' => 'confirmed',
            'occurred_at' => now(),
        ]);

        $this->bearer($this->login($officer, 'password-123'))
            ->deleteJson("/api/violations/{$violation->id}")
            ->assertStatus(403);
    }

    public function test_index_supports_pagination_search_sort_and_filters(): void
    {
        $admin = $this->makeUser('admin', 'password-123');
        $token = $this->login($admin, 'password-123');

        $driver = DriverProfile::create([
            'user_id' => null,
            'license_no' => 'DL-SEARCH-1',
            'full_name' => 'Searchable Person',
            'license_issue_date' => now()->toDateString(),
            'status' => 'active',
        ]);

        // Pagination + search envelope.
        $response = $this->bearer($token)
            ->getJson('/api/drivers?per_page=1&page=1&q=Searchable');
        $response->assertOk()->assertJsonStructure([
            'data' => [['id', 'full_name']],
            'meta' => ['current_page', 'per_page', 'total', 'last_page'],
        ]);
        $this->assertSame(1, $response->json('meta.total'));
        $this->assertSame('Searchable Person', $response->json('data.0.full_name'));

        // Filter + sort.
        $this->bearer($token)
            ->getJson("/api/drivers?filter%5Bstatus%5D=active&sort=-full_name&per_page=5")
            ->assertOk();
        $this->assertDatabaseHas('driver_profiles', ['id' => $driver->id]);
    }

    public function test_motorist_sees_only_their_own_rows(): void
    {
        [$officer, $driver, $vehicle] = $this->seedOfficerDriverVehicle();
        $motoristUser = User::find($driver->user_id);
        $token = $this->login($motoristUser, 'password-123');

        // Another driver's violation exists.
        $otherUser = $this->makeUser('motorist', 'password-123');
        $otherDriver = DriverProfile::create([
            'user_id' => $otherUser->id,
            'license_no' => 'DL-OTHER-1',
            'full_name' => 'Other Driver',
            'license_issue_date' => now()->toDateString(),
            'status' => 'active',
        ]);
        Vehicle::create([
            'owner_user_id' => $otherUser->id,
            'plate_number' => 'KXX 001A',
            'make_model' => 'Test Car',
            'vehicle_class' => 'saloon',
        ]);
        Violation::create([
            'driver_id' => $otherDriver->id,
            'vehicle_id' => $vehicle->id,
            'offense_type' => 'speeding',
            'points_assigned' => 4,
            'status' => 'confirmed',
            'occurred_at' => now(),
        ]);

        $response = $this->bearer($token)->getJson('/api/violations?per_page=100');
        $response->assertOk();
        $this->assertSame(0, $response->json('meta.total'), 'Motorist must not see other drivers\' violations.');

        // Motorists cannot create violations.
        $this->bearer($token)->postJson('/api/violations', ['driver_id' => $driver->id])
            ->assertStatus(403);
    }

    public function test_driver_can_register_a_vehicle_without_owner_field(): void
    {
        $motorist = $this->makeUser('motorist', 'password-123');
        $token = $this->bearer($this->login($motorist, 'password-123'));

        $response = $token->postJson('/api/vehicles', [
            'plate_number' => 'KSELF' . random_int(100, 999),
            'make_model' => 'Self Registered',
            'vehicle_class' => 'saloon',
        ]);

        $response->assertStatus(201);
        $this->assertSame($motorist->id, (int) $response->json('data.owner_user_id'));
    }

    public function test_driver_cannot_register_a_vehicle_for_another_owner(): void
    {
        $motorist = $this->makeUser('motorist', 'password-123');
        $victim = $this->makeUser('motorist', 'password-123');
        $token = $this->bearer($this->login($motorist, 'password-123'));

        // Spoofing another owner must be pinned back to the acting driver.
        $response = $token->postJson('/api/vehicles', [
            'owner_user_id' => $victim->id,
            'plate_number' => 'KSPOOF' . random_int(100, 999),
            'make_model' => 'Spoofed',
            'vehicle_class' => 'saloon',
        ]);

        $response->assertStatus(201);
        $this->assertSame($motorist->id, (int) $response->json('data.owner_user_id'));
    }

    public function test_driver_can_create_an_appeal_for_their_own_profile(): void
    {
        $motorist = $this->makeUser('motorist', 'password-123');
        $driver = DriverProfile::create([
            'user_id' => $motorist->id,
            'license_no' => 'DL-APPEAL-' . uniqid(),
            'full_name' => 'Appealing Driver',
            'license_issue_date' => now()->toDateString(),
            'status' => 'active',
        ]);
        $token = $this->bearer($this->login($motorist, 'password-123'));

        $response = $token->postJson('/api/appeals', [
            'driver_id' => $driver->id,
            'reason' => 'Points were posted for the wrong vehicle.',
            'requested_action' => 'review',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.driver_id', $driver->id);
    }

    public function test_payments_are_create_and_void_only(): void
    {
        [$officer, $driver, $vehicle] = $this->seedOfficerDriverVehicle();
        $admin = $this->makeUser('admin', 'password-123');
        $token = $this->login($admin, 'password-123');

        $violation = Violation::create([
            'driver_id' => $driver->id,
            'vehicle_id' => $vehicle->id,
            'offense_type' => 'speeding',
            'points_assigned' => 4,
            'status' => 'confirmed',
            'occurred_at' => now(),
        ]);

        // Create (M-Pesa style).
        $response = $this->bearer($token)->postJson('/api/payments', [
            'violation_id' => $violation->id,
            'driver_id' => $driver->id,
            'amount' => 5000,
            'method' => 'mpesa',
            'reference_number' => 'MPESA-TEST-1',
        ]);
        $response->assertStatus(201);
        $paymentId = $response->json('data.id');

        // No update / no delete routes -> 405.
        $this->bearer($token)->putJson("/api/payments/{$paymentId}", ['amount' => 1])->assertStatus(405);
        $this->bearer($token)->deleteJson("/api/payments/{$paymentId}")->assertStatus(405);

        // Void keeps the row (append-only) and marks it voided.
        $this->bearer($token)->postJson("/api/payments/{$paymentId}/void")->assertOk();
        $this->assertSame('voided', Payment::find($paymentId)->status);
    }

    /* ------------------------------------------------------------------ */
    /* Helpers                                                             */
    /* ------------------------------------------------------------------ */

    protected function makeUser(string $role, string $password): User
    {
        static $counter = 0;
        $counter++;

        return User::create([
            'name' => ucfirst($role) . " {$counter}",
            'email' => "{$role}-{$counter}-" . uniqid() . '@test.local',
            'password' => \Illuminate\Support\Facades\Hash::make($password),
            'role' => $role,
            'role_id' => User::roleNameToId($role),
            'is_active' => true,
            'registered_on' => now(),
        ]);
    }

    protected function login(User $user, string $password): string
    {
        $response = $this->postJson('/api/auth/' . ($user->role === 'motorist' ? 'driver' : $user->role) . '/login', [
            'email' => $user->email,
            'password' => $password,
        ]);

        $response->assertOk();

        return $response->json('token');
    }

    /** @return array{0: User, 1: DriverProfile, 2: Vehicle} */
    protected function seedOfficerDriverVehicle(): array
    {
        $officer = $this->makeUser('officer', 'password-123');
        $motorist = $this->makeUser('motorist', 'password-123');

        $driver = DriverProfile::create([
            'user_id' => $motorist->id,
            'license_no' => 'DL-SEED-' . uniqid(),
            'full_name' => 'Seeded Driver',
            'license_issue_date' => now()->toDateString(),
            'status' => 'active',
        ]);

        $vehicle = Vehicle::create([
            'owner_user_id' => $motorist->id,
            'plate_number' => 'KSEED' . random_int(100, 999),
            'make_model' => 'Test Vehicle',
            'vehicle_class' => 'saloon',
        ]);

        return [$officer, $driver, $vehicle];
    }

    protected function bearer(string $token)
    {
        return $this->withHeader('Authorization', 'Bearer ' . $token)
            ->withHeader('Accept', 'application/json');
    }
}
