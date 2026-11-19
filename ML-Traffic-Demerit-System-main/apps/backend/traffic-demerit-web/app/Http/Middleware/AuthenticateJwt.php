<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\JwtTokenService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateJwt
{
    public function __construct(private readonly JwtTokenService $jwtTokenService)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $bearer = (string) $request->bearerToken();
        if ($bearer === '') {
            abort(401, 'Missing Bearer token.');
        }

        $user = $this->resolveUser($bearer);

        if (! $user || ! $user->is_active) {
            abort(401, 'Invalid or expired token.');
        }

        // Register the resolved user so policy checks and $request->user() work.
        Auth::setUser($user);
        $request->setUserResolver(fn () => $user);

        return $next($request);
    }

    /**
     * Resolves the bearer token to a user. Sanctum personal access tokens
     * (created on login, stored in personal_access_tokens) are checked
     * first; legacy stateless JWTs remain valid until they expire.
     */
    protected function resolveUser(string $bearer): ?User
    {
        // 1) Sanctum token ("tokenId. plaintext" or raw hash).
        try {
            $token = \Laravel\Sanctum\PersonalAccessToken::findToken($bearer);
            if ($token) {
                $tokenable = $token->tokenable;

                if ($tokenable instanceof User) {
                    return $tokenable;
                }

                if (is_array($tokenable) || is_object($tokenable)) {
                    $user = $tokenable instanceof User ? $tokenable : User::find(
                        is_array($tokenable) ? ($tokenable['id'] ?? null) : ($tokenable->id ?? null)
                    );
                    if ($user) {
                        return $user;
                    }
                }
            }
        } catch (\Throwable) {
            // Not a Sanctum token - fall through to JWT verification.
        }

        // 2) Legacy JWT (short-lived fallback for older sessions).
        try {
            $claims = $this->jwtTokenService->decodeAccessToken($bearer);
        } catch (\Throwable) {
            return null;
        }

        $userId = isset($claims->sub) ? (int) $claims->sub : 0;

        return $userId > 0 ? User::find($userId) : null;
    }
}
