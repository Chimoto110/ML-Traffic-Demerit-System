<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\DriverProfile;
use App\Models\Vehicle;
use App\Models\Violation;
use App\Models\DemeritLedgerEntry;
use App\Models\Payment;
use App\Models\SanctionAction;
use App\Models\Appeal;
use App\Models\ReviewLog;
use App\Models\RiskPrediction;
use App\Models\RiskAssessment;

class VerifyForeignKeys extends Command
{
    protected $signature = 'demo:verify-fks';
    protected $description = 'Verify foreign key chain integrity and report orphans';

    public function handle(): int
    {
        $this->info('=== Foreign Key Chain Verification ===');
        $issues = 0;

        // users -> driver_profiles (user_id)
        $orphanDrivers = DriverProfile::whereNotNull('user_id')
            ->whereDoesntHave('user')
            ->get();
        $this->line("driver_profiles with user_id but no matching user: " . $orphanDrivers->count());
        $issues += $orphanDrivers->count();
        foreach ($orphanDrivers as $d) {
            $this->warn("  DriverProfile #{$d->id} ({$d->full_name}) has user_id={$d->user_id} but no User record");
        }

        // driver_profiles -> vehicles (owner_user_id -> users.id)
        $orphanVehicles = Vehicle::whereDoesntHave('owner')
            ->get();
        $this->line("vehicles with no matching owner user: " . $orphanVehicles->count());
        $issues += $orphanVehicles->count();
        foreach ($orphanVehicles as $v) {
            $this->warn("  Vehicle #{$v->id} ({$v->plate_number}) has owner_user_id={$v->owner_user_id} but no User record");
        }

        // violations -> driver_profiles (driver_id)
        $orphanViolations = Violation::whereDoesntHave('driver')
            ->get();
        $this->line("violations with no matching driver: " . $orphanViolations->count());
        $issues += $orphanViolations->count();
        foreach ($orphanViolations as $v) {
            $this->warn("  Violation #{$v->id} has driver_id={$v->driver_id} but no DriverProfile record");
        }

        // violations -> vehicles (vehicle_id)
        $orphanVehViolations = Violation::whereNotNull('vehicle_id')
            ->whereDoesntHave('vehicle')
            ->get();
        $this->line("violations with vehicle_id but no matching vehicle: " . $orphanVehViolations->count());
        $issues += $orphanVehViolations->count();

        // demerit_ledger_entries -> driver_profiles (driver_id)
        $orphanLedger = DemeritLedgerEntry::whereDoesntHave('driver')
            ->get();
        $this->line("ledger entries with no matching driver: " . $orphanLedger->count());
        $issues += $orphanLedger->count();

        // demerit_ledger_entries -> violations (violation_id)
        $orphanLedgerViolation = DemeritLedgerEntry::whereNotNull('violation_id')
            ->whereDoesntHave('violation')
            ->get();
        $this->line("ledger entries with violation_id but no matching violation: " . $orphanLedgerViolation->count());
        $issues += $orphanLedgerViolation->count();

        // payments -> violations (violation_id)
        $orphanPayments = Payment::whereDoesntHave('violation')
            ->get();
        $this->line("payments with no matching violation: " . $orphanPayments->count());
        $issues += $orphanPayments->count();

        // payments -> driver_profiles (driver_id)
        $orphanPayDriver = Payment::whereDoesntHave('driver')
            ->get();
        $this->line("payments with no matching driver: " . $orphanPayDriver->count());
        $issues += $orphanPayDriver->count();

        // payments -> demerit_ledger_entries (ledger_entry_id)
        $orphanPayLedger = Payment::whereNotNull('ledger_entry_id')
            ->whereDoesntHave('ledgerEntry')
            ->get();
        $this->line("payments with ledger_entry_id but no matching ledger entry: " . $orphanPayLedger->count());
        $issues += $orphanPayLedger->count();

        // sanction_actions -> driver_profiles (driver_id)
        $orphanSanctions = SanctionAction::whereDoesntHave('driver')
            ->get();
        $this->line("sanctions with no matching driver: " . $orphanSanctions->count());
        $issues += $orphanSanctions->count();

        // appeals -> driver_profiles (driver_id)
        $orphanAppeals = Appeal::whereDoesntHave('driver')
            ->get();
        $this->line("appeals with no matching driver: " . $orphanAppeals->count());
        $issues += $orphanAppeals->count();

        // appeals -> violations (violation_id)
        $orphanAppealViolation = Appeal::whereNotNull('violation_id')
            ->whereDoesntHave('violation')
            ->get();
        $this->line("appeals with violation_id but no matching violation: " . $orphanAppealViolation->count());
        $issues += $orphanAppealViolation->count();

        // appeals -> sanction_actions (sanction_action_id)
        $orphanAppealSanction = Appeal::whereNotNull('sanction_action_id')
            ->whereDoesntHave('sanction')
            ->get();
        $this->line("appeals with sanction_action_id but no matching sanction: " . $orphanAppealSanction->count());
        $issues += $orphanAppealSanction->count();

        // risk_predictions -> driver_profiles (driver_id)
        $orphanRiskPred = RiskPrediction::whereDoesntHave('driver')
            ->get();
        $this->line("risk_predictions with no matching driver: " . $orphanRiskPred->count());
        $issues += $orphanRiskPred->count();

        // risk_assessments -> driver_profiles (driver_id)
        $orphanRiskAssess = RiskAssessment::whereDoesntHave('driver')
            ->get();
        $this->line("risk_assessments with no matching driver: " . $orphanRiskAssess->count());
        $issues += $orphanRiskAssess->count();

        // Check drivers with no user_id at all
        $noUserDrivers = DriverProfile::whereNull('user_id')->count();
        $this->line("\ndriver_profiles with NULL user_id: $noUserDrivers");
        $issues += $noUserDrivers;

        // Check drivers with no risk prediction
        $driversWithoutPrediction = DriverProfile::whereDoesntHave('riskPredictions')->count();
        $this->line("drivers without any risk_prediction: $driversWithoutPrediction");

        // Check drivers with no risk assessment
        $driversWithoutAssessment = DriverProfile::whereDoesntHave('riskAssessments')->count();
        $this->line("drivers without any risk_assessment: $driversWithoutAssessment");

        // Check violations without ledger entries
        $violationsWithoutLedger = Violation::whereDoesntHave('ledgerEntry')->count();
        $this->line("violations without ledger entry: $violationsWithoutLedger");

        // Check violations without payments
        $violationsWithoutPayment = Violation::whereDoesntHave('payment')->count();
        $this->line("violations without payment: $violationsWithoutPayment");

        $this->info("\n=== Total integrity issues found: $issues ===");
        return $issues > 0 ? 1 : 0;
    }
}
