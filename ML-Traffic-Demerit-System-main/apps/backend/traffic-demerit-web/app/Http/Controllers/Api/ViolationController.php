<?php

namespace App\Http\Controllers\Api;

use App\Models\DemeritLedgerEntry;
use App\Models\DriverProfile;
use App\Models\OfficerAssignment;
use App\Models\Violation;
use App\Services\DriverStatusSynchronizer;
use App\Services\NotificationService;
use App\Services\RiskInferenceService;
use App\Services\SanctionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * REST CRUD for violations with the closed-loop side effects required by
 * the business process:
 *
 *  - store():  violation -> ledger entry -> balance update -> risk score
 *              (heuristic fallback when ML is down) -> sanction evaluation
 *              -> driver notification. All inside one transaction.
 *  - update(): reverses the original ledger entry, then recreates it with
 *              the edited points and recomputes the balance. Append-only.
 *  - destroy(): soft-deletes the violation and appends a reversing ledger
 *              entry so the ledger is never mutated or deleted.
 *
 * Permissions: admin full; officer creates and may edit their own
 * violations within 24 hours; drivers only read their own.
 */
class ViolationController extends ResourceController
{
    protected string $modelClass = Violation::class;

    protected array $storeRules = [
        'driver_id' => 'required|exists:driver_profiles,id',
        'vehicle_id' => 'required|exists:vehicles,id',
        'offence_type' => 'required|string|max:255',
        'speed_recorded' => 'required|numeric|min:0',
        'speed_limit' => 'required|numeric|min:0',
        'zone_type' => 'required|in:urban,highway,school_zone,residential',
        'weather' => 'required|in:clear,rain,fog,night',
        'location' => 'required|string|max:255',
        'evidence_reference' => 'required|string|max:255',
        'violation_date_time' => 'required|date',
        'officer_assignment_id' => 'nullable|exists:officer_assignments,id',
    ];

    protected array $searchable = ['offense_type', 'location', 'evidence_reference', 'status'];

    protected array $sortable = ['id', 'occurred_at', 'points_assigned', 'offense_type', 'status', 'created_at'];

    protected array $filterable = ['driver_id', 'officer_id', 'offense_type', 'zone_type', 'status'];

    protected array $with = ['driver', 'officer'];

    protected array $showWith = ['payment', 'vehicle', 'officerAssignment.station'];

    /** Simple static point map (mirrors ViolationController). */
    protected array $offensePointsMap = [
        'speeding' => 4,
        'illegal_parking' => 2,
        'dui' => 10,
        'reckless_driving' => 8,
        'red_light' => 5,
        'no_seatbelt' => 2,
        'phone_use' => 3,
    ];

    public function store(Request $request): JsonResponse
    {
        $this->authorizeInstance('create');

        $validated = $request->validate($this->storeRules);

        $riskService = app(RiskInferenceService::class);
        $sanctionService = app(SanctionService::class);
        $notifications = app(NotificationService::class);
        $statusSync = app(DriverStatusSynchronizer::class);

        $result = DB::transaction(function () use ($request, $validated, $riskService, $sanctionService, $notifications, $statusSync) {
            $driver = DriverProfile::findOrFail($validated['driver_id']);
            $vehicleOwner = (int) DB::table('vehicles')->where('id', $validated['vehicle_id'])->value('owner_user_id');

            if ($driver->user_id === null || $vehicleOwner !== (int) $driver->user_id) {
                $this->failValidation(
                    'Selected vehicle does not belong to the selected driver profile.',
                    ['vehicle_id' => ['Selected vehicle does not belong to the selected driver profile.']]
                );
            }

            if ($driver->status !== 'active') {
                $this->failValidation(
                    'This driver profile is locked due to an active sanction.',
                    ['driver_id' => ['This driver profile is locked due to an active sanction.']]
                );
            }

            $officerId = $this->resolveOfficerId($request, $validated);
            $points = $this->offensePointsMap[$validated['offence_type']] ?? 3;
            $occurredAt = Carbon::parse((string) $validated['violation_date_time']);

            $previousViolation = $driver->violations()->whereNull('deleted_at')->latest('occurred_at')->first();
            $daysSinceLast = $previousViolation ? (int) $previousViolation->occurred_at->diffInDays($occurredAt) : 0;

            $violation = Violation::create([
                'driver_id' => $driver->id,
                'vehicle_id' => $validated['vehicle_id'],
                'officer_id' => $officerId,
                'officer_assignment_id' => $validated['officer_assignment_id'] ?? null,
                'offense_type' => $validated['offence_type'],
                'speed_recorded' => $validated['speed_recorded'],
                'posted_speed_limit' => $validated['speed_limit'],
                'zone_type' => $validated['zone_type'],
                'weather_conditions' => $validated['weather'],
                'location' => $validated['location'],
                'evidence_reference' => $validated['evidence_reference'],
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

            // Score the driver (heuristic fallback when the ML service is down).
            $assessment = $riskService->assessDriver($driver->fresh(), [
                'speed_recorded' => $validated['speed_recorded'],
                'zone_type' => $validated['zone_type'],
                'weather_conditions' => $validated['weather'],
            ]);

            $sanction = $sanctionService->evaluate($driver->fresh(), $assessment);
            if ($sanction && $sanction->status !== 'active') {
                $sanction->update(['status' => 'active']);
            }

            // Sync driver status based on active sanctions (single source of truth).
            $statusSync->sync($driver->fresh());

            // Notify the driver that a violation was recorded against them.
            if ($driver->user_id) {
                $notifications->notifyUser(
                    (int) $driver->user_id,
                    'Violation Recorded',
                    sprintf(
                        'A %s violation was recorded against your licence at %s. %d demerit point(s) applied. New balance: %d.',
                        str_replace('_', ' ', $validated['offence_type']),
                        $validated['location'],
                        $points,
                        $newBalance
                    ),
                    [
                        'violation_id' => $violation->id,
                        'points' => $points,
                        'balance' => $newBalance,
                        'risk_score' => (float) $assessment->risk_score,
                        'risk_class' => (string) $assessment->risk_class,
                    ],
                    'violation',
                    (int) $violation->id
                );
            }

            return [
                'violation' => $violation->fresh(['driver', 'officer']),
                'assessment' => $assessment,
                'balance' => $newBalance,
                'points' => $points,
            ];
        });

        $featureSnapshot = $result['assessment']->feature_snapshot ?? [];

        return response()->json([
            'data' => $this->transform($result['violation']),
            'meta' => [
                'demerit_points' => (int) $result['points'],
                'new_balance' => (int) $result['balance'],
                'risk' => [
                    'score' => (float) $result['assessment']->risk_score,
                    'raw_score' => (float) ($featureSnapshot['raw_score'] ?? $result['assessment']->risk_score),
                    'classification' => (string) $result['assessment']->risk_class,
                    'tier' => app(RiskInferenceService::class)->tierForScore((float) $result['assessment']->risk_score),
                    'points_floor' => (float) ($featureSnapshot['points_floor'] ?? 0.0),
                    'floor_applied' => (bool) ($featureSnapshot['floor_applied'] ?? false),
                    'model_version' => (string) $result['assessment']->model_version,
                    'contributions' => $featureSnapshot['explanation_top_factors'] ?? [],
                ],
            ],
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $row = Violation::findOrFail($id);
        $this->authorizeInstance('update', $row);

        $validated = $request->validate([
            'offense_type' => 'sometimes|required|string|max:255',
            'speed_recorded' => 'nullable|numeric|min:0',
            'speed_limit' => 'nullable|numeric|min:0',
            'zone_type' => 'sometimes|required|in:urban,highway,school_zone,residential',
            'weather' => 'sometimes|required|in:clear,rain,fog,night',
            'location' => 'sometimes|required|string|max:255',
            'evidence_reference' => 'sometimes|required|string|max:255',
            'violation_date_time' => 'sometimes|required|date',
            'status' => 'nullable|in:confirmed,disputed',
        ]);

        $updated = DB::transaction(function () use ($row, $validated) {
            $driver = DriverProfile::findOrFail($row->driver_id);
            $oldPoints = (int) $row->points_assigned;

            $offenceType = $validated['offense_type'] ?? $row->offense_type;
            $newPoints = $this->offensePointsMap[$offenceType] ?? 3;

            $occurredAt = isset($validated['violation_date_time'])
                ? Carbon::parse($validated['violation_date_time'])
                : $row->occurred_at;

            // 1) Reverse the original ledger entry (append-only: no mutation).
            $originalEntry = DemeritLedgerEntry::where('driver_id', $driver->id)
                ->where('violation_id', $row->id)
                ->where('points_applied', '>', 0)
                ->latest('id')
                ->first();

            $balance = (int) ($originalEntry?->running_balance ?? $driver->currentBalance());

            if ($originalEntry) {
                $reversed = $balance - $oldPoints;

                DemeritLedgerEntry::create([
                    'driver_id' => $driver->id,
                    'violation_id' => $row->id,
                    'points_applied' => -$oldPoints,
                    'days_since_last_offence' => $originalEntry->days_since_last_offence,
                    'running_balance' => $reversed,
                    'reason' => 'violation edit reversal',
                    'reason_code' => 'violation_edit_reversal',
                ]);

                $balance = $reversed;
            }

            // 2) Apply the edited violation fields.
            $row->fill([
                'offense_type' => $offenceType,
                'speed_recorded' => $validated['speed_recorded'] ?? $row->speed_recorded,
                'posted_speed_limit' => $validated['speed_limit'] ?? $row->posted_speed_limit,
                'zone_type' => $validated['zone_type'] ?? $row->zone_type,
                'weather_conditions' => $validated['weather'] ?? $row->weather_conditions,
                'location' => $validated['location'] ?? $row->location,
                'evidence_reference' => $validated['evidence_reference'] ?? $row->evidence_reference,
                'points_assigned' => $newPoints,
                'status' => $validated['status'] ?? $row->status,
                'occurred_at' => $occurredAt,
            ])->save();

            // 3) Recreate the ledger entry with the new points.
            $newBalance = $balance + $newPoints;

            DemeritLedgerEntry::create([
                'driver_id' => $driver->id,
                'violation_id' => $row->id,
                'points_applied' => $newPoints,
                'days_since_last_offence' => $originalEntry?->days_since_last_offence ?? 0,
                'running_balance' => $newBalance,
                'reason' => 'violation edit re-post',
                'reason_code' => 'violation_edit_repost',
            ]);

            $driver->forceFill(['current_demerit_points' => $newBalance])->save();

            return $row->fresh(['driver', 'officer']);
        });

        return response()->json([
            'data' => $this->transform($updated),
            'meta' => ['new_balance' => (int) DriverProfile::find($updated->driver_id)?->currentBalance()],
        ]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $row = Violation::findOrFail($id);
        $this->authorizeInstance('delete', $row);

        DB::transaction(function () use ($row) {
            $driver = DriverProfile::findOrFail($row->driver_id);
            $points = (int) $row->points_assigned;

            $originalEntry = DemeritLedgerEntry::where('driver_id', $driver->id)
                ->where('violation_id', $row->id)
                ->where('points_applied', '>', 0)
                ->latest('id')
                ->first();

            $balance = (int) ($originalEntry?->running_balance ?? $driver->currentBalance());

            if ($originalEntry) {
                $reversed = $balance - $points;

                DemeritLedgerEntry::create([
                    'driver_id' => $driver->id,
                    'violation_id' => $row->id,
                    'points_applied' => -$points,
                    'days_since_last_offence' => $originalEntry->days_since_last_offence,
                    'running_balance' => $reversed,
                    'reason' => 'violation deleted - reversal',
                    'reason_code' => 'violation_delete_reversal',
                ]);

                $driver->forceFill(['current_demerit_points' => $reversed])->save();
            }

            $row->delete(); // soft delete
        });

        return response()->json(['data' => ['id' => (string) $row->id, 'deleted' => true]]);
    }

    protected function resolveOfficerId(Request $request, array $validated): ?int
    {
        $user = $request->user();

        if (! empty($validated['officer_assignment_id'])) {
            $assignment = OfficerAssignment::find($validated['officer_assignment_id']);
            if ($assignment) {
                return (int) $assignment->officer_user_id;
            }
        }

        if ($user && $user->role === 'officer') {
            return (int) $user->id;
        }

        return null;
    }

    protected function scopeToMotorist($user, $query): void
    {
        $query->where('driver_id', function ($sub) use ($user) {
            $sub->select('id')
                ->from('driver_profiles')
                ->where('user_id', $user->id);
        });
    }

    protected function transform($row): array
    {
        $payload = parent::transform($row);

        $payload['offence_type'] = $payload['offense_type'] ?? null;
        $payload['timestamp'] = $row->occurred_at ? optional($row->occurred_at)->toIso8601String() : null;
        $payload['driver_name'] = $row->driver?->full_name;
        $payload['officer_name'] = $row->officer?->name;

        return $payload;
    }
}
