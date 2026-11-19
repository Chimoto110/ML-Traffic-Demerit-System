<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * SegmentationService
 * -------------------
 * Thin client to the ML service /v1/segment and /v1/segments endpoints.
 * Graceful fallback when the ML service is down.
 */
class SegmentationService
{
    protected string $baseUrl;

    public function __construct()
    {
        $this->baseUrl = config('services.ml_service.url', 'http://127.0.0.1:8001');
    }

    /**
     * Get the segment for a driver based on their feature values.
     * Returns the segment label or null if the ML service is unavailable.
     */
    public function segmentDriver(array $features): ?array
    {
        try {
            $response = Http::timeout(5)->post("{$this->baseUrl}/v1/segment", $features);
        } catch (\Throwable $e) {
            Log::warning('Segmentation service unreachable: ' . $e->getMessage());
            return null;
        }

        if (! $response->successful()) {
            Log::warning('Segmentation service returned error: ' . $response->body());
            return null;
        }

        return $response->json();
    }

    /**
     * Get all segment centroid profiles.
     */
    public function getSegments(): ?array
    {
        try {
            $response = Http::timeout(5)->get("{$this->baseUrl}/v1/segments");
        } catch (\Throwable $e) {
            Log::warning('Segments endpoint unreachable: ' . $e->getMessage());
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        return $response->json();
    }
}
