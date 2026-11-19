<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends ResourceController
{
    protected string $modelClass = User::class;

    protected array $storeRules = [
        'name' => 'required|string|max:255',
        'email' => 'required|email|unique:users,email',
        'password' => 'required|string|min:8',
        'role' => 'required|in:admin,officer,motorist',
        'is_active' => 'nullable|boolean',
    ];

    protected array $searchable = ['name', 'email', 'role'];

    protected array $sortable = ['id', 'name', 'email', 'role', 'created_at', 'registered_on'];

    protected array $filterable = ['role', 'is_active'];

    protected array $with = ['roleRecord'];

    protected array $hiddenFields = ['password', 'remember_token'];

    protected function createFromPayload(Request $request, array $validated)
    {
        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => $validated['role'],
            'role_id' => User::roleNameToId($validated['role']),
            'is_active' => $validated['is_active'] ?? true,
            'registered_on' => now(),
        ]);

        return $user;
    }

    protected function updateFromPayload(Request $request, $row, array $validated)
    {
        $payload = [
            'name' => $validated['name'],
            'email' => $validated['email'],
            'role' => $validated['role'],
            'role_id' => User::roleNameToId($validated['role']),
            'is_active' => $validated['is_active'] ?? $row->is_active,
        ];

        if (! empty($validated['password'])) {
            $payload['password'] = Hash::make($validated['password']);
        }

        $row->forceFill($payload)->save();

        return $row;
    }

    protected function updateRules(): array
    {
        return $this->updateRules;
    }

    public function update(Request $request, int $id): \Illuminate\Http\JsonResponse
    {
        $model = $this->modelClass;
        $row = $model::findOrFail($id);

        $this->authorizeInstance('update', $row);

        $rules = $this->storeRules;
        $rules['email'] = 'required|email|max:255|unique:users,email,' . $row->id;
        $rules['password'] = 'nullable|string|min:8';

        $validated = $request->validate($rules);

        $updated = \Illuminate\Support\Facades\DB::transaction(function () use ($request, $row, $validated) {
            return $this->updateFromPayload($request, $row, $validated);
        });

        return response()->json(['data' => $this->transform($updated->fresh($this->with))]);
    }
}
