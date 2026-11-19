<?php

namespace App\Services;

use App\Models\DriverProfile;
use App\Models\SanctionAction;

/**
 * DriverStatusSynchronizer
 * ------------------------
 * The SINGLE source of truth for driver_profiles.status. No other class
 * may write driver_profiles.status directly.
 *
 * Status is derived ONLY from active sanction_actions:
 *   - active profile_suspension  → 'suspended'
 *   - active profile_restriction → 'restricted'
 *   - otherwise                  → 'active'
 *
 * Called after every sanction create/lift, appeal approval,
 * payment completion, and violation edit/delete.
 */
class DriverStatusSynchronizer
{
    public function sync(DriverProfile $driver): void
    {
        $activeSuspension = $driver->sanctionActions()
            ->where('action_type', 'profile_suspension')
            ->where('status', 'active')
            ->exists();

        $activeRestriction = $driver->sanctionActions()
            ->where('action_type', 'profile_restriction')
            ->where('status', 'active')
            ->exists();

        $newStatus = $activeSuspension
            ? 'suspended'
            : ($activeRestriction ? 'restricted' : 'active');

        if ((string) $driver->status !== $newStatus) {
            $driver->update(['status' => $newStatus]);
        }
    }

    /**
     * Sync status for all drivers (used by repair-status command).
     */
    public function syncAll(): array
    {
        $results = ['synced' => 0, 'changed' => 0];

        \App\Models\DriverProfile::cursor()->each(function (DriverProfile $driver) use (&$results) {
            $results['synced']++;
            $oldStatus = $driver->status;
            $this->sync($driver);
            if ((string) $oldStatus !== $driver->fresh()->status) {
                $results['changed']++;
            }
        });

        return $results;
    }
}
