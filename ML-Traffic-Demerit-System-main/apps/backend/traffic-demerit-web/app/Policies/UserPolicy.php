<?php

namespace App\Policies;

/** Users: admin only (officers/drivers must not manage accounts). */
class UserPolicy extends BasePolicy
{
    protected bool $officerCanCreate = false;
    protected bool $officerCanUpdate = false;
    protected bool $driverCanCreate = false;
    protected bool $driverCanUpdate = false;
    protected bool $driverCanDelete = false;
}
