<?php

namespace App\Services;

use App\Models\DriverProfile;
use App\Models\RiskAssessment;
use App\Models\ReviewLog;
use App\Models\SanctionAction;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * SanctionService
 * ----------------
 * Encodes the business rule for turning a risk assessment into an
 * automated administrative action.
 *
 * Graduated sanctions are driven by the FINAL risk score (after points
 * floor) from RiskAssessment:
 *   0.00 - 0.3999: Good standing — no sanction
 *   0.40 - 0.5499: Warning notification (does NOT lock)
 *   0.55 - 0.6999: Mandatory driver training (status "restricted")
 *   >= 0.70:       Profile suspension + human review (status "suspended")
 *
 * Thresholds are centralised in config/risk.php. The trigger_reason
 * explicitly states whether the trigger was a risk score crossing or a
 * points floor crossing.
 *
 * Status changes to driver_profiles.status are delegated to
 * DriverStatusSynchronizer — this class never writes status directly.
 */
class SanctionService
{
    protected float $riskWarningThreshold;
    protected float $riskTrainingThreshold;
    protected float $riskLockThreshold;
    protected NotificationService $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
        $this->riskWarningThreshold = (float) config('risk.warning_risk_threshold', 0.40);
        $this->riskTrainingThreshold = (float) config('risk.training_risk_threshold', 0.55);
        $this->riskLockThreshold = (float) config('risk.sanction_risk_threshold', 0.7);
    }

    /**
     * Evaluate a driver against their risk assessment and apply graduated
     * sanctions if the final_score crosses a threshold.
     *
     * Returns null when no action is warranted.
     */
    public function evaluate(DriverProfile $driver, RiskAssessment $assessment): ?SanctionAction
    {
        $finalScore = (float) $assessment->risk_score; // already final_score from RiskInferenceService
        $featureSnapshot = $assessment->feature_snapshot ?? [];
        $floorApplied = (bool) ($featureSnapshot['floor_applied'] ?? false);
        $pointsFloorScore = (float) ($featureSnapshot['points_floor'] ?? 0.0);

        // --- Graduated sanctions on FINAL score ---
        if ($finalScore < $this->riskWarningThreshold) {
            return null; // no action warranted
        }

        // 0.40 - 0.5499: warning notification
        if ($finalScore < $this->riskTrainingThreshold) {
            return $this->issueWarning($driver, $assessment, $floorApplied, $pointsFloorScore);
        }

        // 0.55 - 0.6999: mandatory driver training
        if ($finalScore < $this->riskLockThreshold) {
            return $this->requireTraining($driver, $assessment, $floorApplied, $pointsFloorScore);
        }

        // >= 0.70: profile_suspension + human review
        return $this->issueSuspension($driver, $assessment, $floorApplied, $pointsFloorScore);
    }

    /**
     * Issue a warning notification for drivers in the [0.40, 0.55) band.
     */
    protected function issueWarning(
        DriverProfile $driver,
        RiskAssessment $assessment,
        bool $floorApplied,
        float $pointsFloorScore
    ): ?SanctionAction {
        // Idempotent: one active warning per driver.
        $alreadyWarned = $driver->sanctionActions()
            ->where('action_type', 'warning')
            ->where('status', 'active')
            ->exists();

        if ($alreadyWarned) {
            return null;
        }

        $reason = $this->buildTriggerReason(
            $assessment->risk_score,
            $this->riskTrainingThreshold,
            $floorApplied,
            $pointsFloorScore,
            'warning'
        );

        $sanction = SanctionAction::create([
            'driver_id' => $driver->id,
            'risk_assessment_id' => $assessment->id,
            'risk_prediction_id' => $assessment->risk_prediction_id,
            'trigger_reason' => $reason,
            'action_type' => 'warning',
            'sanction_type' => 'warning',
            'issued_at' => now(),
            'effective_from' => now(),
            'webhook_status' => 'sent',
        ]);

        $this->notificationService->notifyUser(
            $driver->user_id ? (int) $driver->user_id : null,
            'Driver Risk Warning',
            'Your driving risk score has been elevated. Please drive safely.',
            [
                'driver_id' => $driver->id,
                'risk_score' => (float) $assessment->risk_score,
                'risk_class' => (string) $assessment->risk_class,
                'floor_applied' => $floorApplied,
            ],
            'warning',
            (int) $sanction->id
        );

        return $sanction;
    }

    /**
     * Require mandatory driver training for drivers in the [0.55, 0.70) band.
     */
    protected function requireTraining(
        DriverProfile $driver,
        RiskAssessment $assessment,
        bool $floorApplied,
        float $pointsFloorScore
    ): ?SanctionAction {
        // Idempotent: one active restriction per driver.
        $alreadyRestricted = $driver->sanctionActions()
            ->where('action_type', 'profile_restriction')
            ->where('status', 'active')
            ->exists();

        if ($alreadyRestricted) {
            return null;
        }

        $reason = $this->buildTriggerReason(
            $assessment->risk_score,
            $this->riskLockThreshold,
            $floorApplied,
            $pointsFloorScore,
            'mandatory_training'
        );

        $sanction = SanctionAction::create([
            'driver_id' => $driver->id,
            'risk_assessment_id' => $assessment->id,
            'risk_prediction_id' => $assessment->risk_prediction_id,
            'trigger_reason' => $reason,
            'action_type' => 'profile_restriction',
            'sanction_type' => 'mandatory_training',
            'fine_amount' => 0,
            'issued_at' => now(),
            'effective_from' => now(),
            'webhook_status' => 'sent',
        ]);

        $this->notificationService->notifyUser(
            $driver->user_id ? (int) $driver->user_id : null,
            'Mandatory Driver Training Required',
            'Your risk score requires mandatory driver training. Please contact NTSA.',
            [
                'driver_id' => $driver->id,
                'risk_score' => (float) $assessment->risk_score,
                'risk_class' => (string) $assessment->risk_class,
                'floor_applied' => $floorApplied,
            ],
            'training',
            (int) $sanction->id
        );

        return $sanction;
    }

    /**
     * Issue profile suspension for drivers with final_score >= 0.70.
     * Creates a review_logs row for mandatory human review.
     */
    protected function issueSuspension(
        DriverProfile $driver,
        RiskAssessment $assessment,
        bool $floorApplied,
        float $pointsFloorScore
    ): ?SanctionAction {
        // Idempotent: one active suspension per driver.
        $alreadySuspended = $driver->sanctionActions()
            ->where('action_type', 'profile_suspension')
            ->where('status', 'active')
            ->exists();

        if ($alreadySuspended) {
            return null;
        }

        $topFactors = collect($assessment->feature_snapshot['explanation_top_factors'] ?? [])
            ->take(3)
            ->map(function ($factor) {
                $feature = $factor['feature'] ?? 'unknown_feature';
                $contribution = $factor['contribution'] ?? 0;

                return "{$feature} ({$contribution})";
            })
            ->implode(', ');

        $reason = sprintf(
            'risk_score=%.4f crossed threshold=%.2f; %s; top_factors=[%s]',
            (float) $assessment->risk_score,
            $this->riskLockThreshold,
            $floorApplied ? "points_floor={$pointsFloorScore}" : 'risk_crossed',
            $topFactors !== '' ? $topFactors : 'none'
        );

        $sanction = SanctionAction::create([
            'driver_id' => $driver->id,
            'risk_assessment_id' => $assessment->id,
            'risk_prediction_id' => $assessment->risk_prediction_id,
            'trigger_reason' => $reason,
            'action_type' => 'profile_suspension',
            'sanction_type' => 'profile_suspension',
            'issued_at' => now(),
            'effective_from' => now(),
            'webhook_status' => 'pending',
        ]);

        $latestViolation = $driver->violations()->latest('occurred_at')->first();
        ReviewLog::create([
            'sanction_action_id' => $sanction->id,
            'officer_assignment_id' => $latestViolation?->officer_assignment_id,
            'decision' => null,
            'notes' => 'Auto-created for mandatory human review of ML-triggered sanction.',
            'reviewed_at' => null,
        ]);

        $this->notificationService->notifyUser(
            $driver->user_id ? (int) $driver->user_id : null,
            'Driver Profile Suspended',
            'An automated sanction has suspended your profile pending human review.',
            [
                'driver_id' => $driver->id,
                'risk_score' => (float) $assessment->risk_score,
                'risk_class' => (string) $assessment->risk_class,
                'floor_applied' => $floorApplied,
            ],
            'sanction',
            (int) $sanction->id
        );

        // Status change is delegated to DriverStatusSynchronizer — not here.
        $this->dispatchWebhook($sanction);

        return $sanction;
    }

    /**
     * Build a human-readable trigger reason that documents whether the
     * sanction was triggered by the risk score or the points floor.
     */
    protected function buildTriggerReason(
        float $riskScore,
        float $threshold,
        bool $floorApplied,
        float $pointsFloorScore,
        string $sanctionType
    ): string {
        if ($floorApplied) {
            return sprintf(
                '%s: points floor score=%.4f crossed %s threshold=%.2f',
                $sanctionType,
                $pointsFloorScore,
                $sanctionType,
                $threshold
            );
        }

        return sprintf(
            '%s: risk_score=%.4f crossed threshold=%.2f',
            $sanctionType,
            $riskScore,
            $threshold
        );
    }

    /**
     * Simulates the eCitizen/NTSA registry notification.
     */
    protected function dispatchWebhook(SanctionAction $sanction): void
    {
        $url = config('services.ntsa_webhook.url', 'http://127.0.0.1:8002/mock-ntsa-webhook');

        try {
            $response = Http::timeout(5)->post($url, [
                'driver_id' => $sanction->driver_id,
                'action_type' => $sanction->action_type,
                'reason' => $sanction->trigger_reason,
                'timestamp' => now()->toIso8601String(),
            ]);

            $sanction->update([
                'webhook_status' => $response->successful() ? 'sent' : 'failed',
                'webhook_response' => $response->json() ?? ['raw' => $response->body()],
            ]);

            $this->notificationService->notifyRole(
                'admin',
                $response->successful() ? 'Sanction Synced to Registry' : 'Sanction Sync Failed',
                $response->successful()
                    ? 'Sanction update was successfully sent to the external registry endpoint.'
                    : 'Sanction update failed to sync to the external registry endpoint.',
                [
                    'sanction_id' => $sanction->id,
                    'driver_id' => $sanction->driver_id,
                    'webhook_status' => $sanction->fresh()->webhook_status,
                ],
                'sanction',
                (int) $sanction->id
            );
        } catch (\Throwable $e) {
            Log::warning('Sanction webhook failed: ' . $e->getMessage());
            $sanction->update(['webhook_status' => 'failed']);

            $this->notificationService->notifyRole(
                'admin',
                'Sanction Sync Failed',
                'Sanction update failed before reaching the external registry endpoint.',
                [
                    'sanction_id' => $sanction->id,
                    'driver_id' => $sanction->driver_id,
                    'error' => $e->getMessage(),
                ],
                'sanction',
                (int) $sanction->id
            );
        }
    }
}
