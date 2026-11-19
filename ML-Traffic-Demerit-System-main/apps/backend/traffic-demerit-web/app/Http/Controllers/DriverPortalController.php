<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class DriverPortalController extends Controller
{
    /**
     * eCitizen-style "My Services" dashboard for the logged-in motorist:
     * active points, violation history, current risk status, payment status.
     */
    public function dashboard(Request $request)
    {
        $driver = $request->user()->driverProfile;
        abort_if(! $driver, 404, 'No driver profile linked to this account.');

        return response()->json([
            'driver' => $driver,
            'active_demerit_points' => $driver->currentBalance(),
            'status' => $driver->status,
            'latest_risk_assessment' => $driver->latestRiskAssessment(),
            'violations' => $driver->violations()->latest('occurred_at')->with('payment')->paginate(10),
            'sanctions' => $driver->sanctionActions()->latest()->get(),
        ]);
    }
}
