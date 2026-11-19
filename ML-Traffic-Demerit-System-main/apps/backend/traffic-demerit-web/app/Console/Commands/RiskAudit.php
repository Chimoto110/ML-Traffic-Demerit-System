<?php

namespace App\Console\Commands;

use App\Models\DriverProfile;
use App\Models\RiskPrediction;
use App\Services\RiskInferenceService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RiskAudit extends Command
{
    protected $signature = 'risk:audit';
    protected $description = 'Audit risk scores: distinct count, min/max/stddev, Spearman correlation with demerit points';

    public function handle(): int
    {
        $totalDrivers = DriverProfile::count();
        $totalPredictions = RiskPrediction::count();

        $this->info("=== Risk Audit ===");
        $this->info("Total drivers: $totalDrivers");
        $this->info("Total risk predictions: $totalPredictions");

        // Distinct score count
        $distinctScores = RiskPrediction::distinct('risk_score')->count('risk_score');
        $this->info("Distinct risk scores: $distinctScores");

        if ($distinctScores < 50) {
            $this->error("FAIL: Distinct scores ($distinctScores) < 50. Risk scores are too uniform.");
        } else {
            $this->info("PASS: Distinct scores >= 50.");
        }

        // Min/max/stddev
        $stats = RiskPrediction::select(
            DB::raw('MIN(risk_score) as min_score'),
            DB::raw('MAX(risk_score) as max_score'),
            DB::raw('AVG(risk_score) as avg_score'),
            DB::raw('STDDEV(risk_score) as stddev_score')
        )->first();

        $this->info(sprintf(
            "  Min: %.4f, Max: %.4f, Mean: %.4f, StdDev: %.4f",
            $stats->min_score,
            $stats->max_score,
            $stats->avg_score,
            $stats->stddev_score
        ));

        // Spearman correlation between demerit points and risk score
        // Spearman = Pearson correlation on ranks. We compute it manually.
        $rows = RiskPrediction::join('driver_profiles', 'risk_predictions.driver_id', '=', 'driver_profiles.id')
            ->select('risk_predictions.risk_score', 'driver_profiles.current_demerit_points')
            ->whereNotNull('driver_profiles.current_demerit_points')
            ->get();

        $n = $rows->count();
        if ($n < 2) {
            $this->warn("Not enough data for Spearman correlation.");
            return 0;
        }

        // Rank both variables
        $scores = $rows->pluck('risk_score')->sort()->values();
        $points = $rows->pluck('current_demerit_points')->sort()->values();

        $scoreRanks = $scores->flip(); // value -> rank (0-based, ties get same rank via array_flip behavior)
        $pointRanks = $points->flip();

        // Handle ties: assign average rank
        $rankScores = $this->rankWithTies($scores->values()->all());
        $rankPoints = $this->rankWithTies($points->values()->all());

        $dSquared = 0.0;
        foreach ($rows as $row) {
            $d = $rankScores[$row->risk_score] - $rankPoints[$row->current_demerit_points];
            $dSquared += $d * $d;
        }

        $spearman = 1 - (6 * $dSquared) / ($n * ($n * $n - 1));

        $this->info(sprintf(
            "  Spearman(points, score) = %.4f (n=%d)",
            $spearman,
            $n
        ));

        if ($spearman < 0.6) {
            $this->error("FAIL: Spearman correlation ($spearman) < 0.6. Risk scores not well correlated with demerit points.");
        } else {
            $this->info("PASS: Spearman correlation >= 0.6.");
        }

        // Count drivers whose score equals >5 others (too many identical scores)
        $scoreCounts = RiskPrediction::select('risk_score', DB::raw('COUNT(*) as cnt'))
            ->groupBy('risk_score')
            ->having('cnt', '>', 5)
            ->get();

        $this->info("Scores shared by >5 drivers: " . $scoreCounts->count());
        if ($scoreCounts->count() > 0) {
            $this->warn("  Some scores are shared by more than 5 drivers (potential uniformity issue).");
        }

        // Summary
        $allPass = $distinctScores >= 50 && $spearman >= 0.6;
        $this->newLine();
        $this->info($allPass
            ? '<fg=green>AUDIT PASSED</>'
            : '<fg=red>AUDIT FAILED</>');

        return $allPass ? 0 : 1;
    }

    /**
     * Compute ranks with ties (average rank for tied values).
     */
    protected function rankWithTies(array $values): array
    {
        $sorted = $values;
        sort($sorted);

        $ranks = [];
        $n = count($sorted);
        $i = 0;
        while ($i < $n) {
            $j = $i;
            while ($j < $n - 1 && $sorted[$j + 1] === $sorted[$i]) {
                $j++;
            }
            $avgRank = (($i + 1) + ($j + 1)) / 2.0; // 1-based average rank
            for ($k = $i; $k <= $j; $k++) {
                $ranks[$sorted[$k]] = $avgRank;
            }
            $i = $j + 1;
        }

        return $ranks;
    }
}
