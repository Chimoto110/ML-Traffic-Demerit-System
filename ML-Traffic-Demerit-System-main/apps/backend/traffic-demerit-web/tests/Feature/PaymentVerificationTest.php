<?php

namespace Tests\Feature;

use App\Models\DriverProfile;
use App\Models\Payment;
use App\Models\Role;
use App\Models\User;
use App\Models\Violation;
use App\Services\JwtTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_payment_verification_clears_a_driver_when_payment_is_completed(): void
    {
        Role::firstOrCreate([
            'role_name' => 'motorist',
        ], [
            'permission_level' => 1,
            'role_description' => 'Driver',
        ]);

        $role = Role::where('role_name', 'motorist')->first();

        $user = User::create([
            'name' => 'Test Driver',
            'email' => 'driver@example.com',
            'password' => bcrypt('secret123'),
            'role' => 'motorist',
            'role_id' => $role->id,
            'is_active' => true,
            'registered_on' => now(),
        ]);

        $driver = DriverProfile::create([
            'user_id' => $user->id,
            'license_no' => 'ABC12345',
            'full_name' => 'Test Driver',
            'license_issue_date' => now()->subYears(2)->toDateString(),
            'status' => 'suspended',
        ]);

        $violation = Violation::create([
            'driver_id' => $driver->id,
            'offense_type' => 'speeding',
            'zone_type' => 'urban',
            'weather_conditions' => 'clear',
            'points_assigned' => 6,
            'occurred_at' => now()->subDay(),
        ]);

        $payment = Payment::create([
            'violation_id' => $violation->id,
            'driver_id' => $driver->id,
            'amount' => 3000,
            'method' => 'mpesa',
            'status' => 'pending',
            'transaction_ref' => 'MOCK-TEST',
        ]);

        $token = app(JwtTokenService::class)->issueAccessToken($user);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/payments/' . $payment->id . '/verify', [
                'status' => 'completed',
                'reference_number' => 'REF-123',
            ]);

        $response->assertOk();
        $this->assertSame('completed', $payment->fresh()->status);
        $this->assertSame('active', $driver->fresh()->status);
    }
}
