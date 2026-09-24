<?php

namespace App\Http\Controllers;

use App\Models\Appeal;
use App\Models\DemeritLedgerEntry;
use App\Models\DriverProfile;
use App\Models\OfficerAssignment;
use App\Models\Payment;
use App\Models\ReviewLog;
use App\Models\RiskPrediction;
use App\Models\Role;
use App\Models\RiskAssessment;
use App\Models\SanctionAction;
use App\Models\Station;
use App\Models\SystemNotification;
use App\Models\Vehicle;
use App\Models\Violation;
use App\Models\User;
use App\Services\NotificationService;
use App\Services\RiskInferenceService;
use App\Services\SanctionService;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;

class IntegrationController extends Controller
{
    protected array $offensePointsMap = [
        'speeding' => 4,
        'illegal_parking' => 2,
        'dui' => 10,
        'reckless_driving' => 8,
        'red_light' => 5,
        'no_seatbelt' => 2,
        'phone_use' => 3,
    ];

    public function listEntity(Request $request, string $entity): JsonResponse
    {
        $this->authorizeEntityRead($request, $entity);

        $rows = $this->queryEntityRows($entity);
        $rows = $this->scopeRowsForMotorist($request, $entity, $rows);
        $sort = $request->query('sort');
        $limit = $request->query('limit');

        $rows = $this->sortRows($rows, is_string($sort) ? $sort : null);

        if (is_numeric($limit)) {
            $rows = array_slice($rows, 0, (int) $limit);
        }

        return response()->json($rows);
    }

    public function filterEntity(Request $request, string $entity): JsonResponse
    {
        $this->authorizeEntityRead($request, $entity);

        $query = $request->query('query', []);
        $rows = $this->queryEntityRows($entity);

        if (is_array($query) && count($query) > 0) {
            $rows = array_values(array_filter($rows, function (array $row) use ($query) {
                foreach ($query as $key => $value) {
                    if (! array_key_exists($key, $row)) {
                        return false;
                    }

                    if ((string) $row[$key] !== (string) $value) {
                        return false;
                    }
                }

                return true;
            }));
        }

        $rows = $this->scopeRowsForMotorist($request, $entity, $rows);

        $sort = $request->query('sort');
        $limit = $request->query('limit');
        $rows = $this->sortRows($rows, is_string($sort) ? $sort : null);

        if (is_numeric($limit)) {
            $rows = array_slice($rows, 0, (int) $limit);
        }

        return response()->json($rows);
    }

    public function createEntity(Request $request, string $entity): JsonResponse
    {
        $this->authorizeEntityCreate($request, $entity);
        $notificationService = app(NotificationService::class);

        if ($entity === 'Appeal') {
            $validated = $request->validate([
                'driver_id' => 'required|exists:driver_profiles,id',
                'violation_id' => 'nullable|exists:violations,id',
                'sanction_action_id' => 'nullable|exists:sanction_actions,id',
                'reason' => 'required|string',
                'status' => 'nullable|in:pending,approved,rejected',
                'submitted_at' => 'nullable|date',
            ]);

            $status = $validated['status'] ?? 'pending';
            $driverId = (int) $validated['driver_id'];

            if ($request->user()?->role === 'motorist') {
                $motoristDriverId = $this->resolveMotoristDriverId($request);
                if ($motoristDriverId === null || $motoristDriverId !== $driverId) {
                    abort(403, 'Drivers can only submit appeals for their own profile.');
                }
            }

            $appeal = Appeal::create([
                'driver_id' => $validated['driver_id'],
                'violation_id' => $validated['violation_id'] ?? null,
                'sanction_action_id' => $validated['sanction_action_id'] ?? null,
                'reason' => $validated['reason'],
                'status' => $status,
                'outcome' => $status === 'pending' ? null : $status,
                'resolved_at' => $status === 'pending' ? null : now(),
                'submitted_at' => $validated['submitted_at'] ?? now(),
            ]);

            $driver = DriverProfile::with('user')->find((int) $validated['driver_id']);
            if ($driver?->user_id) {
                $notificationService->notifyUser(
                    (int) $driver->user_id,
                    'Appeal Submitted',
                    'Your appeal has been submitted and is awaiting NTSA review.',
                    [
                        'appeal_id' => $appeal->id,
                        'status' => $appeal->status,
                    ],
                    'appeal',
                    (int) $appeal->id
                );
            }

            $notificationService->notifyRole(
                'admin',
                'New Appeal Requires Review',
                'A new appeal has been submitted and is pending administrative review.',
                [
                    'appeal_id' => $appeal->id,
                    'driver_id' => $appeal->driver_id,
                    'status' => $appeal->status,
                ],
                'appeal',
                (int) $appeal->id
            );

            return response()->json($this->transformAppeal($appeal->fresh(['driver', 'violation'])), 201);
        }

        if ($entity === 'Station') {
            $validated = $request->validate([
                'station_name' => 'required|string|max:255',
                'region' => 'required|string|max:255',
                'physical_address' => 'required|string|max:255',
                'contact_phone' => 'nullable|string|max:255',
            ]);

            $station = Station::create($validated);
            return response()->json($this->transformStation($station), 201);
        }

        if ($entity === 'OfficerAssignment') {
            $validated = $request->validate([
                'officer_user_id' => 'required|exists:users,id',
                'station_id' => 'required|exists:stations,id',
                'badge_number' => 'required|string|max:255',
                'shift_type' => 'required|in:day,night,swing',
                'assignment_start_date' => 'required|date',
                'assignment_end_date' => 'nullable|date|after_or_equal:assignment_start_date',
            ]);

            $assignment = OfficerAssignment::create($validated);
            return response()->json($this->transformOfficerAssignment($assignment->fresh(['officer', 'station'])), 201);
        }

        if ($entity === 'Vehicle') {
            $validated = $request->validate([
                'owner_user_id' => 'required|exists:users,id',
                'plate_number' => 'required|string|max:255|unique:vehicles,plate_number',
                'make_model' => 'required|string|max:255',
                'vehicle_class' => 'required|string|max:255',
                'colour' => 'nullable|string|max:255',
                'year_of_manufacture' => 'nullable|integer|min:1950|max:2100',
                'registration_expiry' => 'nullable|date',
            ]);

            $vehicle = Vehicle::create($validated);
            return response()->json($this->transformVehicle($vehicle->fresh(['owner'])), 201);
        }

        abort(405, 'Create not supported for this entity.');
    }

    public function updateEntity(Request $request, string $entity, int $id): JsonResponse
    {
        $this->authorizeEntityUpdate($request, $entity);
        $notificationService = app(NotificationService::class);

        if ($entity === 'Driver') {
            $driver = DriverProfile::findOrFail($id);
            $status = strtolower((string) $request->input('status', $driver->status));
            $driver->status = $status === 'active' ? 'active' : 'suspended';
            $driver->save();

            return response()->json($this->transformDriver($driver->fresh(['user', 'violations.payment'])));
        }

        if ($entity === 'Sanction') {
            $sanction = SanctionAction::findOrFail($id);
            $validated = $request->validate([
                'status' => 'nullable|in:active,lifted',
                'lifted_by' => 'nullable|string',
                'lifted_at' => 'nullable|date',
            ]);

            if (isset($validated['status'])) {
                $sanction->status = $validated['status'];
            }
            if (array_key_exists('lifted_by', $validated)) {
                $sanction->lifted_by = $validated['lifted_by'];
            }
            if (array_key_exists('lifted_at', $validated)) {
                $sanction->lifted_at = $validated['lifted_at'];
            }
            $sanction->save();

            if (($validated['status'] ?? null) === 'lifted') {
                $driver = $sanction->driver;
                if ($driver && $driver->status !== 'active') {
                    $driver->update(['status' => 'active']);
                }

                if ($driver?->user_id) {
                    $notificationService->notifyUser(
                        (int) $driver->user_id,
                        'Sanction Lifted',
                        'Your sanction has been lifted and your account is now active.',
                        [
                            'sanction_id' => $sanction->id,
                            'driver_id' => $driver->id,
                        ],
                        'sanction',
                        (int) $sanction->id
                    );
                }
            }

            return response()->json($this->transformSanction($sanction->fresh(['driver', 'riskAssessment'])));
        }

        if ($entity === 'Appeal') {
            $appeal = Appeal::findOrFail($id);
            $validated = $request->validate([
                'status' => 'nullable|in:pending,approved,rejected',
                'outcome' => 'nullable|in:approved,rejected',
                'review_notes' => 'nullable|string',
                'reviewed_by' => 'nullable|string',
                'reviewed_at' => 'nullable|date',
                'resolved_at' => 'nullable|date',
            ]);

            $nextStatus = $validated['status'] ?? $appeal->status;
            if ($nextStatus === 'pending') {
                $validated['outcome'] = null;
                $validated['resolved_at'] = null;
            } else {
                $validated['outcome'] = $validated['outcome'] ?? $nextStatus;
                $validated['resolved_at'] = $validated['resolved_at'] ?? now();
            }

            $appeal->fill($validated)->save();

            $appeal->load(['driver.user', 'sanction']);
            $driver = $appeal->driver;

            // Approved appeal automatically resolves related sanction and unlocks driver.
            if (($appeal->status === 'approved' || $appeal->outcome === 'approved') && $appeal->sanction && $appeal->sanction->status !== 'lifted') {
                $appeal->sanction->update([
                    'status' => 'lifted',
                    'lifted_by' => (string) ($request->user()?->name ?? 'System'),
                    'lifted_at' => now(),
                ]);

                if ($driver && $driver->status !== 'active') {
                    $driver->update(['status' => 'active']);
                }
            }

            if ($driver?->user_id) {
                $notificationService->notifyUser(
                    (int) $driver->user_id,
                    'Appeal Status Updated',
                    'Your appeal status is now ' . strtoupper((string) $appeal->status) . '.',
                    [
                        'appeal_id' => $appeal->id,
                        'status' => $appeal->status,
                        'outcome' => $appeal->outcome,
                        'reviewed_at' => optional($appeal->reviewed_at)->toIso8601String(),
                        'resolved_at' => optional($appeal->resolved_at)->toIso8601String(),
                    ],
                    'appeal',
                    (int) $appeal->id
                );
            }

            return response()->json($this->transformAppeal($appeal->fresh(['driver', 'violation'])));
        }

        if ($entity === 'Notification') {
            $notification = SystemNotification::findOrFail($id);
            $validated = $request->validate([
                'status' => 'required|in:unread,read',
            ]);

            $user = $request->user();
            if (! $user) {
                abort(401, 'Unauthenticated user.');
            }

            if ($user->role !== 'admin') {
                $isRecipient = $notification->recipient_user_id !== null && (int) $notification->recipient_user_id === (int) $user->id;
                $isRoleRecipient = $notification->recipient_role !== null && $notification->recipient_role === $user->role;
                if (! $isRecipient && ! $isRoleRecipient) {
                    abort(403, 'You can only update your own notifications.');
                }
            }

            $status = (string) $validated['status'];
            $notification->status = $status;
            $notification->read_at = $status === 'read' ? now() : null;
            $notification->save();

            return response()->json($this->transformNotification($notification->fresh(['recipient'])));
        }

        if ($entity === 'Violation') {
            $violation = Violation::findOrFail($id);
            $validated = $request->validate([
                'status' => 'required|in:confirmed,disputed',
            ]);
            $violation->fill($validated)->save();

            return response()->json($this->transformViolation($violation->fresh(['driver', 'officer'])));
        }

        if ($entity === 'Station') {
            $station = Station::findOrFail($id);
            $validated = $request->validate([
                'station_name' => 'sometimes|required|string|max:255',
                'region' => 'sometimes|required|string|max:255',
                'physical_address' => 'sometimes|required|string|max:255',
                'contact_phone' => 'nullable|string|max:255',
            ]);
            $station->fill($validated)->save();

            return response()->json($this->transformStation($station));
        }

        if ($entity === 'OfficerAssignment') {
            $assignment = OfficerAssignment::findOrFail($id);
            $validated = $request->validate([
                'officer_user_id' => 'sometimes|required|exists:users,id',
                'station_id' => 'sometimes|required|exists:stations,id',
                'badge_number' => 'sometimes|required|string|max:255',
                'shift_type' => 'sometimes|required|in:day,night,swing',
                'assignment_start_date' => 'sometimes|required|date',
                'assignment_end_date' => 'nullable|date',
            ]);
            $assignment->fill($validated)->save();

            return response()->json($this->transformOfficerAssignment($assignment->fresh(['officer', 'station'])));
        }

        if ($entity === 'Vehicle') {
            $vehicle = Vehicle::findOrFail($id);
            $validated = $request->validate([
                'owner_user_id' => 'sometimes|required|exists:users,id',
                'plate_number' => 'sometimes|required|string|max:255|unique:vehicles,plate_number,' . $vehicle->id,
                'make_model' => 'sometimes|required|string|max:255',
                'vehicle_class' => 'sometimes|required|string|max:255',
                'colour' => 'nullable|string|max:255',
                'year_of_manufacture' => 'nullable|integer|min:1950|max:2100',
                'registration_expiry' => 'nullable|date',
            ]);
            $vehicle->fill($validated)->save();

            return response()->json($this->transformVehicle($vehicle->fresh(['owner'])));
        }

        if ($entity === 'Role') {
            $role = Role::findOrFail($id);
            $validated = $request->validate([
                'role_name' => 'sometimes|required|string|max:255|unique:roles,role_name,' . $role->id,
                'permission_level' => 'sometimes|required|integer|min:1|max:1000',
                'role_description' => 'nullable|string',
            ]);
            $role->fill($validated)->save();

            return response()->json($this->transformRole($role));
        }

        if ($entity === 'ReviewLog') {
            $reviewLog = ReviewLog::findOrFail($id);
            $validated = $request->validate([
                'officer_assignment_id' => 'nullable|exists:officer_assignments,id',
                'reviewed_by_user_id' => 'nullable|exists:users,id',
                'decision' => 'nullable|in:upheld,amended,reversed',
                'notes' => 'nullable|string',
                'reviewed_at' => 'nullable|date',
            ]);
            $reviewLog->fill($validated)->save();

            return response()->json($this->transformReviewLog($reviewLog->fresh(['sanction', 'officerAssignment.officer', 'officerAssignment.station', 'reviewer'])));
        }

        abort(405, 'Update not supported for this entity.');
    }

    public function invokeFunction(Request $request, string $name, RiskInferenceService $riskService, SanctionService $sanctionService): JsonResponse
    {
        $this->authorizeFunctionCall($request, $name);

        if ($name === 'processViolation') {
            return response()->json(['data' => $this->processViolation($request, $riskService, $sanctionService)]);
        }

        if ($name === 'predictAccidentRisk') {
            return response()->json(['data' => $this->predictAccidentRisk($request, $riskService)]);
        }

        if ($name === 'generateComplianceReport') {
            return response()->json(['data' => $this->generateComplianceReport()]);
        }

        if ($name === 'generateSystemReadinessReport') {
            return response()->json(['data' => $this->generateSystemReadinessReport()]);
        }

        abort(404, 'Unknown function.');
    }

    protected function processViolation(Request $request, RiskInferenceService $riskService, SanctionService $sanctionService): array
    {
        $payload = $this->normalizeViolationPayload($request);

        $validator = Validator::make($payload, [
            'driver_id' => 'required|exists:driver_profiles,id',
            'offence_type' => 'required|string',
            'officer_id' => 'nullable|integer',
            'officer_assignment_id' => 'nullable|exists:officer_assignments,id',
            'vehicle_id' => 'required|exists:vehicles,id',
            'speed_recorded' => 'required|numeric|min:0',
            'speed_limit' => 'required|numeric|min:0',
            'zone_type' => 'required|in:urban,highway,school_zone,residential',
            'weather' => 'required|in:clear,rain,fog,night',
            'location' => 'required|string|max:255',
            'evidence_reference' => 'required|string|max:255',
            'violation_date_time' => 'required|date',
        ], [
            'driver_id.required' => 'A driver profile must be selected.',
            'vehicle_id.required' => 'A vehicle must be selected for each violation.',
            'offence_type.required' => 'Offence type is required.',
            'speed_recorded.required' => 'Speed recorded is required.',
            'speed_limit.required' => 'Posted speed limit is required.',
            'zone_type.required' => 'Zone type is required.',
            'weather.required' => 'Weather conditions are required.',
            'location.required' => 'Location description is required.',
            'evidence_reference.required' => 'Evidence reference is required.',
            'violation_date_time.required' => 'Violation date and time is required.',
        ]);

        if ($validator->fails()) {
            throw new HttpResponseException(response()->json([
                'message' => 'Validation failed for violation registration payload.',
                'errors' => $validator->errors(),
            ], 422));
        }

        $validated = $validator->validated();

        $driver = DriverProfile::findOrFail($validated['driver_id']);
        $vehicle = Vehicle::findOrFail($validated['vehicle_id']);
        if ($driver->user_id === null || (int) $vehicle->owner_user_id !== (int) $driver->user_id) {
            throw new HttpResponseException(response()->json([
                'message' => 'Selected vehicle does not belong to the selected driver profile.',
                'errors' => [
                    'vehicle_id' => ['Selected vehicle does not belong to the selected driver profile.'],
                ],
            ], 422));
        }

        if ($driver->status !== 'active') {
            abort(422, 'This driver profile is locked due to an active sanction.');
        }

        $points = $this->offensePointsMap[$validated['offence_type']] ?? 3;
        $occurredAt = Carbon::parse((string) $validated['violation_date_time']);

        [$violationModel, $assessmentModel, $sanctionModel, $daysSinceLast, $newBalance] = DB::transaction(function () use ($validated, $driver, $points, $occurredAt, $riskService, $sanctionService) {
            $previousViolation = $driver->violations()->latest('occurred_at')->first();
            $daysSinceLast = $previousViolation
            ? $previousViolation->occurred_at->diffInDays($occurredAt)
                : 0;

            $officerAssignmentId = $validated['officer_assignment_id'] ?? null;
            $officerId = $validated['officer_id'] ?? null;
            if ($officerAssignmentId !== null) {
                $assignment = OfficerAssignment::find($officerAssignmentId);
                if ($assignment) {
                    $officerId = $assignment->officer_user_id;
                }
            }

            $violation = Violation::create([
                'driver_id' => $driver->id,
                'vehicle_id' => $validated['vehicle_id'] ?? null,
                'officer_id' => $officerId,
                'officer_assignment_id' => $officerAssignmentId,
                'offense_type' => $validated['offence_type'],
                'speed_recorded' => $validated['speed_recorded'] ?? null,
                'posted_speed_limit' => $validated['speed_limit'] ?? null,
                'zone_type' => $validated['zone_type'],
                'weather_conditions' => $validated['weather'],
                'location' => $validated['location'] ?? null,
                'evidence_reference' => $validated['evidence_reference'] ?? null,
                'points_assigned' => $points,
                'status' => 'confirmed',
                'occurred_at' => $occurredAt,
            ]);

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

            $assessment = $riskService->assessDriver($driver->fresh(), [
                'speed_recorded' => $validated['speed_recorded'] ?? null,
                'zone_type' => $validated['zone_type'],
                'weather_conditions' => $validated['weather'],
            ]);

            $sanction = $sanctionService->evaluate($driver->fresh(), $assessment);
            if ($sanction && $sanction->status !== 'active') {
                $sanction->update(['status' => 'active']);
            }

            return [$violation->fresh(['driver', 'officer']), $assessment, $sanction, $daysSinceLast, $newBalance];
        });

        $riskClass = (string) $assessmentModel->risk_class;

        return [
            'violation_id' => (string) $violationModel->id,
            'demerit_points' => (int) $violationModel->points_assigned,
            'new_balance' => (int) $newBalance,
            'days_since_last_offence' => (int) $daysSinceLast,
            'risk' => [
                'score' => (float) $assessmentModel->risk_score,
                'classification' => $riskClass,
                'model_version' => (string) $assessmentModel->model_version,
                'confidence_level' => (float) ($assessmentModel->riskPrediction?->confidence_level ?? 0),
                'inference_latency_ms' => (float) ($assessmentModel->feature_snapshot['inference_latency_ms'] ?? 0),
            ],
            'sanction_triggered' => $sanctionModel !== null,
        ];
    }

    protected function normalizeViolationPayload(Request $request): array
    {
        return [
            'driver_id' => $request->input('driver_id', $request->input('driverId')),
            'offence_type' => $request->input('offence_type', $request->input('offense_type', $request->input('offenceType', $request->input('offenseType')))),
            'officer_id' => $request->input('officer_id', $request->input('officerId')),
            'officer_assignment_id' => $request->input('officer_assignment_id', $request->input('officerAssignmentId')),
            'vehicle_id' => $request->input('vehicle_id', $request->input('vehicleId')),
            'speed_recorded' => $request->input('speed_recorded', $request->input('speedRecorded')),
            'speed_limit' => $request->input('speed_limit', $request->input('posted_speed_limit', $request->input('postedSpeedLimit', $request->input('speedLimit')))),
            'zone_type' => $request->input('zone_type', $request->input('zoneType')),
            'weather' => $request->input('weather', $request->input('weather_conditions', $request->input('weatherConditions'))),
            'location' => $request->input('location', $request->input('location_description', $request->input('locationDescription'))),
            'evidence_reference' => $request->input('evidence_reference', $request->input('evidenceReference')),
            'violation_date_time' => $request->input('violation_date_time', $request->input('violationDateTime', $request->input('occurred_at'))),
        ];
    }

    protected function generateComplianceReport(): array
    {
        $dbDriver = DB::connection()->getDriverName();
        $drivers = DriverProfile::with(['violations.payment'])->get();
        $violationsCount = Violation::count();
        $activeSanctions = SanctionAction::where('status', 'active')->count();
        $pendingAppeals = Appeal::where('status', 'pending')->count();
        $highRisk = RiskAssessment::where('risk_class', 'high')->count();

        $avgBalance = $drivers->count() > 0
            ? $drivers->avg(fn (DriverProfile $driver) => $driver->currentBalance())
            : 0;

        $recidivismRate = $drivers->count() > 0
            ? $drivers->filter(fn (DriverProfile $driver) => $driver->violations()->count() > 1)->count() / $drivers->count()
            : 0;

        $complianceRate = $drivers->count() > 0
            ? $drivers->filter(fn (DriverProfile $driver) => $driver->status === 'active')->count() / $drivers->count()
            : 0;

        $violationsByOffence = Violation::query()
            ->select('offense_type', DB::raw('COUNT(*) as total'))
            ->groupBy('offense_type')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => [
                'offence_type' => $row->offense_type,
                'total' => (int) $row->total,
            ])
            ->all();

        $violationsByZone = Violation::query()
            ->select('zone_type', DB::raw('COUNT(*) as total'))
            ->groupBy('zone_type')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => [
                'zone_type' => $row->zone_type,
                'total' => (int) $row->total,
            ])
            ->all();

        $violationsByWeather = Violation::query()
            ->select('weather_conditions', DB::raw('COUNT(*) as total'))
            ->groupBy('weather_conditions')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => [
                'weather_conditions' => $row->weather_conditions,
                'total' => (int) $row->total,
            ])
            ->all();

        $sanctionsByStatus = SanctionAction::query()
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->get()
            ->map(fn ($row) => [
                'status' => $row->status,
                'total' => (int) $row->total,
            ])
            ->all();

        $appealsByStatus = Appeal::query()
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->get()
            ->map(fn ($row) => [
                'status' => $row->status,
                'total' => (int) $row->total,
            ])
            ->all();

        $appealResolutionStatsQuery = Appeal::query()->whereNotNull('resolved_at');
        if ($dbDriver === 'mysql') {
            $appealResolutionStatsQuery
                ->selectRaw('AVG(TIMESTAMPDIFF(HOUR, submitted_at, resolved_at)) as avg_resolution_hours')
                ->selectRaw('MAX(TIMESTAMPDIFF(HOUR, submitted_at, resolved_at)) as max_resolution_hours');
        } else {
            $appealResolutionStatsQuery
                ->selectRaw('AVG((julianday(resolved_at) - julianday(submitted_at)) * 24) as avg_resolution_hours')
                ->selectRaw('MAX((julianday(resolved_at) - julianday(submitted_at)) * 24) as max_resolution_hours');
        }
        $appealResolutionStats = $appealResolutionStatsQuery->first();

        $riskDistribution = RiskPrediction::query()
            ->select('risk_class', DB::raw('COUNT(*) as total'))
            ->groupBy('risk_class')
            ->get()
            ->map(fn ($row) => [
                'risk_class' => $row->risk_class,
                'total' => (int) $row->total,
            ])
            ->all();

        $modelQuality = RiskPrediction::query()
            ->selectRaw('AVG(confidence_level) as avg_confidence')
            ->selectRaw('COUNT(*) as total_predictions')
            ->first();

        $avgInferenceLatency = RiskAssessment::query()
            ->whereNotNull('feature_snapshot')
            ->get()
            ->avg(function (RiskAssessment $assessment) {
                return (float) ($assessment->feature_snapshot['inference_latency_ms'] ?? 0);
            });

        $monthlyViolationsQuery = Violation::query()
            ->where('occurred_at', '>=', now()->subMonths(6)->startOfMonth())
            ->selectRaw('COUNT(*) as total');

        if ($dbDriver === 'mysql') {
            $monthlyViolationsQuery->selectRaw("DATE_FORMAT(occurred_at, '%Y-%m') as month");
        } else {
            $monthlyViolationsQuery->selectRaw("strftime('%Y-%m', occurred_at) as month");
        }

        $monthlyViolations = $monthlyViolationsQuery
            ->groupBy('month')
            ->orderBy('month')
            ->get()
            ->map(fn ($row) => [
                'month' => $row->month,
                'total' => (int) $row->total,
            ])
            ->all();

        $notificationTotals = SystemNotification::query()
            ->selectRaw('COUNT(*) as total')
            ->selectRaw("SUM(CASE WHEN status = 'unread' THEN 1 ELSE 0 END) as unread")
            ->selectRaw("SUM(CASE WHEN status = 'read' THEN 1 ELSE 0 END) as read")
            ->first();

        return [
            'total_drivers' => $drivers->count(),
            'total_violations' => $violationsCount,
            'active_sanctions' => $activeSanctions,
            'high_risk_drivers' => $highRisk,
            'pending_appeals' => $pendingAppeals,
            'avg_demerit_balance' => (float) $avgBalance,
            'recidivism_rate' => (float) $recidivismRate,
            'compliance_rate' => (float) $complianceRate,
            'violations_by_offence' => $violationsByOffence,
            'violations_by_zone' => $violationsByZone,
            'violations_by_weather' => $violationsByWeather,
            'sanctions_by_status' => $sanctionsByStatus,
            'appeals_by_status' => $appealsByStatus,
            'appeal_resolution' => [
                'avg_resolution_hours' => round((float) ($appealResolutionStats?->avg_resolution_hours ?? 0), 2),
                'max_resolution_hours' => (int) ($appealResolutionStats?->max_resolution_hours ?? 0),
            ],
            'risk_distribution' => $riskDistribution,
            'prediction_quality' => [
                'avg_confidence' => round((float) ($modelQuality?->avg_confidence ?? 0), 4),
                'avg_inference_latency_ms' => round((float) ($avgInferenceLatency ?? 0), 3),
                'total_predictions' => (int) ($modelQuality?->total_predictions ?? 0),
            ],
            'notifications' => [
                'total' => (int) ($notificationTotals?->total ?? 0),
                'unread' => (int) ($notificationTotals?->unread ?? 0),
                'read' => (int) ($notificationTotals?->read ?? 0),
            ],
            'monthly_violations' => $monthlyViolations,
            'generated_at' => now()->toIso8601String(),
        ];
    }

    protected function predictAccidentRisk(Request $request, RiskInferenceService $riskService): array
    {
        $validated = $request->validate([
            'driver_id' => 'required|exists:driver_profiles,id',
        ]);

        $driver = DriverProfile::findOrFail((int) $validated['driver_id']);
        $assessment = $riskService->assessDriver($driver->fresh(), []);
        $prediction = $assessment->riskPrediction;

        return [
            'driver_id' => (string) $driver->id,
            'driver_name' => $driver->full_name,
            'total_violations' => $driver->violations()->count(),
            'current_demerit_points' => $driver->currentBalance(),
            'risk' => [
                'score' => (float) $assessment->risk_score,
                'classification' => (string) $assessment->risk_class,
                'factor_contributions' => $this->normalizeFactorContributions($assessment->feature_snapshot['explanation_top_factors'] ?? []),
            ],
            'model_version' => (string) $assessment->model_version,
            'predicted_at' => optional($assessment->generated_at)->toIso8601String(),
            'prediction' => $prediction ? $this->transformRiskPrediction($prediction->fresh(['driver'])) : null,
        ];
    }

    protected function generateSystemReadinessReport(): array
    {
        $startedAt = microtime(true);
        $dbOk = false;
        $dbError = null;

        try {
            DB::connection()->getPdo();
            $dbOk = true;
        } catch (\Throwable $e) {
            $dbError = $e->getMessage();
        }

        $mlStarted = microtime(true);
        $mlHealthy = false;
        $mlStatusCode = null;
        $mlError = null;

        try {
            $response = Http::timeout(4)->get(config('services.ml_service.url', 'http://127.0.0.1:8001') . '/health');
            $mlHealthy = $response->successful();
            $mlStatusCode = $response->status();
            if (! $mlHealthy) {
                $mlError = $response->body();
            }
        } catch (\Throwable $e) {
            $mlError = $e->getMessage();
        }

        $mlLatencyMs = round((microtime(true) - $mlStarted) * 1000, 2);

        $latestPredictionAt = RiskPrediction::max('predicted_at');
        $latestBatchLagMinutes = $latestPredictionAt
            ? max(0, (int) now()->diffInMinutes(Carbon::parse($latestPredictionAt), false))
            : null;

        $sanctionTotals = SanctionAction::query()
            ->selectRaw('COUNT(*) as total')
            ->selectRaw("SUM(CASE WHEN webhook_status = 'failed' THEN 1 ELSE 0 END) as failed")
            ->first();

        $totalSanctions = (int) ($sanctionTotals?->total ?? 0);
        $failedSanctions = (int) ($sanctionTotals?->failed ?? 0);
        $webhookFailureRate = $totalSanctions > 0 ? $failedSanctions / $totalSanctions : 0;

        $latencySeries = RiskAssessment::query()
            ->whereNotNull('feature_snapshot')
            ->get()
            ->map(fn (RiskAssessment $assessment) => (float) ($assessment->feature_snapshot['inference_latency_ms'] ?? 0))
            ->filter(fn (float $v) => $v > 0)
            ->values()
            ->all();

        sort($latencySeries);
        $p50 = $this->percentile($latencySeries, 0.50);
        $p95 = $this->percentile($latencySeries, 0.95);

        $jobsBacklog = Schema::hasTable('jobs') ? (int) DB::table('jobs')->count() : null;
        $failedJobs = Schema::hasTable('failed_jobs') ? (int) DB::table('failed_jobs')->count() : null;

        $pendingAppeals = (int) Appeal::where('status', 'pending')->count();
        $pendingReviews = (int) ReviewLog::whereNull('decision')->count();

        $overallHealthy = $dbOk
            && $mlHealthy
            && ($latestBatchLagMinutes === null || $latestBatchLagMinutes <= 120)
            && ($failedJobs === null || $failedJobs === 0);

        return [
            'status' => $overallHealthy ? 'healthy' : 'degraded',
            'generated_at' => now()->toIso8601String(),
            'environment' => app()->environment(),
            'database' => [
                'connection' => config('database.default'),
                'ok' => $dbOk,
                'error' => $dbError,
            ],
            'ml_service' => [
                'url' => config('services.ml_service.url', 'http://127.0.0.1:8001'),
                'healthy' => $mlHealthy,
                'status_code' => $mlStatusCode,
                'health_latency_ms' => $mlLatencyMs,
                'error' => $mlError,
            ],
            'scheduler' => [
                'latest_prediction_at' => $latestPredictionAt ? Carbon::parse($latestPredictionAt)->toIso8601String() : null,
                'batch_lag_minutes' => $latestBatchLagMinutes,
                'stale_threshold_minutes' => 120,
            ],
            'reliability' => [
                'queue_backlog_jobs' => $jobsBacklog,
                'failed_jobs' => $failedJobs,
                'pending_appeals' => $pendingAppeals,
                'pending_review_logs' => $pendingReviews,
                'sanction_webhook_failure_rate' => round((float) $webhookFailureRate, 4),
            ],
            'performance' => [
                'inference_latency_p50_ms' => $p50,
                'inference_latency_p95_ms' => $p95,
                'sample_size' => count($latencySeries),
            ],
            'report_latency_ms' => round((microtime(true) - $startedAt) * 1000, 2),
        ];
    }

    protected function percentile(array $sortedValues, float $percentile): float
    {
        $count = count($sortedValues);
        if ($count === 0) {
            return 0.0;
        }

        $index = (int) ceil($percentile * $count) - 1;
        $index = max(0, min($count - 1, $index));

        return round((float) $sortedValues[$index], 3);
    }

    protected function queryEntityRows(string $entity): array
    {
        return match ($entity) {
            'User' => User::query()->get()->map(fn ($row) => $this->transformUser($row))->all(),
            'Role' => Role::query()->get()->map(fn ($row) => $this->transformRole($row))->all(),
            'Station' => Station::query()->get()->map(fn ($row) => $this->transformStation($row))->all(),
            'OfficerAssignment' => OfficerAssignment::with(['officer', 'station'])->get()->map(fn ($row) => $this->transformOfficerAssignment($row))->all(),
            'Vehicle' => Vehicle::with(['owner'])->get()->map(fn ($row) => $this->transformVehicle($row))->all(),
            'Driver' => DriverProfile::with(['user', 'violations.payment'])->get()->map(fn ($row) => $this->transformDriver($row))->all(),
            'Violation' => Violation::with(['driver', 'officer'])->get()->map(fn ($row) => $this->transformViolation($row))->all(),
            'DemeritLedger' => DemeritLedgerEntry::with(['driver', 'violation'])->get()->map(fn ($row) => $this->transformLedger($row))->all(),
            'Notification' => SystemNotification::with(['recipient'])->get()->map(fn ($row) => $this->transformNotification($row))->all(),
            'Payment' => Payment::with(['driver', 'violation'])->get()->map(fn ($row) => $this->transformPayment($row))->all(),
            'RiskPrediction' => RiskPrediction::with(['driver'])->get()->map(fn ($row) => $this->transformRiskPrediction($row))->all(),
            'RecidivismScore' => RiskAssessment::with(['driver', 'riskPrediction'])->get()->map(fn ($row) => $this->transformRisk($row))->all(),
            'Sanction' => SanctionAction::with(['driver', 'riskAssessment'])->get()->map(fn ($row) => $this->transformSanction($row))->all(),
            'Appeal' => Appeal::with(['driver', 'violation'])->get()->map(fn ($row) => $this->transformAppeal($row))->all(),
            'ReviewLog' => ReviewLog::with(['sanction', 'officerAssignment.officer', 'officerAssignment.station', 'reviewer'])->get()->map(fn ($row) => $this->transformReviewLog($row))->all(),
            default => abort(404, 'Unknown entity.'),
        };
    }

    protected function transformUser(User $user): array
    {
        return [
            'id' => (string) $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'role_id' => $user->role_id ? (string) $user->role_id : null,
            'is_active' => (bool) $user->is_active,
        ];
    }

    protected function transformRole(Role $role): array
    {
        return [
            'id' => (string) $role->id,
            'role_name' => $role->role_name,
            'permission_level' => (int) $role->permission_level,
            'role_description' => $role->role_description,
            'created_at' => optional($role->created_at)->toIso8601String(),
        ];
    }

    protected function transformStation(Station $station): array
    {
        return [
            'id' => (string) $station->id,
            'station_name' => $station->station_name,
            'region' => $station->region,
            'physical_address' => $station->physical_address,
            'contact_phone' => $station->contact_phone,
        ];
    }

    protected function transformOfficerAssignment(OfficerAssignment $assignment): array
    {
        return [
            'id' => (string) $assignment->id,
            'officer_user_id' => (string) $assignment->officer_user_id,
            'officer_name' => $assignment->officer?->name,
            'station_id' => (string) $assignment->station_id,
            'station_name' => $assignment->station?->station_name,
            'badge_number' => $assignment->badge_number,
            'shift_type' => $assignment->shift_type,
            'assignment_start_date' => optional($assignment->assignment_start_date)->toDateString(),
            'assignment_end_date' => optional($assignment->assignment_end_date)->toDateString(),
        ];
    }

    protected function transformVehicle(Vehicle $vehicle): array
    {
        return [
            'id' => (string) $vehicle->id,
            'owner_user_id' => (string) $vehicle->owner_user_id,
            'owner_name' => $vehicle->owner?->name,
            'plate_number' => $vehicle->plate_number,
            'make_model' => $vehicle->make_model,
            'vehicle_class' => $vehicle->vehicle_class,
            'colour' => $vehicle->colour,
            'year_of_manufacture' => $vehicle->year_of_manufacture,
            'registration_expiry' => optional($vehicle->registration_expiry)->toDateString(),
        ];
    }

    protected function transformDriver(DriverProfile $driver): array
    {
        $violations = $driver->relationLoaded('violations') ? $driver->violations : $driver->violations()->with('payment')->get();
        $currentBalance = $driver->currentBalance();
        $accountStatus = $driver->status;

        if ($driver->current_demerit_points !== $currentBalance || $driver->account_status !== $accountStatus) {
            $driver->forceFill([
                'current_demerit_points' => $currentBalance,
                'account_status' => $accountStatus,
            ])->save();
        }

        $delay = $violations->avg(function (Violation $violation) {
            if (! $violation->payment || ! $violation->payment->paid_at) {
                return 0;
            }

            return $violation->occurred_at->diffInDays($violation->payment->paid_at);
        });

        return [
            'id' => (string) $driver->id,
            'user_id' => $driver->user_id !== null ? (string) $driver->user_id : null,
            'full_name' => $driver->full_name,
            'profile_photo_url' => $driver->profile_photo_path ? url($driver->profile_photo_path) : null,
            'email' => $driver->user?->email,
            'phone' => '',
            'license_number' => $driver->license_no,
            'status' => $driver->status === 'active' ? 'active' : 'locked',
            'demerit_balance' => $currentBalance,
            'total_violations' => $violations->count(),
            'avg_fine_payment_delay_days' => round((float) ($delay ?? 0), 2),
            'locked_reason' => $driver->status === 'active' ? '' : 'Active sanction in NTSA workflow.',
            'locked_at' => null,
        ];
    }

    protected function transformViolation(Violation $violation): array
    {
        return [
            'id' => (string) $violation->id,
            'driver_id' => (string) $violation->driver_id,
            'driver_name' => $violation->driver?->full_name,
            'driver_email' => $violation->driver?->user?->email,
            'vehicle_id' => $violation->vehicle_id ? (string) $violation->vehicle_id : null,
            'officer_id' => $violation->officer_id ? (string) $violation->officer_id : null,
            'officer_assignment_id' => $violation->officer_assignment_id ? (string) $violation->officer_assignment_id : null,
            'officer_name' => $violation->officer?->name,
            'offence_type' => $violation->offense_type,
            'speed_recorded' => (float) ($violation->speed_recorded ?? 0),
            'speed_limit' => (float) ($violation->posted_speed_limit ?? 0),
            'zone_type' => $violation->zone_type,
            'weather' => $violation->weather_conditions,
            'location' => $violation->location,
            'evidence_reference' => $violation->evidence_reference,
            'timestamp' => optional($violation->occurred_at)->toIso8601String(),
            'demerit_points' => (int) $violation->points_assigned,
            'status' => $violation->status,
        ];
    }

    protected function transformLedger(DemeritLedgerEntry $entry): array
    {
        $offenceType = $entry->violation?->offense_type ?? 'manual_adjustment';

        return [
            'id' => (string) $entry->id,
            'driver_id' => (string) $entry->driver_id,
            'driver_name' => $entry->driver?->full_name,
            'driver_email' => $entry->driver?->user?->email,
            'violation_id' => $entry->violation_id ? (string) $entry->violation_id : null,
            'points_applied' => (int) $entry->points_applied,
            'days_since_last_offence' => $entry->days_since_last_offence === null ? null : (int) $entry->days_since_last_offence,
            'running_balance' => (int) $entry->running_balance,
            'cumulative_balance' => (int) $entry->running_balance,
            'cumulative_active_demerit_points' => (int) $entry->running_balance,
            'offence_type' => $offenceType,
            'reason_code' => $entry->reason_code,
            'timestamp' => optional($entry->created_at)->toIso8601String(),
            'description' => $entry->reason,
        ];
    }

    protected function transformRisk(RiskAssessment $assessment): array
    {
        $prediction = $assessment->riskPrediction;
        $rawFactors = $assessment->feature_snapshot['explanation_top_factors'] ?? [];

        return [
            'id' => (string) $assessment->id,
            'driver_id' => (string) $assessment->driver_id,
            'driver_name' => $assessment->driver?->full_name,
            'driver_email' => $assessment->driver?->user?->email,
            'risk_score' => (float) $assessment->risk_score,
            'risk_classification' => $assessment->risk_class,
            'model_version' => (string) $assessment->model_version,
            'confidence_level' => (float) ($prediction?->confidence_level ?? 0),
            'inference_latency_ms' => (float) ($assessment->feature_snapshot['inference_latency_ms'] ?? 0),
            'features' => $assessment->feature_snapshot['feature_vector'] ?? null,
            'factor_contributions' => $this->normalizeFactorContributions($rawFactors),
            'factor_contributions_raw' => $rawFactors,
            'timestamp' => optional($assessment->generated_at)->toIso8601String(),
            'triggered_sanction' => ($assessment->risk_class === 'high'),
        ];
    }

    protected function normalizeFactorContributions(array $factors): array
    {
        $total = collect($factors)
            ->sum(fn ($factor) => max(0, (float) ($factor['contribution'] ?? 0)));

        return collect($factors)
            ->map(function ($factor) use ($total) {
                $contribution = max(0, (float) ($factor['contribution'] ?? 0));
                $weightPct = $total > 0 ? round(($contribution / $total) * 100, 2) : 0.0;

                return [
                    'feature' => (string) ($factor['feature'] ?? 'unknown_factor'),
                    'contribution' => $contribution,
                    'weight_percentage' => $weightPct,
                ];
            })
            ->values()
            ->all();
    }

    protected function transformRiskPrediction(RiskPrediction $prediction): array
    {
        return [
            'id' => (string) $prediction->id,
            'driver_id' => (string) $prediction->driver_id,
            'driver_name' => $prediction->driver?->full_name,
            'risk_score' => (float) $prediction->risk_score,
            'risk_class' => $prediction->risk_class,
            'model_version' => $prediction->model_version,
            'confidence_level' => (float) ($prediction->confidence_level ?? 0),
            'predicted_at' => optional($prediction->predicted_at)->toIso8601String(),
        ];
    }

    protected function transformSanction(SanctionAction $sanction): array
    {
        $assessment = $sanction->riskAssessment;
        $factors = $assessment?->feature_snapshot['explanation_top_factors'] ?? [];

        return [
            'id' => (string) $sanction->id,
            'driver_id' => (string) $sanction->driver_id,
            'driver_name' => $sanction->driver?->full_name,
            'driver_email' => $sanction->driver?->user?->email,
            'prediction_id' => $sanction->risk_assessment_id ? (string) $sanction->risk_assessment_id : null,
            'risk_score' => (float) ($assessment?->risk_score ?? 0),
            'risk_classification' => (string) ($assessment?->risk_class ?? 'low'),
            'triggered_at' => optional($sanction->created_at)->toIso8601String(),
            'explanation' => $sanction->trigger_reason,
            'factors' => $factors,
            'status' => $sanction->status,
            'lifted_by' => $sanction->lifted_by,
            'lifted_at' => optional($sanction->lifted_at)->toIso8601String(),
        ];
    }

    protected function transformAppeal(Appeal $appeal): array
    {
        return [
            'id' => (string) $appeal->id,
            'driver_id' => (string) $appeal->driver_id,
            'driver_name' => $appeal->driver?->full_name,
            'violation_id' => $appeal->violation_id ? (string) $appeal->violation_id : null,
            'sanction_action_id' => $appeal->sanction_action_id ? (string) $appeal->sanction_action_id : null,
            'offence_type' => $appeal->violation?->offense_type,
            'reason' => $appeal->reason,
            'status' => $appeal->status,
            'outcome' => $appeal->outcome,
            'submitted_at' => optional($appeal->submitted_at)->toIso8601String(),
            'review_notes' => $appeal->review_notes,
            'reviewed_by' => $appeal->reviewed_by,
            'reviewed_at' => optional($appeal->reviewed_at)->toIso8601String(),
            'resolved_at' => optional($appeal->resolved_at)->toIso8601String(),
        ];
    }

    protected function transformReviewLog(ReviewLog $reviewLog): array
    {
        return [
            'id' => (string) $reviewLog->id,
            'sanction_action_id' => (string) $reviewLog->sanction_action_id,
            'officer_assignment_id' => $reviewLog->officer_assignment_id ? (string) $reviewLog->officer_assignment_id : null,
            'reviewed_by_user_id' => $reviewLog->reviewed_by_user_id ? (string) $reviewLog->reviewed_by_user_id : null,
            'reviewer_name' => $reviewLog->reviewer?->name,
            'reviewer_email' => $reviewLog->reviewer?->email,
            'officer_name' => $reviewLog->officerAssignment?->officer?->name,
            'station_name' => $reviewLog->officerAssignment?->station?->station_name,
            'decision' => $reviewLog->decision,
            'notes' => $reviewLog->notes,
            'reviewed_at' => optional($reviewLog->reviewed_at)->toIso8601String(),
        ];
    }

    protected function transformNotification(SystemNotification $notification): array
    {
        return [
            'id' => (string) $notification->id,
            'recipient_user_id' => $notification->recipient_user_id ? (string) $notification->recipient_user_id : null,
            'recipient_role' => $notification->recipient_role,
            'recipient_name' => $notification->recipient?->name,
            'recipient_email' => $notification->recipient?->email,
            'channel' => $notification->channel,
            'title' => $notification->title,
            'message' => $notification->message,
            'context' => $notification->context,
            'related_type' => $notification->related_type,
            'related_id' => $notification->related_id ? (string) $notification->related_id : null,
            'status' => $notification->status,
            'sent_at' => optional($notification->sent_at)->toIso8601String(),
            'read_at' => optional($notification->read_at)->toIso8601String(),
            'created_at' => optional($notification->created_at)->toIso8601String(),
        ];
    }

    protected function transformPayment(Payment $payment): array
    {
        return [
            'id' => (string) $payment->id,
            'driver_id' => (string) $payment->driver_id,
            'driver_name' => $payment->driver?->full_name,
            'driver_email' => $payment->driver?->user?->email,
            'violation_id' => $payment->violation_id ? (string) $payment->violation_id : null,
            'violation_type' => $payment->violation?->offense_type,
            'amount' => (float) ($payment->amount ?? 0),
            'amount_paid' => (float) ($payment->amount_paid ?? 0),
            'method' => $payment->method,
            'status' => $payment->status,
            'transaction_ref' => $payment->transaction_ref,
            'reference_number' => $payment->reference_number,
            'currency' => $payment->currency ?? 'KES',
            'paid_at' => optional($payment->paid_at)->toIso8601String(),
            'created_at' => optional($payment->created_at)->toIso8601String(),
        ];
    }

    protected function authorizeEntityRead(Request $request, string $entity): void
    {
        $role = (string) $request->user()?->role;

        $permissions = [
            'admin' => ['*'],
            'officer' => ['Driver', 'Violation', 'DemeritLedger', 'Notification', 'Payment', 'RiskPrediction', 'RecidivismScore', 'Sanction', 'Appeal', 'ReviewLog', 'Station', 'OfficerAssignment', 'Vehicle'],
            'motorist' => ['Driver', 'Violation', 'DemeritLedger', 'Notification', 'Payment', 'RiskPrediction', 'RecidivismScore', 'Sanction', 'Appeal'],
        ];

        $allowed = $permissions[$role] ?? [];
        if (! in_array('*', $allowed, true) && ! in_array($entity, $allowed, true)) {
            abort(403, 'You do not have permission to read this entity.');
        }
    }

    protected function authorizeEntityCreate(Request $request, string $entity): void
    {
        $role = (string) $request->user()?->role;

        $permissions = [
            'admin' => ['Appeal', 'Station', 'OfficerAssignment', 'Vehicle'],
            'officer' => ['Appeal'],
            'motorist' => ['Appeal'],
        ];

        if (! in_array($entity, $permissions[$role] ?? [], true)) {
            abort(403, 'You do not have permission to create this entity.');
        }
    }

    protected function authorizeEntityUpdate(Request $request, string $entity): void
    {
        $role = (string) $request->user()?->role;

        $permissions = [
            'admin' => ['Driver', 'Sanction', 'Appeal', 'Violation', 'Notification', 'Station', 'OfficerAssignment', 'Vehicle', 'Role', 'ReviewLog'],
            'officer' => ['Appeal', 'Violation', 'Notification'],
            'motorist' => ['Notification'],
        ];

        if (! in_array($entity, $permissions[$role] ?? [], true)) {
            abort(403, 'You do not have permission to update this entity.');
        }
    }

    protected function authorizeFunctionCall(Request $request, string $name): void
    {
        $role = (string) $request->user()?->role;

        $permissions = [
            'admin' => ['processViolation', 'predictAccidentRisk', 'generateComplianceReport', 'generateSystemReadinessReport'],
            'officer' => ['processViolation', 'predictAccidentRisk'],
            'motorist' => [],
        ];

        if (! in_array($name, $permissions[$role] ?? [], true)) {
            abort(403, 'You do not have permission to call this function.');
        }
    }

    protected function scopeRowsForMotorist(Request $request, string $entity, array $rows): array
    {
        $role = (string) $request->user()?->role;
        if ($role !== 'motorist') {
            if ($role === 'officer') {
                $userId = (int) ($request->user()?->id ?? 0);
                if ($entity === 'Notification') {
                    return array_values(array_filter($rows, fn (array $row) => ((int) ($row['recipient_user_id'] ?? 0) === $userId) || (($row['recipient_role'] ?? null) === 'officer')));
                }
            }
            return $rows;
        }

        if ($entity === 'Notification') {
            $userId = (int) ($request->user()?->id ?? 0);
            return array_values(array_filter($rows, fn (array $row) => ((int) ($row['recipient_user_id'] ?? 0) === $userId) || (($row['recipient_role'] ?? null) === 'motorist')));
        }

        $driverId = $this->resolveMotoristDriverId($request);
        if ($driverId === null) {
            return [];
        }

        $fieldMap = [
            'Driver' => 'id',
            'Violation' => 'driver_id',
            'DemeritLedger' => 'driver_id',
            'Payment' => 'driver_id',
            'RiskPrediction' => 'driver_id',
            'RecidivismScore' => 'driver_id',
            'Sanction' => 'driver_id',
            'Appeal' => 'driver_id',
        ];

        $driverField = $fieldMap[$entity] ?? null;
        if ($driverField === null) {
            return [];
        }

        return array_values(array_filter($rows, fn (array $row) => (int) ($row[$driverField] ?? 0) === $driverId));
    }

    protected function resolveMotoristDriverId(Request $request): ?int
    {
        $user = $request->user();
        if (! $user || $user->role !== 'motorist') {
            return null;
        }

        return DriverProfile::where('user_id', $user->id)->value('id');
    }

    protected function sortRows(array $rows, ?string $sort): array
    {
        if (! $sort) {
            return $rows;
        }

        $descending = str_starts_with($sort, '-');
        $key = $descending ? substr($sort, 1) : $sort;

        usort($rows, function (array $a, array $b) use ($key, $descending) {
            $av = $a[$key] ?? null;
            $bv = $b[$key] ?? null;

            if ($av === $bv) {
                return 0;
            }
            if ($av === null) {
                return $descending ? 1 : -1;
            }
            if ($bv === null) {
                return $descending ? -1 : 1;
            }

            if (is_numeric($av) && is_numeric($bv)) {
                return $descending ? ($bv <=> $av) : ($av <=> $bv);
            }

            $cmp = strcmp((string) $av, (string) $bv);
            return $descending ? -$cmp : $cmp;
        });

        return $rows;
    }
}
