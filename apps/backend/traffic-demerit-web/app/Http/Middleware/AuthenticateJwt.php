<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\JwtTokenService;
use Closure;
use Illuminate\Http\Request;
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

        try {
            $claims = $this->jwtTokenService->decodeAccessToken($bearer);
        } catch (\Throwable) {
            abort(401, 'Invalid or expired token.');
        }

        $userId = isset($claims->sub) ? (int) $claims->sub : 0;
        $user = User::find($userId);
        if (! $user || ! $user->is_active) {
            abort(401, 'User account is inactive or missing.');
        }

        $request->setUserResolver(fn () => $user);

        return $next($request);
    }
}
