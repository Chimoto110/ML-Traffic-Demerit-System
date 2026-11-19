<?php

namespace App\Policies;

use App\Models\User;

/**
 * Shared role rules for the CRUD resources:
 *  - admin: everything
 *  - officer: read everything, create, edit own rows within 24 hours
 *  - driver (motorist): read/update own rows only (row scoping is applied
 *    at query level too)
 */
abstract class BasePolicy
{
    protected bool $officerCanCreate = true;
    protected bool $officerCanUpdate = true;
    protected int $officerUpdateWindowHours = 24;
    protected bool $driverCanCreate = false;
    protected bool $driverCanUpdate = true;
    protected bool $driverCanDelete = true;

    /** Column linking the row to the owning user id. */
    protected ?string $ownerUserIdColumn = null;

    /** Column linking the row to the owning driver_profile id. */
    protected ?string $ownerDriverIdColumn = null;

    public function viewAny(User $user): bool
    {
        return true; // query-level scoping limits what each role sees
    }

    public function view(User $user, object $model): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $this->ownedBy($user, $model) || $user->isOfficer();
    }

    public function create(User $user): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($user->isOfficer()) {
            return $this->officerCanCreate;
        }

        return $this->driverCanCreate;
    }

    public function update(User $user, object $model): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        $owned = $this->ownedBy($user, $model);

        if ($user->isOfficer()) {
            if ($owned) {
                return $this->officerCanUpdate;
            }

            return $this->officerCanUpdate && $this->withinWindow($model);
        }

        return $this->driverCanUpdate && $owned;
    }

    public function delete(User $user, object $model): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($user->isOfficer()) {
            return $this->ownedBy($user, $model);
        }

        return $this->driverCanDelete && $this->ownedBy($user, $model);
    }

    protected function ownedBy(User $user, object $model): bool
    {
        if ($this->ownerUserIdColumn && isset($model->{$this->ownerUserIdColumn})) {
            return (int) $model->{$this->ownerUserIdColumn} === (int) $user->id;
        }

        if ($this->ownerDriverIdColumn && isset($model->{$this->ownerDriverIdColumn})) {
            $driverUserId = \App\Models\DriverProfile::where('id', $model->{$this->ownerDriverIdColumn})->value('user_id');

            return $driverUserId !== null && (int) $driverUserId === (int) $user->id;
        }

        return false;
    }

    /** Officers editing their own rows only: 24 hour window. */
    protected function withinWindow(object $model): bool
    {
        $timestamp = $model->created_at ?? null;

        if (! $timestamp) {
            return false;
        }

        return $timestamp->gt(now()->subHours($this->officerUpdateWindowHours));
    }
}
