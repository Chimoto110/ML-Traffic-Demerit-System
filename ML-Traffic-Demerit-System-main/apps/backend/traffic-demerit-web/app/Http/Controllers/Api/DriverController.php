<?php

namespace App\Http\Controllers\Api;

use App\Models\DriverProfile;
use App\Models\User;
use Illuminate\Http\Request;

class DriverController extends ResourceController
{
    protected string $modelClass = DriverProfile::class;

    protected array $storeRules = [
        'user_id' => 'required|exists:users,id',
        'full_name' => 'required|string|max:255',
        'license_no' => 'required|string|max:255|unique:driver_profiles,license_no',
        'license_class' => 'nullable|string|max:255',
        'license_issue_date' => 'nullable|date',
        'license_expiry_date' => 'nullable|date|after_or_equal:license_issue_date',
        'status' => 'nullable|in:active,suspended',
    ];

    protected array $searchable = ['full_name', 'license_no'];

    protected array $sortable = ['id', 'full_name', 'license_no', 'current_demerit_points', 'status', 'created_at'];

    protected array $filterable = ['status', 'account_status', 'user_id'];

    protected array $with = ['user'];

    protected array $showWith = ['violations.payment', 'ledgerEntries', 'riskPredictions'];

    protected function updateRules(): array
    {
        return $this->updateRules;
    }

    protected function createFromPayload(Request $request, array $validated)
    {
        // license_issue_date is NOT NULL without a DB default.
        if (empty($validated['license_issue_date'])) {
            $validated['license_issue_date'] = now()->toDateString();
        }

        return DriverProfile::create($validated);
    }

    public function update(Request $request, int $id): \Illuminate\Http\JsonResponse
    {
        $row = DriverProfile::findOrFail($id);
        $this->authorizeInstance('update', $row);

        $validated = $request->validate([
            'user_id' => 'sometimes|required|exists:users,id',
            'full_name' => 'sometimes|required|string|max:255',
            'license_no' => 'sometimes|required|string|max:255|unique:driver_profiles,license_no,' . $row->id,
            'license_class' => 'nullable|string|max:255',
            'license_issue_date' => 'nullable|date',
            'license_expiry_date' => 'nullable|date',
            'status' => 'nullable|in:active,suspended',
            'account_status' => 'nullable|in:active,locked',
        ]);

        $updated = \Illuminate\Support\Facades\DB::transaction(function () use ($row, $validated) {
            $row->fill($validated)->save();
            return $row;
        });

        return response()->json(['data' => $this->transform($updated->fresh($this->with))]);
    }

    protected function scopeToMotorist($user, $query): void
    {
        $query->where('user_id', $user->id);
    }

    protected function transform($row): array
    {
        $payload = parent::transform($row);

        $payload['email'] = $row->user?->email;
        $payload['current_balance'] = $row->current_demerit_points;

        return $payload;
    }
}
