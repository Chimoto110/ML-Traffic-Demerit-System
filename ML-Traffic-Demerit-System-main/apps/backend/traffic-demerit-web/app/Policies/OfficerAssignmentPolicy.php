<?php

namespace App\Policies;

/** Officer assignments: admin only for writes; officers read their own. */
class OfficerAssignmentPolicy extends BasePolicy
{
    protected bool $officerCanCreate = false;
    protected bool $driverCanCreate = false;
    protected bool $driverCanUpdate = false;
    protected bool $driverCanDelete = false;
    protected ?string $ownerUserIdColumn = 'officer_user_id';
}
