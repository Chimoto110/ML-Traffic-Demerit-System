<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\DriverProfile;
use App\Models\DemeritLedgerEntry;
use App\Models\Violation;
use App\Models\RiskPrediction;
use App\Models\RiskAssessment;
use App\Models\User;
use App\Models\Role;

class DemoVerify extends Command
{
    protected $signature = 'demo:verify';
    protected $description = 'Verify demo data integrity: balances, risk records, model counts';

    public function handle(): int
    {
        $this->info('=== Demo Data Verification ===');

        // 1. Check demerit balances match sum of ledger entries
        $this->info("\n1. Demerit Balance Verification");
        $mismatches = 0;
        $drivers = DriverProfile::all();

        foreach ($drivers as $driver) {
            $ledgerSum = DemeritLedgerEntry::where('driver_id', $driver->id)
                ->sum('points_applied');
            $stored = (int) $driver->current_demerit_points;

            if ((int) $ledgerSum !== $stored) {
                $mismatches++;
                $this->warn("  Driver #{$driver->id} ({$driver->full_name}): ledger_sum={$ledgerSum}, stored={$stored}");
            }
        }

        if ($mismatches === 0) {
            $this->line("   <fg=green>All " . $drivers->count() . " driver balances match ledger sums.</>");
        } else {
            $this->error("   $mismatches balance mismatches found!");
        }

        // 2. Check risk records exist for every driver
        $this->info("\n2. Risk Records Verification");
        $driversWithoutPrediction = DriverProfile::whereDoesntHave('riskPredictions')->count();
        $driversWithoutAssessment = DriverProfile::whereDoesntHave('riskAssessments')->count();

        $this->line("   Drivers without risk_prediction: $driversWithoutPrediction");
        $this->line("   Drivers without risk_assessment: $driversWithoutAssessment");

        // 3. Model counts
        $this->info("\n3. Model Counts");
        $counts = [
            'users' => User::count(),
            'roles' => Role::count(),
            'driver_profiles' => DriverProfile::count(),
            'violations' => Violation::count(),
            'demerit_ledger_entries' => DemeritLedgerEntry::count(),
            'risk_predictions' => RiskPrediction::count(),
            'risk_assessments' => RiskAssessment::count(),
        ];

        foreach ($counts as $model => $count) {
            $this->line("   $model: $count");
        }

        // 4. Risk class distribution
        $this->info("\n4. Risk Class Distribution");
        $dist = RiskPrediction::select('risk_class', \Illuminate\Support\Facades\DB::raw('COUNT(*) as cnt'))
            ->groupBy('risk_class')
            ->pluck('cnt', 'risk_class');
        foreach (['low', 'moderate', 'high'] as $cls) {
            $this->line("   $cls: " . ($dist[$cls] ?? 0));
        }

        // 5. Sanction status
        $this->info("\n5. Sanction Summary");
        $this->line("   Active sanctions: " . \App\Models\SanctionAction::where('status', 'active')->count());
        $this->line("   Lifted sanctions: " . \App\Models\SanctionAction::where('status', 'lifted')->count());
        $this->line("   Pending review logs: " . \App\Models\ReviewLog::whereNull('decision')->count());

        $this->info("\n=== Verification Complete ===");
        return $mismatches === 0 ? 0 : 1;
    }
}
