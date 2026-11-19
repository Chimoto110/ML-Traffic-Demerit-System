<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\Role;
use App\Models\DriverProfile;
use App\Models\Vehicle;
use App\Models\Station;
use App\Models\OfficerAssignment;
use App\Models\Violation;
use App\Models\DemeritLedgerEntry;
use App\Models\Payment;
use App\Models\SanctionAction;
use App\Models\Appeal;
use App\Models\ReviewLog;
use App\Models\RiskPrediction;
use App\Models\RiskAssessment;
use App\Models\SystemNotification;
use App\Models\LoginAudit;

class VerifyModels extends Command
{
    protected $signature = 'demo:verify-models';
    protected $description = 'Verify all Eloquent models work with the real database';

    public function handle(): int
    {
        $this->info('=== Model Query Test ===');

        $models = [
            'User' => User::class,
            'Role' => Role::class,
            'DriverProfile' => DriverProfile::class,
            'Vehicle' => Vehicle::class,
            'Station' => Station::class,
            'OfficerAssignment' => OfficerAssignment::class,
            'Violation' => Violation::class,
            'DemeritLedgerEntry' => DemeritLedgerEntry::class,
            'Payment' => Payment::class,
            'SanctionAction' => SanctionAction::class,
            'Appeal' => Appeal::class,
            'ReviewLog' => ReviewLog::class,
            'RiskPrediction' => RiskPrediction::class,
            'RiskAssessment' => RiskAssessment::class,
            'SystemNotification' => SystemNotification::class,
            'LoginAudit' => LoginAudit::class,
        ];

        $allOk = true;
        foreach ($models as $name => $class) {
            try {
                $count = $class::count();
                $first = $class::first();
                $this->line("  <fg=green>$name: count=$count</>");
                if ($first) {
                    $cols = array_keys($first->getAttributes());
                    $this->line("    columns: " . implode(', ', $cols));
                }
            } catch (\Throwable $e) {
                $allOk = false;
                $this->error("  $name: ERROR - " . $e->getMessage());
            }
        }

        $this->info("\n=== Relationship Tests ===");

        $user = User::first();
        $this->line("User #{$user->id} ({$user->name}, {$user->role}): driverProfile = " . ($user->driverProfile ? $user->driverProfile->full_name : 'none'));

        $driver = DriverProfile::first();
        $this->line("Driver #{$driver->id} ({$driver->full_name}): user = " . ($driver->user ? $driver->user->name : 'none'));
        $this->line("Driver #{$driver->id} violations: " . $driver->violations()->count());

        $vehicle = Vehicle::first();
        $this->line("Vehicle {$vehicle->plate_number}: owner = " . ($vehicle->owner ? $vehicle->owner->name : 'none'));

        $violation = Violation::first();
        $this->line("Violation #{$violation->id} ({$violation->offense_type}): driver = " . ($violation->driver ? $violation->driver->full_name : 'none') . ", ledger = " . ($violation->ledgerEntry ? 'yes' : 'no') . ", payment = " . ($violation->payment ? 'yes' : 'no'));

        $sanction = SanctionAction::first();
        if ($sanction) {
            $this->line("Sanction #{$sanction->id}: driver = " . ($sanction->driver ? $sanction->driver->full_name : 'none') . ", status = {$sanction->status}");
        }

        $prediction = RiskPrediction::first();
        $this->line("RiskPrediction #{$prediction->id}: driver = " . ($prediction->driver ? $prediction->driver->full_name : 'none') . ", score = {$prediction->risk_score}");

        $this->info("\n=== All model queries completed ===");
        return $allOk ? 0 : 1;
    }
}
