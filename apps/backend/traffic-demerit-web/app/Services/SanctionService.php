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
 * automated administrative action. This is the "automation loop" the
 * proposal's Gap Analysis (2.4) says existing systems are missing.
 *
 * Thresholds are intentionally simple and centralised here so they can
 * be tuned/defended as a single design decision at your viva.
 */
class SanctionService
{
    protected float $riskLockThreshold;
    protected NotificationService $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
        $this->riskLockThreshold = (float) config('risk.sanction_risk_threshold', 0.7);
    }

    public function evaluate(DriverProfile $driver, RiskAssessment $assessment): ?SanctionAction
    {
        if ((float) $assessment->risk_score < $this->riskLockThreshold) {
            return null; // no action warranted
        }

        $alreadySuspended = $driver->status === 'suspended'
            && $driver->sanctionActions()->where('action_type', 'profile_suspension')->exists();

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
            'risk_score=%.4f crossed threshold=%.2f; top_factors=[%s]',
            (float) $assessment->risk_score,
            $this->riskLockThreshold,
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
            ],
            'sanction',
            (int) $sanction->id
        );

        $this->applyLocalEffect($driver, 'profile_suspension');
        $this->dispatchWebhook($sanction);

        return $sanction;
    }

    protected function applyLocalEffect(DriverProfile $driver, string $actionType): void
    {
        $statusMap = [
            'profile_suspension' => 'suspended',
        ];

        if (isset($statusMap[$actionType])) {
            $driver->update(['status' => $statusMap[$actionType]]);
        }
    }

    /**
     * Simulates the eCitizen/NTSA registry notification described in
     * proposal section 3.2.5 ("automated webhook systems that notify
     * eCitizen or NTSA databases"). Points at a mock endpoint for now —
     * swap MOCK_WEBHOOK_URL for the real integration once available.
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
