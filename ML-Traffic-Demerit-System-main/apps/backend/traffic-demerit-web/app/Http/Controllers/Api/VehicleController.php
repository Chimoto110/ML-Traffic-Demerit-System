<?php

namespace App\Http\Controllers\Api;

use App\Models\Vehicle;
use Illuminate\Http\Request;

class VehicleController extends ResourceController
{
    protected string $modelClass = Vehicle::class;

    protected array $storeRules = [
        // Optional: drivers may omit it (defaults to their own account).
        'owner_user_id' => 'sometimes|nullable|exists:users,id',
        'plate_number' => 'required|string|max:255|unique:vehicles,plate_number',
        'make_model' => 'required|string|max:255',
        'vehicle_class' => 'required|string|max:255',
        'colour' => 'nullable|string|max:255',
        'year_of_manufacture' => 'nullable|integer|min:1950|max:2100',
        'registration_expiry' => 'nullable|date',
    ];

    protected array $searchable = ['plate_number', 'make_model', 'vehicle_class', 'colour'];

    protected array $sortable = ['id', 'plate_number', 'make_model', 'vehicle_class', 'year_of_manufacture', 'created_at'];

    protected array $filterable = ['vehicle_class', 'owner_user_id'];

    protected array $with = ['owner'];

    protected function updateRules(): array
    {
        return $this->updateRules;
    }

    public function update(\Illuminate\Http\Request $request, int $id): \Illuminate\Http\JsonResponse
    {
        $row = Vehicle::findOrFail($id);
        $this->authorizeInstance('update', $row);

        $validated = $request->validate([
            'owner_user_id' => 'sometimes|nullable|exists:users,id',
            'plate_number' => 'sometimes|required|string|max:255|unique:vehicles,plate_number,' . $row->id,
            'make_model' => 'sometimes|required|string|max:255',
            'vehicle_class' => 'sometimes|required|string|max:255',
            'colour' => 'nullable|string|max:255',
            'year_of_manufacture' => 'nullable|integer|min:1950|max:2100',
            'registration_expiry' => 'nullable|date',
        ]);

        // Drivers may only edit their own vehicles and can never reassign
        // ownership to another account.
        if ($request->user() && ! $request->user()->isAdmin()) {
            unset($validated['owner_user_id']);
        }

        $updated = \Illuminate\Support\Facades\DB::transaction(function () use ($row, $validated) {
            $row->fill($validated)->save();
            return $row;
        });

        return response()->json(['data' => $this->transform($updated->fresh($this->with))]);
    }

    protected function createFromPayload(Request $request, array $validated)
    {
        // Only admins may register a vehicle for someone else; every other
        // actor is pinned to their own account (motorist scoping integrity).
        $user = $request->user();
        if (! $user || ! $user->isAdmin()) {
            $validated['owner_user_id'] = $user ? $user->id : null;
        } elseif (empty($validated['owner_user_id'])) {
            $validated['owner_user_id'] = $user->id;
        }

        return Vehicle::create($validated);
    }

    protected function scopeToMotorist($user, $query): void
    {
        $query->where('owner_user_id', $user->id);
    }
}
