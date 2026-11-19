<?php

namespace App\Services;

use App\Models\DemeritLedgerEntry;
use App\Models\DriverProfile;
use App\Models\Violation;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * DriverRiskFeatureBuilder
 * ------------------------
 * Computes per-driver risk features from ALL of a driver's non-deleted
 * violations (not just the latest). These features feed both the ML model
 * and the heuristic_v2 fallback, ensuring the score reflects the full
 * enforcement history rather than a single snapshot.
 *
 * All features are non-demographic: no gender, region, occupation, or age
 * proxies are ever used.
 */
class DriverRiskFeatureBuilder
{
    // Severity weights per offence type (mirrors OffenseCatalog).
    protected array $severityWeights = [
        'dui' => 5,
        'reckless_driving' => 4,
        'red_light' => 3,
        'speeding' => 2,
        'phone_use' => 2,
        'no_seatbelt' => 1,
        'illegal_parking' => 1,
        'driving_without_license' => 4,
        'overloading' => 3,
        'no_insurance' => 3,
    ];

    protected array $severeOffenceTypes = [
        'dui', 'reckless_driving', 'red_light', 'driving_without_license',
    ];

    /**
     * Build the complete feature vector for a driver as of "now".
     *
     * @return array<string, mixed> All features needed by the ML model and
     *                              heuristic_v2, plus metadata for the risk
     *                              response object.
     */
    public function build(DriverProfile $driver): array
    {
        $violations = $driver->violations()->whereNull('deleted_at')->orderBy('occurred_at', 'asc')->get();
        $ledgerEntries = $driver->ledgerEntries()->orderBy('id', 'asc')->get();

        $now = Carbon::now();
        $violationCount = $violations->count();

        // --- Time-windowed counts ---
        $count12m = $violations->filter(fn ($v) => $v->occurred_at->gte($now->copy()->subYear()))->count();
        $count90d = $violations->filter(fn ($v) => $v->occurred_at->gte($now->copy()->subDays(90)))->count();

        // --- Severity-weighted points (last 12 months) ---
        $weightedPoints12m = 0.0;
        $severeCount = 0;
        foreach ($violations as $v) {
            if ($v->occurred_at->gte($now->copy()->subYear())) {
                $weight = $this->severityWeights[$v->offense_type] ?? 1;
                $weightedPoints12m += $weight * (float) $v->points_assigned;
            }
            if (in_array($v->offense_type, $this->severeOffenceTypes)) {
                $severeCount++;
            }
        }
        $severeOffenceShare = $violationCount > 0 ? $severeCount / $violationCount : 0.0;

        // --- Cumulative active demerit points (ledger balance) ---
        $cumulativePoints = $driver->currentBalance();

        // --- Speed stats (only speeding violations with speed data) ---
        $speedingViolations = $violations->filter(fn ($v) => $v->offense_type === 'speeding' && $v->speed_recorded !== null);
        $avgSpeedOverLimit = 0.0;
        $maxSpeedOverLimit = 0.0;
        if ($speedingViolations->isNotEmpty()) {
            $excesses = $speedingViolations->map(fn ($v) => max(0, (float) $v->speed_recorded - (float) ($v->posted_speed_limit ?? 80)));
            $avgSpeedOverLimit = $excesses->avg();
            $maxSpeedOverLimit = $excesses->max();
        }

        // --- Days since last offense ---
        $lastViolation = $violations->last();
        $daysSinceLastOffense = $lastViolation
            ? (int) $lastViolation->occurred_at->diffInDays($now)
            : 365;

        // --- Mean gap between offences ---
        $meanGapBetweenOffences = 0.0;
        if ($violationCount >= 2) {
            $gaps = [];
            $sorted = $violations->sortBy('occurred_at');
            $prev = null;
            foreach ($sorted as $v) {
                if ($prev !== null) {
                    $gaps[] = $prev->occurred_at->diffInDays($v->occurred_at);
                }
                $prev = $v;
            }
            $meanGapBetweenOffences = !empty($gaps) ? (array_sum($gaps) / count($gaps)) : 0.0;
        }

        // --- Trend: last 90d vs prior 90d ---
        $last90d = $violations->filter(fn ($v) => $v->occurred_at->gte($now->copy()->subDays(90)))->count();
        $prior90d = $violations->filter(fn ($v) => $v->occurred_at->gte($now->copy()->subDays(180)) && $v->occurred_at->lt($now->copy()->subDays(90)))->count();
        $trend = $prior90d > 0 ? ($last90d - $prior90d) / $prior90d : ($last90d > 0 ? 1.0 : 0.0);

        // --- Time/zone/weather shares ---
        $shareNight = $violationCount > 0
            ? $violations->filter(fn ($v) => $v->weather_conditions === 'night')->count() / $violationCount
            : 0.0;
        $shareSchoolZone = $violationCount > 0
            ? $violations->filter(fn ($v) => $v->zone_type === 'school_zone')->count() / $violationCount
            : 0.0;
        $shareBadWeather = $violationCount > 0
            ? $violations->filter(fn ($v) => in_array($v->weather_conditions, ['rain', 'fog', 'storm']))->count() / $violationCount
            : 0.0;

        // --- Payment behaviour ---
        $unpaidFineRatio = 0.0;
        $avgPaymentDelayDays = 0.0;
        $ledgerWithPayments = $ledgerEntries->filter(fn ($le) => $le->violation_id !== null);
        $totalViolationFines = 0.0;
        $paidFines = 0.0;
        $paymentDelays = [];

        foreach ($violations as $v) {
            $payment = $v->payment;
            $fine = $this->fineForOffence($v->offense_type);
            $totalViolationFines += $fine;

            if ($payment && $payment->status === 'completed') {
                $paidFines += $fine;
                if ($payment->paid_at && $v->occurred_at) {
                    $paymentDelays[] = $v->occurred_at->diffInDays($payment->paid_at);
                }
            }
        }

        if ($totalViolationFines > 0) {
            $unpaidFineRatio = max(0.0, min(1.0, 1.0 - ($paidFines / $totalViolationFines)));
        }
        if (count($paymentDelays) > 0) {
            $avgPaymentDelayDays = array_sum($paymentDelays) / count($paymentDelays);
        }

        // --- Appeals ---
        $appealsLost = $driver->appeals()->where('status', 'rejected')->count();

        // --- New driver flag ---
        $newDriver = $violationCount < 2;

        return [
            // Core features (match ML model input order)
            'days_since_last_offense' => $daysSinceLastOffense,
            'speed_recorded' => $lastViolation?->speed_recorded ?? 0.0,
            'zone_type' => $lastViolation?->zone_type ?? 'urban',
            'weather_conditions' => $lastViolation?->weather_conditions ?? 'clear',
            'cumulative_active_demerit_points' => (int) $cumulativePoints,
            'past_fine_payment_delays_days' => round($avgPaymentDelayDays, 2),

            // Extended segmentation features
            'violation_count' => $violationCount,
            'violation_count_12m' => $count12m,
            'violation_count_90d' => $count90d,
            'weighted_points_12m' => round($weightedPoints12m, 2),
            'severe_offence_share' => round($severeOffenceShare, 4),
            'avg_speed_over_limit' => round($avgSpeedOverLimit, 2),
            'max_speed_over_limit' => round($maxSpeedOverLimit, 2),
            'mean_gap_between_offences' => round($meanGapBetweenOffences, 2),
            'trend' => round($trend, 4),
            'share_night' => round($shareNight, 4),
            'share_school_zone' => round($shareSchoolZone, 4),
            'share_bad_weather' => round($shareBadWeather, 4),
            'unpaid_fine_ratio' => round($unpaidFineRatio, 4),
            'avg_payment_delay_days' => round($avgPaymentDelayDays, 2),
            'appeals_lost' => $appealsLost,
            'new_driver' => (int) $newDriver,

            // Metadata
            'violation_ids' => $violations->pluck('id')->toArray(),
            'last_violation_id' => $lastViolation?->id,
            'total_violations' => $violationCount,
        ];
    }

    /**
     * Fine amount for an offence type (mirrors OffenseCatalog / PaymentController).
     */
    protected function fineForOffence(string $offenceType): float
    {
        return match ($offenceType) {
            'speeding' => 5000.0,
            'red_light' => 3000.0,
            'dui' => 15000.0,
            'no_seatbelt' => 1000.0,
            'phone_use' => 2000.0,
            'reckless_driving' => 7000.0,
            'illegal_parking' => 500.0,
            'driving_without_license' => 12000.0,
            'overloading' => 4000.0,
            'no_insurance' => 8000.0,
            default => 1000.0,
        };
    }
}
