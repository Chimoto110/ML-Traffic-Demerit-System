<?php

namespace App\Policies;

use App\Models\User;

/**
 * Violations: admin full; officer create + edit own within 24 hours;
 * drivers never edit violations (read-only via query scoping).
 */
class ViolationPolicy extends BasePolicy
{
    protected bool $officerCanCreate = true;
    protected bool $driverCanCreate = false;
    protected bool $driverCanUpdate = false;
    protected bool $driverCanDelete = false;
    protected ?string $ownerDriverIdColumn = 'driver_id';

    public function update(User $user, object $model): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($user->isOfficer()) {
            // Officers may edit their own violations within 24 hours only.
            $own = (int) ($model->officer_id ?? 0) === (int) $user->id;

            return $own && $this->withinWindow($model);
        }

        return false;
    }

    public function delete(User $user, object $model): bool
    {
        return $user->isAdmin();
    }
}
