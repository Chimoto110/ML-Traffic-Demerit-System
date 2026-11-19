<?php

namespace App\Policies;

/** Vehicles: drivers manage their own fleet; officers read. */
class VehiclePolicy extends BasePolicy
{
    protected bool $officerCanCreate = false;
    protected bool $driverCanCreate = true;
    protected bool $driverCanUpdate = true;
    protected bool $driverCanDelete = true;
    protected ?string $ownerUserIdColumn = 'owner_user_id';
}
