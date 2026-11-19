<?php

namespace App\Policies;

/** Payments: create only (M-Pesa by drivers) + void (admin/officer). Never edited or deleted. */
class PaymentPolicy extends BasePolicy
{
    protected bool $officerCanCreate = true;
    protected bool $driverCanCreate = true;
    protected bool $driverCanUpdate = false;
    protected bool $driverCanDelete = false;
    protected ?string $ownerDriverIdColumn = 'driver_id';

    public function update(\App\Models\User $user, object $model): bool
    {
        return false; // void action authorizes separately
    }

    public function delete(\App\Models\User $user, object $model): bool
    {
        return $user->isAdmin() || $user->isOfficer(); // void permission
    }
}
