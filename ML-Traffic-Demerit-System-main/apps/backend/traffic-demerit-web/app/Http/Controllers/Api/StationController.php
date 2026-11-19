<?php

namespace App\Http\Controllers\Api;

use App\Models\Station;

class StationController extends ResourceController
{
    protected string $modelClass = Station::class;

    protected array $storeRules = [
        'station_name' => 'required|string|max:255|unique:stations,station_name',
        'region' => 'required|string|max:255',
        'physical_address' => 'required|string|max:255',
        'contact_phone' => 'nullable|string|max:255',
    ];

    protected array $searchable = ['station_name', 'region', 'physical_address'];

    protected array $sortable = ['id', 'station_name', 'region', 'created_at'];

    protected array $filterable = ['region'];

    protected function updateRules(): array
    {
        return $this->updateRules;
    }

    public function update(\Illuminate\Http\Request $request, int $id): \Illuminate\Http\JsonResponse
    {
        $row = Station::findOrFail($id);
        $this->authorizeInstance('update', $row);

        $validated = $request->validate([
            'station_name' => 'sometimes|required|string|max:255|unique:stations,station_name,' . $row->id,
            'region' => 'sometimes|required|string|max:255',
            'physical_address' => 'sometimes|required|string|max:255',
            'contact_phone' => 'nullable|string|max:255',
        ]);

        $updated = \Illuminate\Support\Facades\DB::transaction(function () use ($row, $validated) {
            $row->fill($validated)->save();
            return $row;
        });

        return response()->json(['data' => $this->transform($updated->fresh($this->with))]);
    }
}
