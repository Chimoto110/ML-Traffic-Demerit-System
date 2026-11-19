<?php

namespace App\Http\Controllers;

use App\Models\LoginAudit;
use App\Models\Role;
use App\Models\User;
use App\Services\JwtTokenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class AuthApiController extends Controller
{
    /** Portal segment => canonical user role. */
    protected const PORTAL_ROLES = [
        'driver' => 'motorist',
        'officer' => 'officer',
        'admin' => 'admin',
    ];

    /** Failed attempts allowed per identity before a 15 minute lockout. */
    protected const MAX_FAILED_ATTEMPTS = 5;

    /** Lockout window in seconds (15 minutes). */
    protected const LOCKOUT_SECONDS = 900;

    public function __construct(private readonly JwtTokenService $jwtTokenService)
    {
    }

    /**
     * POST /api/auth/{driver|officer|admin}/login
     *
     * Portal-scoped login. A role mismatch (or bad password, or inactive
     * account) always returns the SAME generic message so the endpoint
     * cannot be used to enumerate which emails exist on which portal.
     * 5 failed attempts lock the identity for 15 minutes.
     * Every attempt is written to login_audits.
     */
    public function portalLogin(Request $request, string $portal): JsonResponse
    {
        $expectedRole = self::PORTAL_ROLES[$portal] ?? null;

        if ($expectedRole === null) {
            abort(404, 'Unknown portal.');
        }

        $requestedEmail = trim((string) $request->input('email', ''));

        $validated = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $email = strtolower($validated['email']);
        $lockKey = $this->lockoutKey($portal, $request->ip(), $email);
        $genericError = 'Invalid credentials for this portal';

        if (RateLimiter::tooManyAttempts($lockKey, self::MAX_FAILED_ATTEMPTS)) {
            $seconds = RateLimiter::availableIn($lockKey);
            $this->recordLoginAttempt(
                $request,
                null,
                $requestedEmail !== '' ? $requestedEmail : $email,
                false,
                'locked_out'
            );

            // 429 Too Many Requests - the identity is locked for 15 minutes.
            throw new \Illuminate\Http\Exceptions\HttpResponseException(
                response()->json([
                    'message' => "Too many failed attempts. Please try again in {$seconds} seconds.",
                    'errors' => [
                        'email' => ["Too many failed attempts. Please try again in {$seconds} seconds."],
                    ],
                    'retry_after' => $seconds,
                ], 429)
            );
        }

        $user = User::where('email', $email)->first();

        $failureReason = null;

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            $failureReason = 'invalid_credentials';
        } elseif ($user->role !== $expectedRole) {
            // Role mismatch on this portal: indistinguishable from bad credentials.
            $failureReason = 'portal_role_mismatch';
        } elseif (! $user->is_active) {
            $failureReason = 'inactive_account';
        }

        if ($failureReason !== null) {
            RateLimiter::hit($lockKey, self::LOCKOUT_SECONDS);

            $this->recordLoginAttempt(
                $request,
                $user,
                $requestedEmail !== '' ? $requestedEmail : $email,
                false,
                $failureReason
            );

            throw ValidationException::withMessages([
                'email' => [$genericError],
            ]);
        }

        RateLimiter::clear($lockKey);

        $token = $this->issueToken($user, $portal);

        $this->recordLoginAttempt($request, $user, $email, true);

        return response()->json([
            'token' => $token,
            'user' => $this->transformUser($user->fresh(['roleRecord'])),
            'redirect' => $this->portalRedirect($portal),
        ]);
    }

    /**
     * POST /api/auth/login (legacy, portal-agnostic).
     * Delegates to the shared credential check without a role requirement.
     */
    public function login(Request $request): JsonResponse
    {
        $requestedEmail = trim((string) $request->input('email', ''));

        $validated = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $validated['email'])->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            $this->recordLoginAttempt($request, $user, $requestedEmail !== '' ? $requestedEmail : $validated['email'], false, 'invalid_credentials');

            throw ValidationException::withMessages([
                'email' => ['Invalid credentials.'],
            ]);
        }

        if (! $user->is_active) {
            $this->recordLoginAttempt($request, $user, $validated['email'], false, 'inactive_account');

            throw ValidationException::withMessages([
                'email' => ['Account is inactive.'],
            ]);
        }

        $portal = $this->portalForRole($user->role);
        $token = $this->issueToken($user, $portal);

        $this->recordLoginAttempt($request, $user, $validated['email'], true);

        return response()->json([
            'token' => $token,
            'user' => $this->transformUser($user->fresh(['roleRecord'])),
            'redirect' => $this->portalRedirect($portal),
        ]);
    }

    /**
     * POST /api/auth/register
     *
     * Only drivers (motorists) may self-register. Any requested role other
     * than driver/motorist is coerced to motorist - officers and admins are
     * provisioned by the admin portal only.
     */
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8',
        ]);

        $name = trim((string) ($validated['name'] ?? ''));

        if ($name === '') {
            $name = strtok($validated['email'], '@') ?: 'New User';
        }

        $roleRecordId = Role::query()
            ->where('role_name', 'motorist')
            ->value('id');

        $user = User::create([
            'name' => $name,
            'email' => $validated['email'],
            'password' => Hash::driver('bcrypt')->make($validated['password']),
            'role' => 'motorist',
            'role_id' => $roleRecordId,
            'is_active' => true,
            'registered_on' => now(),
        ]);

        // Every self-registered driver needs a driver profile so the
        // demerit ledger, appeals and payments flows have a target.
        // license_issue_date is NOT NULL without a DB default.
        \App\Models\DriverProfile::create([
            'user_id' => $user->id,
            'license_no' => 'PENDING-' . str_pad((string) $user->id, 6, '0', STR_PAD_LEFT),
            'full_name' => $name,
            'license_issue_date' => now()->toDateString(),
            'status' => 'active',
            'current_demerit_points' => 0,
            'account_status' => 'active',
        ]);

        $token = $this->issueToken($user, 'driver');

        $this->recordLoginAttempt($request, $user, $validated['email'], true);

        return response()->json([
            'token' => $token,
            'user' => $this->transformUser($user->fresh(['roleRecord'])),
            'redirect' => '/driver',
        ], 201);
    }

    public function me(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json($this->transformUser($user->fresh(['roleRecord'])));
    }

    public function logout(Request $request): JsonResponse
    {
        // Revoke the Sanctum token backing this request (if any). Stateless
        // JWTs simply expire via TTL.
        $bearer = (string) $request->bearerToken();

        if ($bearer !== '') {
            try {
                $token = \Laravel\Sanctum\PersonalAccessToken::findToken($bearer);
                $token?->delete();
            } catch (\Throwable) {
                // Not a Sanctum token (legacy JWT) - nothing to revoke.
            }
        }

        return response()->json(['ok' => true]);
    }

    /**
     * Issues the API token for a login. Sanctum tokens are the source of
     * truth (a row lands in personal_access_tokens so sessions are visible
     * and revocable); a short-lived JWT is returned alongside for
     * backward-compatible consumers.
     */
    protected function issueToken(User $user, ?string $portal): string
    {
        $abilities = ['portal:' . ($portal ?? 'general')];

        if ($user->isAdmin()) {
            $abilities[] = 'admin';
        }

        try {
            return $user->createToken($portal ?? 'general', $abilities)->plainTextToken;
        } catch (\Throwable $e) {
            // Fall back to the legacy JWT if token persistence fails.
            return $this->jwtTokenService->issueAccessToken($user);
        }
    }

    protected function portalForRole(string $role): string
    {
        return collect(self::PORTAL_ROLES)->search($role) ?: 'driver';
    }

    protected function portalRedirect(string $portal): string
    {
        return '/' . $portal;
    }

    protected function lockoutKey(string $portal, ?string $ip, string $email): string
    {
        return sprintf('login-lockout:%s:%s:%s', $portal, $ip ?? 'unknown-ip', $email);
    }

    protected function transformUser(User $user): array
    {
        $role = $user->role === 'motorist' ? 'driver' : $user->role;

        return [
            'id' => (string) $user->id,
            'full_name' => $user->name,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $role,
            'is_active' => (bool) $user->is_active,
            'registered_on' => optional($user->registered_on)->toIso8601String(),
        ];
    }

    protected function recordLoginAttempt(Request $request, ?User $user, string $email, bool $successful, ?string $failureReason = null): void
    {
        LoginAudit::create([
            'user_id' => $user?->id,
            'email' => strtolower($email),
            'successful' => $successful,
            'failure_reason' => $failureReason,
            'ip_address' => $request->ip(),
            'user_agent' => (string) $request->userAgent(),
            'attempted_at' => now(),
        ]);
    }
}
