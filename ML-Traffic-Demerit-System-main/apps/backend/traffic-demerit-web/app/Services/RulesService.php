<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * RulesService
 * ------------
 * Thin client to the ML service /v1/rules endpoint.
 * Graceful fallback when the ML service is down.
 */
class RulesService
{
    protected string $baseUrl;

    public function __construct()
    {
        $this->baseUrl = config('services.ml_service.url', 'http://127.0.0.1:8001');
    }

    /**
     * Get association rules, optionally filtered.
     */
    public function getRules(?float $minLift = null, ?float $minConfidence = null, ?string $consequent = null, int $limit = 50): ?array
    {
        try {
            $query = [];
            if ($minLift !== null) $query['min_lift'] = $minLift;
            if ($minConfidence !== null) $query['min_confidence'] = $minConfidence;
            if ($consequent !== null) $query['consequent'] = $consequent;
            if ($limit > 0) $query['limit'] = $limit;

            $response = Http::timeout(5)->get("{$this->baseUrl}/v1/rules", $query);
        } catch (\Throwable $e) {
            Log::warning('Rules service unreachable: ' . $e->getMessage());
            return null;
        }

        if (! $response->successful()) {
            Log::warning('Rules service returned error: ' . $response->body());
            return null;
        }

        return $response->json();
    }

    /**
     * Get risk rules only (consequent is outcome=reoffended_180d or payment=late).
     */
    public function getRiskRules(int $limit = 20): ?array
    {
        return $this->getRules(null, null, 'reoffended_180d', $limit);
    }
}
