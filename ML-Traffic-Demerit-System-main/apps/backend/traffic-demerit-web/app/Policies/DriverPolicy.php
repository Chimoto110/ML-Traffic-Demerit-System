<?php

namespace App\Policies;

/** Driver profiles: officers may create; drivers edit their own profile. */
class DriverPolicy extends BasePolicy
{
    protected bool $officerCanCreate = true;
    protected bool $driverCanCreate = false;
    protected bool $driverCanUpdate = true;
    protected bool $driverCanDelete = false;
    protected ?string $ownerUserIdColumn = 'user_id';
}
