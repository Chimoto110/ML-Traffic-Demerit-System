<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * Generic REST controller with:
 *  - pagination (page, per_page <= 100)
 *  - search (q -> searchable columns)
 *  - sorting (sort=field or sort=-field, whitelisted)
 *  - filters (filter[field]=value, whitelisted)
 *  - a uniform {data, meta} envelope
 *  - policy checks for every ability
 *  - DB transactions around every write
 *  - soft deletes for destroy() unless the controller overrides it
 */
abstract class ResourceController extends Controller
{
    /** Fully-qualified Eloquent model class. */
    protected string $modelClass;

    /** Validation rules for store(). */
    protected array $storeRules = [];

    /** Validation rules for update() (usually storeRules without required). */
    protected array $updateRules = [];

    /** Columns matched by the `q` search parameter. */
    protected array $searchable = [];

    /** Columns allowed in `sort=` (prefix with - for descending). */
    protected array $sortable = ['id'];

    /** Columns allowed in `filter[column]=value`. */
    protected array $filterable = [];

    /** Eager loads for index/show. */
    protected array $with = [];

    /** Relations to eager load only on show(). */
    protected array $showWith = [];

    /** Default sort when none requested. */
    protected string $defaultSort = '-id';

    /** Payload keys removed before returning a row. */
    protected array $hiddenFields = [];

    public function index(Request $request): JsonResponse
    {
        $this->authorizeInstance('viewAny');

        $model = $this->modelClass;

        $query = $model::query()->with($this->with);

        $this->applyMotoristScope($request, $query);
        $this->applySearch($request, $query);
        $this->applyFilters($request, $query);

        $perPage = min(100, max(1, (int) $request->input('per_page', 15)));
        $page = max(1, (int) $request->input('page', 1));
        $sort = $request->input('sort', $this->defaultSort);

        $this->applySort($query, $sort);

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'data' => collect($paginator->items())->map(fn ($row) => $this->transform($row))->values()->all(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
                'sort' => $sort,
            ],
        ]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $model = $this->modelClass;
        $row = $model::with(array_values(array_unique([...$this->with, ...$this->showWith])))->findOrFail($id);

        $this->authorizeInstance('view', $row);

        return response()->json(['data' => $this->transform($row)]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeInstance('create');

        $validated = $request->validate($this->storeRules);

        $row = DB::transaction(function () use ($request, $validated) {
            return $this->createFromPayload($request, $validated);
        });

        return response()->json(['data' => $this->transform($row)], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $model = $this->modelClass;
        $row = $model::findOrFail($id);

        $this->authorizeInstance('update', $row);

        $validated = $request->validate($this->updateRules ?: $this->storeRules);

        $updated = DB::transaction(function () use ($request, $row, $validated) {
            return $this->updateFromPayload($request, $row, $validated);
        });

        return response()->json(['data' => $this->transform($updated->fresh($this->with))]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $model = $this->modelClass;
        $row = $model::findOrFail($id);

        $this->authorizeInstance('delete', $row);

        DB::transaction(function () use ($row) {
            $this->beforeDelete($row);
            $row->delete(); // soft delete when the model uses SoftDeletes
        });

        return response()->json(['data' => ['id' => (string) $row->id, 'deleted' => true]]);
    }

    /* ------------------------------------------------------------------ */
    /* Override points                                                     */
    /* ------------------------------------------------------------------ */

    protected function createFromPayload(Request $request, array $validated)
    {
        $model = $this->modelClass;

        return $model::create($validated);
    }

    protected function updateFromPayload(Request $request, $row, array $validated)
    {
        $row->fill($validated)->save();

        return $row;
    }

    protected function beforeDelete($row): void
    {
        // Hook for reversal logic (e.g. violation ledger reversal).
    }

    protected function transform($row): array
    {
        $payload = $row->toArray();

        foreach ($this->hiddenFields as $field) {
            unset($payload[$field]);
        }

        return $payload;
    }

    /**
     * Drivers (motorists) only ever see their own rows. Officers and
     * admins see everything. Override for resource-specific scoping.
     */
    protected function applyMotoristScope(Request $request, $query): void
    {
        $user = $request->user();

        if ($user && $user->role === 'motorist') {
            $this->scopeToMotorist($user, $query);
        }
    }

    protected function scopeToMotorist($user, $query): void
    {
        // Default: no additional scoping (overridden per resource).
    }

    /* ------------------------------------------------------------------ */
    /* Query helpers                                                       */
    /* ------------------------------------------------------------------ */

    protected function applySearch(Request $request, $query): void
    {
        $term = trim((string) $request->input('q', ''));

        if ($term === '' || $this->searchable === []) {
            return;
        }

        $query->where(function ($outer) use ($term) {
            foreach ($this->searchable as $column) {
                $outer->orWhere($column, 'like', '%' . $term . '%');
            }
        });
    }

    protected function applyFilters(Request $request, $query): void
    {
        $filters = $request->input('filter', []);

        if (! is_array($filters)) {
            return;
        }

        foreach ($filters as $column => $value) {
            if (! in_array($column, $this->filterable, true) || $value === '' || $value === null) {
                continue;
            }

            if (is_array($value)) {
                $query->whereIn($column, $value);
            } else {
                $query->where($column, $value);
            }
        }
    }

    protected function applySort($query, string $sort): void
    {
        $descending = str_starts_with($sort, '-');
        $column = ltrim($sort, '-');

        if (! in_array($column, $this->sortable, true)) {
            $descending = str_starts_with($this->defaultSort, '-');
            $column = ltrim($this->defaultSort, '-');
        }

        if (! Schema::hasColumn($query->getModel()->getTable(), $column) && $column !== 'id') {
            $column = 'id';
        }

        $query->orderBy($column, $descending ? 'desc' : 'asc');
    }

    /**
     * Resolves the policy ability against the resource's policy. Falls back
     * to a role-based default when no policy class exists.
     */
    protected function authorizeInstance(string $ability, $instance = null): void
    {
        $model = $this->modelClass;
        $policy = app(\Illuminate\Contracts\Auth\Access\Gate::class);

        if ($policy->getPolicyFor($model)) {
            $policy->authorize($ability, $instance ?? $model);
            return;
        }

        $user = request()->user();

        if (! $user) {
            abort(401, 'Unauthenticated.');
        }

        if ($user->role === 'admin') {
            return;
        }

        // Conservative default: officers may read and create; drivers may
        // read their own rows only (scoped by the query).
        if (in_array($ability, ['viewAny', 'view'], true)) {
            return;
        }

        if ($user->role === 'officer' && $ability === 'create') {
            return;
        }

        abort(403, 'You do not have permission to perform this action.');
    }

    protected function rulesPresent(array $rules): array
    {
        $out = [];

        foreach ($rules as $key => $rule) {
            $out[$key] = collect($rule)
                ->filter(fn ($r) => $r !== 'required' && $r !== 'required_if:status,pending')
                ->values()
                ->all();
        }

        return $out;
    }

    protected function failValidation(string $message, array $errors): void
    {
        throw ValidationException::withMessages($errors ?: ['payload' => [$message]]);
    }
}
