<?php

namespace App\Http\Middleware;

use App\Models\DriverProfile;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureDriverScope
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user || $user->role !== 'motorist') {
            return $next($request);
        }

        $driverProfile = DriverProfile::where('user_id', $user->id)->first();
        if (! $driverProfile) {
            abort(403, 'No driver profile found for this account.');
        }

        $targetDriverId = $request->input('driver_id')
            ?? $request->query('query.driver_id')
            ?? $request->route('id');

        if ($targetDriverId !== null && (int) $targetDriverId !== (int) $driverProfile->id) {
            abort(403, 'Drivers can only access their own records.');
        }

        return $next($request);
    }
}
