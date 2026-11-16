<?php

namespace App\Services\Analytics;

class AnalyticsCalculationService
{
    /**
     * Calculate Engagement Rate by Reach:
     * Formula: (likes + comments + shares + saves) / reach * 100
     *
     * Returns an array with calculated rate, formula, denominator, and availability status.
     */
    public function calculateEngagementRateByReach(
        ?float $likes,
        ?float $comments,
        ?float $shares,
        ?float $saves,
        ?float $reach
    ): array {
        if ($reach === null || $reach <= 0) {
            return [
                'rate'         => null,
                'formula'      => '(likes + comments + shares + saves) / reach * 100',
                'denominator'  => 'reach',
                'availability' => 'unavailable',
                'reason'       => 'Reach metric is zero or unavailable.',
            ];
        }

        $interactions = ($likes ?? 0.0) + ($comments ?? 0.0) + ($shares ?? 0.0) + ($saves ?? 0.0);
        $rate = round(($interactions / $reach) * 100, 4);

        return [
            'rate'         => $rate,
            'formula'      => '(likes + comments + shares + saves) / reach * 100',
            'denominator'  => 'reach',
            'availability' => 'available',
            'interactions' => $interactions,
            'reason'       => null,
        ];
    }

    /**
     * Calculate Engagement Rate by Impressions (explicitly distinguished from reach-based calculation).
     */
    public function calculateEngagementRateByImpressions(
        ?float $likes,
        ?float $comments,
        ?float $shares,
        ?float $saves,
        ?float $impressions
    ): array {
        if ($impressions === null || $impressions <= 0) {
            return [
                'rate'         => null,
                'formula'      => '(likes + comments + shares + saves) / impressions * 100',
                'denominator'  => 'impressions',
                'availability' => 'unavailable',
                'reason'       => 'Impressions metric is zero or unavailable.',
            ];
        }

        $interactions = ($likes ?? 0.0) + ($comments ?? 0.0) + ($shares ?? 0.0) + ($saves ?? 0.0);
        $rate = round(($interactions / $impressions) * 100, 4);

        return [
            'rate'         => $rate,
            'formula'      => '(likes + comments + shares + saves) / impressions * 100',
            'denominator'  => 'impressions',
            'availability' => 'available',
            'interactions' => $interactions,
            'reason'       => null,
        ];
    }

    /**
     * Calculate Follower Growth between two snapshots:
     * Absolute Growth = Current Followers - Previous Followers
     * Percentage Growth = ((Current - Previous) / Previous) * 100
     */
    public function calculateFollowerGrowth(?float $currentFollowers, ?float $previousFollowers): array
    {
        if ($currentFollowers === null) {
            return [
                'absolute_growth'   => null,
                'percentage_growth' => null,
                'status'            => 'unavailable',
                'reason'            => 'Current follower count is unavailable.',
            ];
        }

        if ($previousFollowers === null) {
            return [
                'current'           => $currentFollowers,
                'absolute_growth'   => 0.0,
                'percentage_growth' => 0.0,
                'status'            => 'insufficient_history',
                'reason'            => 'No previous baseline snapshot found for growth calculation.',
            ];
        }

        $absolute = $currentFollowers - $previousFollowers;

        if ($previousFollowers <= 0) {
            $percentage = $absolute > 0 ? 100.0 : 0.0;
        } else {
            $percentage = round(($absolute / $previousFollowers) * 100, 2);
        }

        return [
            'current'           => $currentFollowers,
            'previous'          => $previousFollowers,
            'absolute_growth'   => $absolute,
            'percentage_growth' => $percentage,
            'status'            => 'calculated',
            'reason'            => null,
        ];
    }

    /**
     * Calculate percentage change between two metric values safely.
     */
    public function calculatePercentageChange(?float $current, ?float $previous): ?float
    {
        if ($current === null || $previous === null) {
            return null;
        }

        if ($previous == 0.0) {
            return $current > 0 ? 100.0 : 0.0;
        }

        return round((($current - $previous) / abs($previous)) * 100, 2);
    }
}
