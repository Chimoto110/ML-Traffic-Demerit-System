<?php

namespace App\Policies;

/** Appeals: drivers file their own; officers/admins review. */
class AppealPolicy extends BasePolicy
{
    protected bool $officerCanCreate = true;
    protected bool $officerCanUpdate = true;
    protected bool $driverCanCreate = true;
    protected bool $driverCanUpdate = false;
    protected bool $driverCanDelete = true;
    protected ?string $ownerDriverIdColumn = 'driver_id';
}
