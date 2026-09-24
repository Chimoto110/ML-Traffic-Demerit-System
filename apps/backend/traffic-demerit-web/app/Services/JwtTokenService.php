<?php

namespace App\Services;

use App\Models\User;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Support\Str;

class JwtTokenService
{
    public function issueAccessToken(User $user, ?int $minutes = null): string
    {
        $now = time();
        $ttlMinutes = $minutes ?? (int) config('auth.jwt_ttl_minutes', 30);

        $payload = [
            'iss' => config('app.url'),
            'sub' => (string) $user->id,
            'role' => $user->role,
            'email' => $user->email,
            'jti' => (string) Str::uuid(),
            'iat' => $now,
            'nbf' => $now,
            'exp' => $now + max(60, $ttlMinutes * 60),
        ];

        return JWT::encode($payload, (string) config('app.key'), 'HS256');
    }

    public function decodeAccessToken(string $token): object
    {
        return JWT::decode($token, new Key((string) config('app.key'), 'HS256'));
    }
}
