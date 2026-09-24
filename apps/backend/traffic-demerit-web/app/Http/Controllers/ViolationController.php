<?php

namespace App\Http\Controllers;

use App\Models\DemeritLedgerEntry;
use App\Models\DriverProfile;
use App\Models\OfficerAssignment;
use App\Models\Vehicle;
use App\Models\Violation;
use App\Services\RiskInferenceService;
use App\Services\SanctionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ViolationController extends Controller
{
    // Simple static point map — move to a DB-backed lookup table later if needed.
    protected array $offensePointsMap = [
        'speeding' => 4,
        'illegal_parking' => 2,
        'dui' => 10,
        'reckless_driving' => 8,
        'red_light' => 5,
        'no_seatbelt' => 2,
        'phone_use' => 3,
    ];

    public function store(Request $request, RiskInferenceService $riskService, SanctionService $sanctionService)
    {
        $payload = $this->normalizeViolationPayload($request);
        $validator = Validator::make($payload, [
            'driver_id' => 'required|exists:driver_profiles,id',
            'vehicle_id' => 'required|exists:vehicles,id',
            'offense_type' => 'required|string',
            'officer_assignment_id' => 'nullable|exists:officer_assignments,id',
            'speed_recorded' => 'required|numeric|min:0',
            'posted_speed_limit' => 'required|numeric|min:0',
            'zone_type' => 'required|in:urban,highway,school_zone,residential',
            'weather_conditions' => 'required|in:clear,rain,fog,night',
            'location' => 'required|string|max:255',
            'evidence_reference' => 'required|string|max:255',
            'occurred_at' => 'required|date',
        ], [
            'driver_id.required' => 'A driver profile must be selected.',
            'vehicle_id.required' => 'A vehicle must be selected for each violation.',
            'offense_type.required' => 'Offence type is required.',
            'speed_recorded.required' => 'Speed recorded is required.',
            'posted_speed_limit.required' => 'Posted speed limit is required.',
            'zone_type.required' => 'Zone type is required.',
            'weather_conditions.required' => 'Weather conditions are required.',
            'location.required' => 'Location description is required.',
            'evidence_reference.required' => 'Evidence reference is required.',
            'occurred_at.required' => 'Violation date and time is required.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed for violation registration payload.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();

        $driver = DriverProfile::findOrFail($validated['driver_id']);
        $vehicle = Vehicle::findOrFail($validated['vehicle_id']);
        if ($driver->user_id === null || (int) $vehicle->owner_user_id !== (int) $driver->user_id) {
            return response()->json([
                'message' => 'Selected vehicle does not belong to the selected driver profile.',
                'errors' => [
                    'vehicle_id' => ['Selected vehicle does not belong to the selected driver profile.'],
                ],
            ], 422);
        }

        $points = $this->offensePointsMap[$validated['offense_type']] ?? 3;

        $occurredAt = Carbon::parse((string) $validated['occurred_at']);

        [$violation, $assessment, $sanction, $daysSinceLast, $newBalance] = DB::transaction(function () use (
            $validated, $points, $driver, $request, $riskService, $sanctionService, $occurredAt
        ) {
            $previousViolation = $driver->violations()->latest('occurred_at')->first();
            $daysSinceLast = $previousViolation
                ? $previousViolation->occurred_at->diffInDays($occurredAt)
                : 0;

            $officerAssignmentId = $validated['officer_assignment_id'] ?? null;
            $officerId = $request->user()?->id;
            if ($officerAssignmentId !== null) {
                $assignment = OfficerAssignment::find($officerAssignmentId);
                if ($assignment) {
                    $officerId = $assignment->officer_user_id;
                }
            }

            // 1. Record the violation
            $violation = Violation::create([
                ...$validated,
                'officer_id' => $officerId,
                'points_assigned' => $points,
                'occurred_at' => $occurredAt,
            ]);

            // 2. Post to the demerit ledger
            $newBalance = $driver->currentBalance() + $points;
            DemeritLedgerEntry::create([
                'driver_id' => $driver->id,
                'violation_id' => $violation->id,
                'points_applied' => $points,
                'days_since_last_offence' => $daysSinceLast,
                'running_balance' => $newBalance,
                'reason' => 'violation',
                'reason_code' => 'violation_auto_post',
                'created_at' => $occurredAt,
                'updated_at' => $occurredAt,
            ]);

            $driver->forceFill([
                'current_demerit_points' => $newBalance,
                'account_status' => $driver->status,
            ])->save();

            // 3. Call the ML inference service for an updated risk score
            $assessment = $riskService->assessDriver($driver, [
                'speed_recorded' => $validated['speed_recorded'],
                'zone_type' => $validated['zone_type'],
                'weather_conditions' => $validated['weather_conditions'],
            ]);

            // 4. Evaluate the automated sanction rule
            $sanction = $sanctionService->evaluate($driver->fresh(), $assessment);

            return [$violation, $assessment, $sanction, $daysSinceLast, $newBalance];
        });

        return response()->json([
            'violation' => $violation,
            'risk_assessment' => $assessment,
            'sanction' => $sanction,
            'driver_balance' => $driver->fresh()->currentBalance(),
            'demerit_points' => (int) $violation->points_assigned,
            'days_since_last_offence' => (int) $daysSinceLast,
            'new_balance' => (int) $newBalance,
        ], 201);
    }

    protected function normalizeViolationPayload(Request $request): array
    {
        return [
            'driver_id' => $request->input('driver_id', $request->input('driverId')),
            'vehicle_id' => $request->input('vehicle_id', $request->input('vehicleId')),
            'offense_type' => $request->input('offense_type', $request->input('offence_type', $request->input('offenseType', $request->input('offenceType')))),
            'officer_assignment_id' => $request->input('officer_assignment_id', $request->input('officerAssignmentId')),
            'speed_recorded' => $request->input('speed_recorded', $request->input('speedRecorded')),
            'posted_speed_limit' => $request->input('posted_speed_limit', $request->input('postedSpeedLimit', $request->input('speed_limit', $request->input('speedLimit')))),
            'zone_type' => $request->input('zone_type', $request->input('zoneType')),
            'weather_conditions' => $request->input('weather_conditions', $request->input('weather', $request->input('weatherConditions'))),
            'location' => $request->input('location', $request->input('location_description', $request->input('locationDescription'))),
            'evidence_reference' => $request->input('evidence_reference', $request->input('evidenceReference')),
            'occurred_at' => $request->input('occurred_at', $request->input('violation_date_time', $request->input('violationDateTime'))),
        ];
    }
}
