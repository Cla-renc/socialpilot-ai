<?php

namespace App\Services;

use App\Models\Brand;
use App\Models\SchedulingRecommendation;
use Illuminate\Support\Collection;

class BestTimeToPostService
{
    public function __construct(
        protected SchedulingIntelligenceService $intelligence
    ) {}

    /**
     * Get best times for a brand, refreshing database recommendations if empty.
     */
    public function getRecommendationsForBrand(Brand $brand, ?string $platform = null, bool $forceRefresh = false): Collection
    {
        $query = SchedulingRecommendation::query()
            ->where('brand_id', $brand->id)
            ->where('is_active', true)
            ->when($platform, fn ($q) => $q->where('platform', strtolower($platform)));

        if (!$forceRefresh && $query->exists()) {
            return $query->orderBy('confidence', 'desc')->get();
        }

        // Fetch from AI intelligence service
        $recs = $this->intelligence->getBestTimes(
            $brand,
            $platform,
            $brand->settings?->timezone ?: 'UTC'
        );

        $this->persistRecommendations($brand, $recs, $platform);

        return SchedulingRecommendation::query()
            ->where('brand_id', $brand->id)
            ->where('is_active', true)
            ->when($platform, fn ($q) => $q->where('platform', strtolower($platform)))
            ->orderBy('confidence', 'desc')
            ->get();
    }

    /**
     * Persist recommendations into database table.
     */
    public function persistRecommendations(Brand $brand, array $recommendations, ?string $platform = null): void
    {
        // Deactivate previous recommendations for this platform/brand
        SchedulingRecommendation::query()
            ->where('brand_id', $brand->id)
            ->when($platform, fn ($q) => $q->where('platform', strtolower($platform)))
            ->update(['is_active' => false]);

        foreach ($recommendations as $r) {
            SchedulingRecommendation::create([
                'brand_id'       => $brand->id,
                'user_id'        => $brand->user_id,
                'platform'       => strtolower($r['platform'] ?? 'linkedin'),
                'day_of_week'    => strtolower($r['day_of_week'] ?? 'wednesday'),
                'suggested_time' => $r['suggested_time'] ?? '10:00',
                'timezone'       => $r['timezone'] ?? ($brand->settings?->timezone ?: 'UTC'),
                'reason'         => $r['reason'] ?? 'Algorithmic heuristic suggestion.',
                'confidence'     => (float) ($r['confidence'] ?? 0.75),
                'evidence_type'  => $r['evidence_type'] ?? 'heuristic_preliminary',
                'metadata'       => [
                    'data_basis'     => $r['data_basis'] ?? null,
                    'is_preliminary' => (bool) ($r['is_preliminary'] ?? true),
                ],
                'is_active'      => true,
            ]);
        }
    }
}
