<?php

namespace App\Http\Controllers\Api;

use App\Models\OfficerAssignment;

class AssignmentController extends ResourceController
{
    protected string $modelClass = OfficerAssignment::class;

    protected array $storeRules = [
        'officer_user_id' => 'required|exists:users,id',
        'station_id' => 'required|exists:stations,id',
        'badge_number' => 'required|string|max:255',
        'shift_type' => 'required|in:day,night,swing',
        'assignment_start_date' => 'required|date',
        'assignment_end_date' => 'nullable|date|after_or_equal:assignment_start_date',
    ];

    protected array $searchable = ['badge_number', 'shift_type'];

    protected array $sortable = ['id', 'badge_number', 'shift_type', 'assignment_start_date', 'created_at'];

    protected array $filterable = ['station_id', 'officer_user_id', 'shift_type'];

    protected array $with = ['officer', 'station'];

    protected function updateRules(): array
    {
        return $this->updateRules;
    }

    public function update(\Illuminate\Http\Request $request, int $id): \Illuminate\Http\JsonResponse
    {
        $row = OfficerAssignment::findOrFail($id);
        $this->authorizeInstance('update', $row);

        $validated = $request->validate([
            'officer_user_id' => 'sometimes|required|exists:users,id',
            'station_id' => 'sometimes|required|exists:stations,id',
            'badge_number' => 'sometimes|required|string|max:255',
            'shift_type' => 'sometimes|required|in:day,night,swing',
            'assignment_start_date' => 'sometimes|required|date',
            'assignment_end_date' => 'nullable|date',
        ]);

        $updated = \Illuminate\Support\Facades\DB::transaction(function () use ($row, $validated) {
            $row->fill($validated)->save();
            return $row;
        });

        return response()->json(['data' => $this->transform($updated->fresh($this->with))]);
    }
}
