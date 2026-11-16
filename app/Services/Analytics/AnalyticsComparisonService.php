<?php

namespace App\Services\Analytics;

use App\Models\AnalyticsSnapshot;
use App\Models\Brand;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AnalyticsComparisonService
{
    public function __construct(
        protected AnalyticsCalculationService $calculationService,
        protected AnalyticsAggregationService $aggregationService
    ) {}

    /**
     * Compare a brand's performance between the current period and previous matching period.
     */
    public function comparePeriods(Brand|int $brand, string $period = 'last_30_days', ?string $customStart = null, ?string $customEnd = null): array
    {
        $brand = $brand instanceof Brand ? $brand : Brand::findOrFail($brand);
        $tz = $brand->timezone ?? 'UTC';
        $currentRange = $this->aggregationService->resolveDateRange($period, $customStart, $customEnd, $tz);

        // Compute prior matching period
        $durationSeconds = $currentRange['end']->diffInSeconds($currentRange['start']);
        $priorEnd = $currentRange['start']->copy()->subSecond();
        $priorStart = $priorEnd->copy()->subSeconds($durationSeconds);

        // Fetch current period totals
        $currentTotals = AnalyticsSnapshot::where('brand_id', $brand->id)
            ->whereBetween('collected_at', [$currentRange['start'], $currentRange['end']])
            ->groupBy('metric_name')
            ->select('metric_name', DB::raw('SUM(metric_value) as total'))
            ->pluck('total', 'metric_name')
            ->toArray();

        // Fetch prior period totals
        $priorTotals = AnalyticsSnapshot::where('brand_id', $brand->id)
            ->whereBetween('collected_at', [$priorStart, $priorEnd])
            ->groupBy('metric_name')
            ->select('metric_name', DB::raw('SUM(metric_value) as total'))
            ->pluck('total', 'metric_name')
            ->toArray();

        // Check if prior data exists
        $hasPriorData = !empty($priorTotals);

        $comparison = [];
        $allMetrics = array_unique(array_merge(array_keys($currentTotals), array_keys($priorTotals)));

        foreach ($allMetrics as $metric) {
            $curr = isset($currentTotals[$metric]) ? (float) $currentTotals[$metric] : 0.0;
            $prev = isset($priorTotals[$metric]) ? (float) $priorTotals[$metric] : null;

            $pctChange = $hasPriorData ? $this->calculationService->calculatePercentageChange($curr, $prev ?? 0.0) : null;

            $comparison[$metric] = [
                'current'           => $curr,
                'previous'          => $prev,
                'percentage_change' => $pctChange,
                'trend'             => $pctChange === null ? 'no_baseline' : ($pctChange > 0 ? 'up' : ($pctChange < 0 ? 'down' : 'flat')),
            ];
        }

        return [
            'brand_id'        => $brand->id,
            'period'          => $period,
            'current_period'  => [
                'start' => $currentRange['start']->toIso8601String(),
                'end'   => $currentRange['end']->toIso8601String(),
            ],
            'prior_period'    => [
                'start' => $priorStart->toIso8601String(),
                'end'   => $priorEnd->toIso8601String(),
            ],
            'has_prior_data'  => $hasPriorData,
            'metrics'         => $comparison,
        ];
    }

    /**
     * Week-over-week performance comparison for a brand.
     */
    public function weekOverWeek(Brand $brand): array
    {
        return $this->comparePeriods($brand, 'last_7_days');
    }

    /**
     * Month-over-month performance comparison for a brand.
     */
    public function monthOverMonth(Brand $brand): array
    {
        return $this->comparePeriods($brand, 'last_30_days');
    }
}
