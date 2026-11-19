<?php

namespace App\Http\Controllers\Api;

use App\Models\Appeal;
use App\Models\DriverProfile;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AppealController extends ResourceController
{
    protected string $modelClass = Appeal::class;

    protected array $storeRules = [
        'driver_id' => 'required|exists:driver_profiles,id',
        'violation_id' => 'nullable|exists:violations,id',
        'sanction_action_id' => 'nullable|exists:sanction_actions,id',
        'reason' => 'required|string|max:2000',
    ];

    protected array $searchable = ['reason', 'status', 'outcome'];

    protected array $sortable = ['id', 'submitted_at', 'status', 'reviewed_at', 'created_at'];

    protected array $filterable = ['status', 'driver_id', 'violation_id'];

    protected array $with = ['driver', 'violation'];

    protected function updateRules(): array
    {
        return $this->updateRules;
    }

    public function store(Request $request): \Illuminate\Http\JsonResponse
    {
        $this->authorizeInstance('create');

        $validated = $request->validate($this->storeRules);

        $notifications = app(NotificationService::class);

        $user = $request->user();

        if ($user && $user->role === 'motorist') {
            $ownDriverId = DriverProfile::where('user_id', $user->id)->value('id');

            if (! $ownDriverId || (int) $ownDriverId !== (int) $validated['driver_id']) {
                $this->failValidation(
                    'Drivers can only submit appeals for their own profile.',
                    ['driver_id' => ['Drivers can only submit appeals for their own profile.']]
                );
            }
        }

        $appeal = DB::transaction(function () use ($validated) {
            return Appeal::create([
                'driver_id' => $validated['driver_id'],
                'violation_id' => $validated['violation_id'] ?? null,
                'sanction_action_id' => $validated['sanction_action_id'] ?? null,
                'reason' => $validated['reason'],
                'status' => 'pending',
                'submitted_at' => now(),
            ]);
        });

        $driver = DriverProfile::with('user')->find((int) $validated['driver_id']);

        if ($driver?->user_id) {
            $notifications->notifyUser(
                (int) $driver->user_id,
                'Appeal Submitted',
                'Your appeal has been submitted and is awaiting NTSA review.',
                ['appeal_id' => $appeal->id, 'status' => 'pending'],
                'appeal',
                (int) $appeal->id
            );
        }

        $notifications->notifyRole(
            'admin',
            'New Appeal Requires Review',
            'A new appeal has been submitted and is pending administrative review.',
            ['appeal_id' => $appeal->id, 'driver_id' => $appeal->driver_id, 'status' => 'pending'],
            'appeal',
            (int) $appeal->id
        );

        return response()->json(['data' => $this->transform($appeal->fresh(['driver', 'violation']))], 201);
    }

    public function update(Request $request, int $id): \Illuminate\Http\JsonResponse
    {
        $row = Appeal::findOrFail($id);
        $this->authorizeInstance('update', $row);

        $validated = $request->validate([
            'status' => 'required|in:pending,approved,rejected',
            'review_notes' => 'nullable|string|max:2000',
            'outcome' => 'nullable|string|max:255',
        ]);

        $updated = DB::transaction(function () use ($row, $validated, $request) {
            $wasPending = $row->status === 'pending';
            $nowPending = $validated['status'] === 'pending';

            $row->fill([
                'status' => $validated['status'],
                'review_notes' => $validated['review_notes'] ?? $row->review_notes,
                'outcome' => $validated['outcome'] ?? ($validated['status'] === 'pending' ? null : $validated['status']),
                'reviewed_by' => $wasPending && ! $nowPending ? $request->user()?->id : $row->reviewed_by,
                'reviewed_at' => $wasPending && ! $nowPending ? now() : $row->reviewed_at,
                'resolved_at' => $wasPending && ! $nowPending ? now() : $row->resolved_at,
            ])->save();

            return $row;
        });

        return response()->json(['data' => $this->transform($updated->fresh(['driver', 'violation']))]);
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
        $payload['driver_name'] = $row->driver?->full_name;
        $payload['offence_type'] = $row->violation?->offense_type;

        return $payload;
    }
}
