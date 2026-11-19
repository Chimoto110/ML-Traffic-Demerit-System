<?php

namespace App\Policies;

/** Stations: admin only for writes. */
class StationPolicy extends BasePolicy
{
    protected bool $officerCanCreate = false;
    protected bool $officerCanUpdate = false;
    protected bool $driverCanCreate = false;
    protected bool $driverCanUpdate = false;
    protected bool $driverCanDelete = false;
}
