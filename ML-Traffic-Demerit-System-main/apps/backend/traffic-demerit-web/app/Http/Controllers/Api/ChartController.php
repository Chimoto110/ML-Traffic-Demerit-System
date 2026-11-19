<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Appeal;
use App\Models\DriverProfile;
use App\Models\Payment;
use App\Models\RiskPrediction;
use App\Models\SanctionAction;
use App\Models\Violation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Every chart series below is a plain MySQL aggregate - no Python, no ML
 * service. The dashboards therefore render real data even while the ML
 * microservice is down. Responses are never hardcoded.
 */
class ChartController extends Controller
{
    /**
     * GET /api/charts/admin - the full admin analytics payload.
     */
    public function admin(Request $request): JsonResponse
    {
        return response()->json([
            'risk_distribution' => $this->riskDistribution(),
            'monthly_violations_fines' => $this->monthlyViolationsAndFines(),
            'violations_by_type' => $this->violationsByType(),
            'violations_by_station' => $this->violationsByStation(),
            'payments_summary' => $this->paymentsSummary(),
            'sanctions_by_status' => $this->sanctionsByStatus(),
            'appeals_by_status' => $this->appealsByStatus(),
            'watch_list' => $this->watchList(),
            'leaderboard' => $this->leaderboard(),
            'generated_at' => now()->toIso8601String(),
        ]);
    }

    /**
     * GET /api/charts/ml-insights - elbow/silhouette curves, PCA scatter,
     * segment profiles, association rules (table + bubble series).
     */
    public function mlInsights(): JsonResponse
    {
        return response()->json([
            'elbow' => $this->elbowSeries(),
            'silhouette' => $this->silhouetteSeries(),
            'pca_scatter' => $this->pcaScatter(),
            'segment_profiles' => $this->segmentProfiles(),
            'association_rules' => $this->associationRules(),
            'rules_bubble' => $this->rulesBubbleSeries(),
            'generated_at' => now()->toIso8601String(),
        ]);
    }

    /**
     * GET /api/charts/driver - demerit gauge + points over time (own ledger).
     */
    public function driver(Request $request): JsonResponse
    {
        $driver = DriverProfile::where('user_id', $request->user()->id)->first();

        if (! $driver) {
            return response()->json([
                'gauge' => null,
                'points_over_time' => [],
                'message' => 'No driver profile linked to this account.',
            ]);
        }

        $balance = (int) $driver->currentBalance();

        return response()->json([
            'gauge' => [
                'balance' => $balance,
                'max_points' => 100,
                'threshold_warning' => 40,
                'threshold_suspension' => 70,
                'percentage' => min(100, round($balance / 100 * 100, 1)),
                'classification' => $balance >= 70 ? 'high' : ($balance >= 40 ? 'moderate' : 'low'),
                'status' => $driver->status,
            ],
            'points_over_time' => $this->driverPointsOverTime((int) $driver->id),
            'recent_ledger' => $this->recentLedger((int) $driver->id),
            'generated_at' => now()->toIso8601String(),
        ]);
    }

    /**
     * GET /api/charts/officer - this officer's own violations over time.
     */
    public function officer(Request $request): JsonResponse
    {
        $officerId = (int) $request->user()->id;

        return response()->json([
            'violations_over_time' => $this->officerViolationsOverTime($officerId),
            'by_type' => $this->officerViolationsByType($officerId),
            'totals' => [
                'violations' => Violation::where('officer_id', $officerId)->count(),
                'points_issued' => (int) Violation::where('officer_id', $officerId)->sum('points_assigned'),
                'drivers_affected' => Violation::where('officer_id', $officerId)->distinct('driver_id')->count('driver_id'),
            ],
            'generated_at' => now()->toIso8601String(),
        ]);
    }

    /* ------------------------------------------------------------------ */
    /* Admin series                                                        */
    /* ------------------------------------------------------------------ */

    protected function riskDistribution(): array
    {
        // Latest prediction per driver, grouped by risk class.
        return RiskPrediction::query()
            ->select('risk_class', DB::raw('COUNT(*) as drivers'))
            ->where('id', function ($sub) {
                // Alias the inner table: without it, the bare name
                // "risk_predictions" inside the subquery binds to the INNER
                // table, making the correlation always true (MAX(id) over
                // every row -> a single bogus group).
                $sub->selectRaw('MAX(rp2.id) FROM risk_predictions rp2 WHERE rp2.driver_id = risk_predictions.driver_id');
            })
            ->groupBy('risk_class')
            ->orderBy('risk_class')
            ->get()
            ->map(fn ($row) => [
                'risk_class' => $row->risk_class,
                'drivers' => (int) $row->drivers,
            ])
            ->values()
            ->all();
    }

    protected function monthlyViolationsAndFines(): array
    {
        $violations = DB::table('violations')
            ->whereNull('violations.deleted_at')
            ->selectRaw("DATE_FORMAT(occurred_at, '%Y-%m') as month, COUNT(*) as violations")
            ->groupBy('month')
            ->orderBy('month')
            ->pluck('violations', 'month');

        $fines = DB::table('payments')
            ->whereNull('payments.deleted_at')
            ->whereNotNull('paid_at')
            ->selectRaw("DATE_FORMAT(paid_at, '%Y-%m') as month, SUM(amount_paid) as fines, COUNT(*) as payments")
            ->groupBy('month')
            ->orderBy('month')
            ->get()
            ->keyBy('month');

        $months = $violations->keys()->merge($fines->keys())->unique()->sort()->values();

        return $months->map(function ($month) use ($violations, $fines) {
            return [
                'month' => $month,
                'violations' => (int) ($violations[$month] ?? 0),
                'fines' => round((float) ($fines[$month]->fines ?? 0), 2),
                'payments' => (int) ($fines[$month]->payments ?? 0),
            ];
        })->values()->all();
    }

    protected function violationsByType(): array
    {
        return Violation::query()
            ->select('offense_type', DB::raw('COUNT(*) as total'), DB::raw('SUM(points_assigned) as points'))
            ->groupBy('offense_type')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => [
                'type' => $row->offense_type,
                'total' => (int) $row->total,
                'points' => (int) $row->points,
            ])
            ->values()
            ->all();
    }

    protected function violationsByStation(): array
    {
        return DB::table('violations')
            ->whereNull('violations.deleted_at')
            ->leftJoin('officer_assignments', 'violations.officer_assignment_id', '=', 'officer_assignments.id')
            ->leftJoin('stations', 'officer_assignments.station_id', '=', 'stations.id')
            ->selectRaw('COALESCE(stations.station_name, "Unassigned") as station, COUNT(*) as total')
            ->groupBy('station')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => [
                'station' => $row->station,
                'total' => (int) $row->total,
            ])
            ->values()
            ->all();
    }

    protected function paymentsSummary(): array
    {
        $byStatus = Payment::query()
            ->select('status', DB::raw('COUNT(*) as total'), DB::raw('SUM(amount_paid) as paid'), DB::raw('SUM(amount) as invoiced'))
            ->groupBy('status')
            ->get()
            ->map(fn ($row) => [
                'status' => $row->status,
                'total' => (int) $row->total,
                'paid' => round((float) $row->paid, 2),
                'invoiced' => round((float) $row->invoiced, 2),
            ])
            ->values()
            ->all();

        $byMethod = Payment::query()
            ->select('method', DB::raw('COUNT(*) as total'), DB::raw('SUM(amount_paid) as paid'))
            ->groupBy('method')
            ->get()
            ->map(fn ($row) => [
                'method' => $row->method,
                'total' => (int) $row->total,
                'paid' => round((float) $row->paid, 2),
            ])
            ->values()
            ->all();

        return [
            'by_status' => $byStatus,
            'by_method' => $byMethod,
            'totals' => [
                'payments' => Payment::count(),
                'collected' => round((float) Payment::sum('amount_paid'), 2),
                'invoiced' => round((float) Payment::sum('amount'), 2),
                'outstanding' => round((float) (Payment::sum('amount') - Payment::sum('amount_paid')), 2),
            ],
        ];
    }

    protected function sanctionsByStatus(): array
    {
        return SanctionAction::query()
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->get()
            ->map(fn ($row) => ['status' => $row->status, 'total' => (int) $row->total])
            ->values()
            ->all();
    }

    protected function appealsByStatus(): array
    {
        return Appeal::query()
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->get()
            ->map(fn ($row) => ['status' => $row->status, 'total' => (int) $row->total])
            ->values()
            ->all();
    }

    protected function watchList(): array
    {
        return DriverProfile::with(['user:id,name,email'])
            ->orderByDesc('current_demerit_points')
            ->limit(10)
            ->get()
            ->map(function ($driver) {
                $latest = RiskPrediction::where('driver_id', $driver->id)->latest('predicted_at')->first();

                return [
                    'driver_id' => (string) $driver->id,
                    'driver_name' => $driver->full_name,
                    'email' => $driver->user?->email,
                    'demerit_balance' => (int) $driver->current_demerit_points,
                    'status' => $driver->status,
                    'risk_score' => $latest ? (float) $latest->risk_score : null,
                    'risk_class' => $latest?->risk_class,
                ];
            })
            ->values()
            ->all();
    }

    protected function leaderboard(): array
    {
        return DB::table('violations')
            ->whereNull('violations.deleted_at')
            ->join('users', 'violations.officer_id', '=', 'users.id')
            ->select(
                'users.id as officer_id',
                'users.name as officer_name',
                DB::raw('COUNT(*) as violations'),
                DB::raw('SUM(violations.points_assigned) as points_issued'),
                DB::raw('COUNT(DISTINCT violations.driver_id) as drivers')
            )
            ->whereNotNull('violations.officer_id')
            ->groupBy('users.id', 'users.name')
            ->orderByDesc('violations')
            ->limit(10)
            ->get()
            ->map(fn ($row) => [
                'officer_id' => (string) $row->officer_id,
                'officer_name' => $row->officer_name,
                'violations' => (int) $row->violations,
                'points_issued' => (int) $row->points_issued,
                'drivers' => (int) $row->drivers,
            ])
            ->values()
            ->all();
    }

    /* ------------------------------------------------------------------ */
    /* ML insight series (all read MySQL, not the ML service)              */
    /* ------------------------------------------------------------------ */

    protected function elbowSeries(): array
    {
        $model = DB::table('segment_models')->latest('trained_at')->first();

        if (! $model) {
            return [];
        }

        // Preferred: wcss_by_k JSON column (regenerated by the ML step).
        $byK = $this->decodeJsonColumn($model->wcss_by_k ?? null);

        if ($byK === []) {
            $byK = $this->decodeJsonColumn($model->elbow_data ?? null);
        }

        if ($byK === [] && isset($model->wcss, $model->k)) {
            $byK = [(string) $model->k => (float) $model->wcss];
        }

        return collect($byK)
            ->map(fn ($wcss, $k) => ['k' => (int) $k, 'wcss' => round((float) $wcss, 4)])
            ->sortBy('k')
            ->values()
            ->all();
    }

    protected function silhouetteSeries(): array
    {
        $model = DB::table('segment_models')->latest('trained_at')->first();

        if (! $model) {
            return [];
        }

        $byK = $this->decodeJsonColumn($model->silhouette_by_k ?? null);

        if ($byK === [] && isset($model->silhouette)) {
            // Fallback: only the chosen k was stored by the original pipeline.
            $byK = [(string) $model->k => $model->silhouette];
        }

        return collect($byK)
            ->map(fn ($score, $k) => ['k' => (int) $k, 'silhouette' => round((float) $score, 4)])
            ->sortBy('k')
            ->values()
            ->all();
    }

    protected function pcaScatter(): array
    {
        return DB::table('driver_segments')
            ->join('driver_profiles', 'driver_segments.driver_profile_id', '=', 'driver_profiles.id')
            ->whereNotNull('driver_segments.pca_x')
            ->whereNotNull('driver_segments.pca_y')
            ->select(
                'driver_segments.driver_profile_id as driver_id',
                'driver_profiles.full_name as driver_name',
                'driver_segments.segment_id',
                'driver_segments.segment_label',
                'driver_segments.pca_x',
                'driver_segments.pca_y'
            )
            ->limit(500)
            ->get()
            ->map(fn ($row) => [
                'driver_id' => (string) $row->driver_id,
                'driver_name' => $row->driver_name,
                'segment_id' => (int) $row->segment_id,
                'segment_label' => $row->segment_label,
                'pca_x' => round((float) $row->pca_x, 4),
                'pca_y' => round((float) $row->pca_y, 4),
            ])
            ->values()
            ->all();
    }

    protected function segmentProfiles(): array
    {
        return DB::table('driver_segments')
            ->join('ml_driver_features', 'driver_segments.driver_profile_id', '=', 'ml_driver_features.driver_profile_id')
            ->select(
                'driver_segments.segment_id',
                'driver_segments.segment_label',
                DB::raw('COUNT(*) as drivers'),
                DB::raw('ROUND(AVG(ml_driver_features.violation_count), 2) as avg_violation_count'),
                DB::raw('ROUND(AVG(ml_driver_features.cumulative_active_demerit_points), 2) as avg_demerit_points'),
                DB::raw('ROUND(AVG(ml_driver_features.avg_speed_over_limit), 2) as avg_speed_over_limit'),
                DB::raw('ROUND(AVG(ml_driver_features.unpaid_fine_ratio), 3) as avg_unpaid_ratio'),
                DB::raw('ROUND(AVG(ml_driver_features.total_fines_kes), 2) as avg_fines_kes'),
                DB::raw('ROUND(AVG(ml_driver_features.days_since_last_offense), 1) as avg_days_since_offense'),
                DB::raw('ROUND(AVG(ml_driver_features.share_at_night), 3) as avg_share_at_night')
            )
            ->groupBy('driver_segments.segment_id', 'driver_segments.segment_label')
            ->orderBy('driver_segments.segment_id')
            ->get()
            ->map(fn ($row) => [
                'segment_id' => (int) $row->segment_id,
                'segment_label' => $row->segment_label,
                'drivers' => (int) $row->drivers,
                'avg_violation_count' => (float) $row->avg_violation_count,
                'avg_demerit_points' => (float) $row->avg_demerit_points,
                'avg_speed_over_limit' => (float) $row->avg_speed_over_limit,
                'avg_unpaid_ratio' => (float) $row->avg_unpaid_ratio,
                'avg_fines_kes' => (float) $row->avg_fines_kes,
                'avg_days_since_offense' => (float) $row->avg_days_since_offense,
                'avg_share_at_night' => (float) $row->avg_share_at_night,
            ])
            ->values()
            ->all();
    }

    protected function associationRules(): array
    {
        return DB::table('association_rules')
            ->orderByDesc('lift')
            ->limit(50)
            ->get()
            ->map(fn ($row) => [
                'id' => (int) $row->id,
                'antecedent' => $row->antecedent,
                'consequent' => $row->consequent,
                'support' => round((float) $row->support, 4),
                'confidence' => round((float) $row->confidence, 4),
                'lift' => round((float) $row->lift, 4),
                'coverage' => $row->coverage !== null ? round((float) $row->coverage, 4) : null,
                'composite_score' => $row->composite_score !== null ? round((float) $row->composite_score, 4) : null,
                'plain_english' => $row->plain_english,
                'rule_family' => $row->rule_family,
            ])
            ->values()
            ->all();
    }

    protected function rulesBubbleSeries(): array
    {
        // Bubble chart: x = support, y = confidence, size = lift.
        return DB::table('association_rules')
            ->orderByDesc('lift')
            ->limit(50)
            ->get()
            ->map(fn ($row) => [
                'id' => (int) $row->id,
                'label' => $row->antecedent . ' => ' . $row->consequent,
                'x' => round((float) $row->support, 4),
                'y' => round((float) $row->confidence, 4),
                'size' => round((float) $row->lift, 4),
            ])
            ->values()
            ->all();
    }

    /* ------------------------------------------------------------------ */
    /* Driver / officer series                                             */
    /* ------------------------------------------------------------------ */

    protected function driverPointsOverTime(int $driverId): array
    {
        return DB::table('demerit_ledger_entries')
            ->where('driver_id', $driverId)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get(['created_at', 'points_applied', 'running_balance', 'reason_code', 'reason'])
            ->map(fn ($row) => [
                'date' => optional(\Illuminate\Support\Carbon::parse($row->created_at))->toDateString(),
                'points_applied' => (int) $row->points_applied,
                'running_balance' => (int) $row->running_balance,
                'reason' => $row->reason,
                'reason_code' => $row->reason_code,
            ])
            ->values()
            ->all();
    }

    protected function recentLedger(int $driverId): array
    {
        return DB::table('demerit_ledger_entries')
            ->where('driver_id', $driverId)
            ->orderByDesc('id')
            ->limit(10)
            ->get(['id', 'violation_id', 'points_applied', 'running_balance', 'reason', 'reason_code', 'created_at'])
            ->map(fn ($row) => [
                'id' => (int) $row->id,
                'violation_id' => $row->violation_id !== null ? (string) $row->violation_id : null,
                'points_applied' => (int) $row->points_applied,
                'running_balance' => (int) $row->running_balance,
                'reason' => $row->reason,
                'reason_code' => $row->reason_code,
                'date' => optional(\Illuminate\Support\Carbon::parse($row->created_at))->toDateTimeString(),
            ])
            ->values()
            ->all();
    }

    protected function officerViolationsOverTime(int $officerId): array
    {
        return Violation::query()
            ->selectRaw("DATE_FORMAT(occurred_at, '%Y-%m') as month, COUNT(*) as total, SUM(points_assigned) as points")
            ->where('officer_id', $officerId)
            ->groupBy('month')
            ->orderBy('month')
            ->get()
            ->map(fn ($row) => [
                'month' => $row->month,
                'total' => (int) $row->total,
                'points' => (int) $row->points,
            ])
            ->values()
            ->all();
    }

    protected function officerViolationsByType(int $officerId): array
    {
        return Violation::query()
            ->select('offense_type', DB::raw('COUNT(*) as total'))
            ->where('officer_id', $officerId)
            ->groupBy('offense_type')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => ['type' => $row->offense_type, 'total' => (int) $row->total])
            ->values()
            ->all();
    }

    protected function decodeJsonColumn($value): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        if (is_array($value)) {
            return $value;
        }

        $decoded = json_decode((string) $value, true);

        return is_array($decoded) ? $decoded : [];
    }
}
