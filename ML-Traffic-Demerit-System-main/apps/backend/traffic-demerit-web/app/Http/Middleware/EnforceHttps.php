<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnforceHttps
{
    public function handle(Request $request, Closure $next): Response
    {
        if (app()->environment('local', 'testing')) {
            return $next($request);
        }

        $forwardedProto = strtolower((string) $request->header('x-forwarded-proto'));
        $isSecure = $request->isSecure() || $forwardedProto === 'https';

        if (! $isSecure) {
            abort(426, 'HTTPS is required for this API.');
        }

        return $next($request);
    }
}
