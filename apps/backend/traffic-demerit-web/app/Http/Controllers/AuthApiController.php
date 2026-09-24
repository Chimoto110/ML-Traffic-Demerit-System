<?php

namespace App\Http\Controllers;

use App\Models\LoginAudit;
use App\Models\Role;
use App\Models\User;
use App\Services\JwtTokenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthApiController extends Controller
{
    public function __construct(private readonly JwtTokenService $jwtTokenService)
    {
    }

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

        $token = $this->jwtTokenService->issueAccessToken($user);

        $this->recordLoginAttempt($request, $user, $validated['email'], true);

        return response()->json([
            'token' => $token,
            'user' => $this->transformUser($user->fresh(['roleRecord'])),
        ]);
    }

    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8',
            'role' => 'nullable|in:motorist,officer,admin,driver',
        ]);

        $requestedRole = $validated['role'] ?? 'motorist';
        $role = $requestedRole === 'driver' ? 'motorist' : $requestedRole;
        $name = trim((string) ($validated['name'] ?? ''));

        if ($name === '') {
            $name = strtok($validated['email'], '@') ?: 'New User';
        }

        $roleRecordId = Role::query()
            ->where('role_name', $role)
            ->value('id');

        $user = User::create([
            'name' => $name,
            'email' => $validated['email'],
            'password' => Hash::driver('bcrypt')->make($validated['password']),
            'role' => $role,
            'role_id' => $roleRecordId,
            'is_active' => true,
            'registered_on' => now(),
        ]);

        $token = $this->jwtTokenService->issueAccessToken($user);

        return response()->json([
            'token' => $token,
            'user' => $this->transformUser($user->fresh(['roleRecord'])),
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
        // JWT is stateless; token expires automatically by TTL.
        return response()->json(['ok' => true]);
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
