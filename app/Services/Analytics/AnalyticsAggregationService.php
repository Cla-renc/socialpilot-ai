<?php

namespace App\Services\Analytics;

use App\Models\AnalyticsSnapshot;
use App\Models\AudienceInsight;
use App\Models\Brand;
use App\Models\PostAnalytic;
use App\Models\PublishingAttempt;
use App\Models\SocialAccount;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AnalyticsAggregationService
{
    public function __construct(
        protected AnalyticsCalculationService $calculationService
    ) {}

    /**
     * Resolve date boundary timestamps based on filter string or custom range.
     */
    public function resolveDateRange(string $period = 'last_30_days', ?string $customStart = null, ?string $customEnd = null, string $timezone = 'UTC'): array
    {
        $tz = in_array($timezone, timezone_identifiers_list(), true) ? $timezone : 'UTC';
        $now = Carbon::now($tz);

        switch ($period) {
            case 'today':
                $start = $now->copy()->startOfDay()->utc();
                $end = $now->copy()->endOfDay()->utc();
                break;
            case 'yesterday':
                $start = $now->copy()->subDay()->startOfDay()->utc();
                $end = $now->copy()->subDay()->endOfDay()->utc();
                break;
            case 'last_7_days':
                $start = $now->copy()->subDays(6)->startOfDay()->utc();
                $end = $now->copy()->endOfDay()->utc();
                break;
            case 'last_90_days':
                $start = $now->copy()->subDays(89)->startOfDay()->utc();
                $end = $now->copy()->endOfDay()->utc();
                break;
            case 'custom':
                $start = $customStart ? Carbon::parse($customStart, $tz)->startOfDay()->utc() : $now->copy()->subDays(29)->startOfDay()->utc();
                $end = $customEnd ? Carbon::parse($customEnd, $tz)->endOfDay()->utc() : $now->copy()->endOfDay()->utc();
                break;
            case 'last_30_days':
            default:
                $start = $now->copy()->subDays(29)->startOfDay()->utc();
                $end = $now->copy()->endOfDay()->utc();
                break;
        }

        return [
            'period'    => $period,
            'start'     => $start,
            'end'       => $end,
            'timezone'  => $tz,
        ];
    }

    /**
     * Level 1: Overall account aggregation (all brands belonging to user).
     */
    public function getUserOverview(User $user, string $period = 'last_30_days', ?string $customStart = null, ?string $customEnd = null): array
    {
        $brandIds = $user->brands()->pluck('id')->toArray();
        $dateRange = $this->resolveDateRange($period, $customStart, $customEnd);

        $connectedAccountsCount = SocialAccount::whereIn('brand_id', $brandIds)
            ->where('status', 'connected')
            ->count();

        // Latest followers per connected account
        $followers = (float) AnalyticsSnapshot::whereIn('brand_id', $brandIds)
            ->where('metric_name', 'followers')
            ->whereIn('id', function ($query) use ($brandIds) {
                $query->select(DB::raw('MAX(id)'))
                    ->from('analytics_snapshots')
                    ->whereIn('brand_id', $brandIds)
                    ->where('metric_name', 'followers')
                    ->groupBy('social_account_id');
            })
            ->sum('metric_value');

        // Aggregated metric sums for the period
        $periodSums = AnalyticsSnapshot::whereIn('brand_id', $brandIds)
            ->whereBetween('collected_at', [$dateRange['start'], $dateRange['end']])
            ->groupBy('metric_name')
            ->select('metric_name', DB::raw('SUM(metric_value) as total'))
            ->pluck('total', 'metric_name')
            ->toArray();

        // Platform breakdown
        $platformBreakdown = AnalyticsSnapshot::whereIn('brand_id', $brandIds)
            ->whereBetween('collected_at', [$dateRange['start'], $dateRange['end']])
            ->groupBy('platform', 'metric_name')
            ->select('platform', 'metric_name', DB::raw('SUM(metric_value) as total'))
            ->get()
            ->groupBy('platform')
            ->map(function ($items) {
                return $items->pluck('total', 'metric_name')->toArray();
            })
            ->toArray();

        // Post analytics summary
        $postsSummary = PostAnalytic::whereIn('brand_id', $brandIds)
            ->whereBetween('collected_at', [$dateRange['start'], $dateRange['end']])
            ->groupBy('metric_name')
            ->select('metric_name', DB::raw('SUM(metric_value) as total'))
            ->pluck('total', 'metric_name')
            ->toArray();

        $impressions = (float) ($periodSums['impressions'] ?? $postsSummary['impressions'] ?? 0);
        $reach = (float) ($periodSums['reach'] ?? $postsSummary['reach'] ?? 0);
        $engagement = (float) ($periodSums['engagement'] ?? $periodSums['accounts_engaged'] ?? $postsSummary['engagement'] ?? 0);
        $views = (float) ($periodSums['video_views'] ?? $postsSummary['views'] ?? 0);

        // Engagement calculation
        $engCalc = $this->calculationService->calculateEngagementRateByReach(
            $postsSummary['likes'] ?? 0,
            $postsSummary['comments'] ?? 0,
            $postsSummary['shares'] ?? 0,
            $postsSummary['saves'] ?? 0,
            $reach > 0 ? $reach : null
        );

        return [
            'level'                    => 'user_overview',
            'period'                   => $dateRange['period'],
            'start'                    => $dateRange['start']->toIso8601String(),
            'end'                      => $dateRange['end']->toIso8601String(),
            'connected_accounts_count' => $connectedAccountsCount,
            'metrics'                  => [
                'total_followers'   => $followers,
                'total_impressions' => $impressions,
                'total_reach'       => $reach,
                'total_engagement'  => $engagement,
                'total_views'       => $views,
                'engagement_rate'   => $engCalc['rate'],
                'likes'             => (float) ($postsSummary['likes'] ?? 0),
                'comments'          => (float) ($postsSummary['comments'] ?? 0),
                'shares'            => (float) ($postsSummary['shares'] ?? 0),
                'saves'             => (float) ($postsSummary['saves'] ?? 0),
            ],
            'platforms'                => $platformBreakdown,
            'top_performing_posts'     => $this->getTopPostsForBrands($brandIds, 5),
        ];
    }

    /**
     * Level 2: Brand analytics aggregation.
     */
    public function getBrandAnalytics(Brand $brand, string $period = 'last_30_days', ?string $customStart = null, ?string $customEnd = null): array
    {
        $tz = $brand->timezone ?? 'UTC';
        $dateRange = $this->resolveDateRange($period, $customStart, $customEnd, $tz);

        $accounts = $brand->socialAccounts()->where('status', 'connected')->get();

        // Current followers for brand
        $latestFollowers = (float) AnalyticsSnapshot::where('brand_id', $brand->id)
            ->where('metric_name', 'followers')
            ->whereIn('id', function ($query) use ($brand) {
                $query->select(DB::raw('MAX(id)'))
                    ->from('analytics_snapshots')
                    ->where('brand_id', $brand->id)
                    ->where('metric_name', 'followers')
                    ->groupBy('social_account_id');
            })
            ->sum('metric_value');

        // Period snapshots
        $snapshots = AnalyticsSnapshot::where('brand_id', $brand->id)
            ->whereBetween('collected_at', [$dateRange['start'], $dateRange['end']])
            ->get();

        $metricsSum = $snapshots->groupBy('metric_name')->map(function ($items) {
            return (float) $items->sum('metric_value');
        })->toArray();

        // Platform breakdown
        $platforms = $snapshots->groupBy('platform')->map(function ($items) {
            return $items->groupBy('metric_name')->map(fn ($m) => (float) $m->sum('metric_value'))->toArray();
        })->toArray();

        // Daily trend data for charts
        $dailyTrends = AnalyticsSnapshot::where('brand_id', $brand->id)
            ->whereBetween('collected_at', [$dateRange['start'], $dateRange['end']])
            ->select(
                DB::raw('DATE(collected_at) as date'),
                'metric_name',
                DB::raw('SUM(metric_value) as value')
            )
            ->groupBy('date', 'metric_name')
            ->orderBy('date')
            ->get()
            ->groupBy('date')
            ->map(function ($rows) {
                return $rows->pluck('value', 'metric_name')->toArray();
            })
            ->toArray();

        // Post analytics for brand
        $postMetrics = PostAnalytic::where('brand_id', $brand->id)
            ->whereBetween('collected_at', [$dateRange['start'], $dateRange['end']])
            ->groupBy('metric_name')
            ->select('metric_name', DB::raw('SUM(metric_value) as total'))
            ->pluck('total', 'metric_name')
            ->toArray();

        $reach = (float) ($metricsSum['reach'] ?? $postMetrics['reach'] ?? 0);
        $engCalc = $this->calculationService->calculateEngagementRateByReach(
            $postMetrics['likes'] ?? 0,
            $postMetrics['comments'] ?? 0,
            $postMetrics['shares'] ?? 0,
            $postMetrics['saves'] ?? 0,
            $reach > 0 ? $reach : null
        );

        return [
            'level'                    => 'brand',
            'brand'                    => [
                'id'       => $brand->id,
                'name'     => $brand->name,
                'timezone' => $tz,
            ],
            'period'                   => $dateRange['period'],
            'start'                    => $dateRange['start']->toIso8601String(),
            'end'                      => $dateRange['end']->toIso8601String(),
            'connected_accounts_count' => $accounts->count(),
            'metrics'                  => [
                'followers'       => $latestFollowers,
                'impressions'     => (float) ($metricsSum['impressions'] ?? $postMetrics['impressions'] ?? 0),
                'reach'           => $reach,
                'engagement'      => (float) ($metricsSum['engagement'] ?? $metricsSum['accounts_engaged'] ?? $postMetrics['engagement'] ?? 0),
                'views'           => (float) ($metricsSum['video_views'] ?? $postMetrics['views'] ?? 0),
                'engagement_rate' => $engCalc['rate'],
                'likes'           => (float) ($postMetrics['likes'] ?? 0),
                'comments'        => (float) ($postMetrics['comments'] ?? 0),
                'shares'          => (float) ($postMetrics['shares'] ?? 0),
                'saves'           => (float) ($postMetrics['saves'] ?? 0),
            ],
            'platforms'                => $platforms,
            'daily_trends'             => $dailyTrends,
            'top_posts'                => $this->getTopPostsForBrands([$brand->id], 10),
            'underperforming_posts'    => $this->getUnderperformingPostsForBrand($brand->id, 5),
        ];
    }

    /**
     * Level 3: Platform analytics aggregation for a brand or all user brands.
     */
    public function getPlatformAnalytics(string $platform, array $brandIds, string $period = 'last_30_days', ?string $customStart = null, ?string $customEnd = null): array
    {
        $platformKey = strtolower($platform);
        $dateRange = $this->resolveDateRange($period, $customStart, $customEnd);

        $accounts = SocialAccount::whereIn('brand_id', $brandIds)
            ->where('provider', $platformKey)
            ->get();

        $snapshots = AnalyticsSnapshot::whereIn('brand_id', $brandIds)
            ->where('platform', $platformKey)
            ->whereBetween('collected_at', [$dateRange['start'], $dateRange['end']])
            ->get();

        $metricTotals = $snapshots->groupBy('metric_name')->map(function ($items) {
            return (float) $items->sum('metric_value');
        })->toArray();

        // Latest followers for this platform
        $followers = (float) AnalyticsSnapshot::whereIn('brand_id', $brandIds)
            ->where('platform', $platformKey)
            ->where('metric_name', 'followers')
            ->whereIn('id', function ($query) use ($brandIds, $platformKey) {
                $query->select(DB::raw('MAX(id)'))
                    ->from('analytics_snapshots')
                    ->whereIn('brand_id', $brandIds)
                    ->where('platform', $platformKey)
                    ->where('metric_name', 'followers')
                    ->groupBy('social_account_id');
            })
            ->sum('metric_value');

        // Post metrics for this platform
        $posts = PostAnalytic::whereIn('brand_id', $brandIds)
            ->where('platform', $platformKey)
            ->whereBetween('collected_at', [$dateRange['start'], $dateRange['end']])
            ->get();

        $postTotals = $posts->groupBy('metric_name')->map(fn ($m) => (float) $m->sum('metric_value'))->toArray();

        return [
            'level'           => 'platform',
            'platform'        => $platformKey,
            'accounts_count'  => $accounts->count(),
            'period'          => $dateRange['period'],
            'start'           => $dateRange['start']->toIso8601String(),
            'end'             => $dateRange['end']->toIso8601String(),
            'metrics'         => array_merge($metricTotals, [
                'followers' => $followers,
                'posts_likes' => (float) ($postTotals['likes'] ?? 0),
                'posts_comments' => (float) ($postTotals['comments'] ?? 0),
                'posts_shares' => (float) ($postTotals['shares'] ?? 0),
            ]),
            'top_posts'       => $this->getTopPostsForPlatform($platformKey, $brandIds, 10),
        ];
    }

    /**
     * Level 4: Individual social account performance.
     */
    public function getAccountAnalytics(SocialAccount $account, string $period = 'last_30_days', ?string $customStart = null, ?string $customEnd = null): array
    {
        $dateRange = $this->resolveDateRange($period, $customStart, $customEnd);

        $latestFollowers = (float) (AnalyticsSnapshot::where('social_account_id', $account->id)
            ->where('metric_name', 'followers')
            ->latest('period_start')
            ->value('metric_value') ?? 0);

        $snapshots = AnalyticsSnapshot::where('social_account_id', $account->id)
            ->whereBetween('collected_at', [$dateRange['start'], $dateRange['end']])
            ->get();

        $totals = $snapshots->groupBy('metric_name')->map(fn ($m) => (float) $m->sum('metric_value'))->toArray();
        $totals['followers'] = $latestFollowers;

        return [
            'level'      => 'account',
            'account'    => [
                'id'           => $account->id,
                'platform'     => $account->provider,
                'display_name' => $account->display_name,
                'status'       => $account->status,
            ],
            'period'     => $dateRange['period'],
            'start'      => $dateRange['start']->toIso8601String(),
            'end'        => $dateRange['end']->toIso8601String(),
            'metrics'    => $totals,
            'top_posts'  => PostAnalytic::where('social_account_id', $account->id)
                ->where('metric_name', 'views')
                ->orderByDesc('metric_value')
                ->take(10)
                ->get(),
        ];
    }

    /**
     * Level 5: Individual post analytics.
     */
    public function getPostAnalytics(string $externalPostId): array
    {
        $metrics = PostAnalytic::where('external_post_id', $externalPostId)->get();
        if ($metrics->isEmpty()) {
            return [
                'found'            => false,
                'external_post_id' => $externalPostId,
                'metrics'          => [],
            ];
        }

        $first = $metrics->first();
        $map = $metrics->pluck('metric_value', 'metric_name')->toArray();

        $reach = (float) ($map['reach'] ?? 0);
        $engCalc = $this->calculationService->calculateEngagementRateByReach(
            $map['likes'] ?? 0,
            $map['comments'] ?? 0,
            $map['shares'] ?? 0,
            $map['saves'] ?? 0,
            $reach > 0 ? $reach : null
        );

        return [
            'found'                 => true,
            'external_post_id'      => $externalPostId,
            'platform'              => $first->platform,
            'brand_id'              => $first->brand_id,
            'scheduled_post_id'     => $first->scheduled_post_id,
            'publishing_attempt_id' => $first->publishing_attempt_id,
            'metrics'               => array_merge($map, [
                'engagement_rate' => $engCalc['rate'],
            ]),
            'collected_at'          => $first->collected_at?->toIso8601String(),
        ];
    }

    /**
     * Retrieve top-performing posts across brands.
     */
    public function getTopPostsForBrands(array $brandIds, int $limit = 10): Collection
    {
        return PostAnalytic::whereIn('brand_id', $brandIds)
            ->whereIn('metric_name', ['views', 'impressions', 'engagement', 'likes'])
            ->select(
                'external_post_id',
                'platform',
                'brand_id',
                'scheduled_post_id',
                'publishing_attempt_id',
                DB::raw('MAX(CASE WHEN metric_name = "views" THEN metric_value ELSE 0 END) as views'),
                DB::raw('MAX(CASE WHEN metric_name = "impressions" THEN metric_value ELSE 0 END) as impressions'),
                DB::raw('MAX(CASE WHEN metric_name = "reach" THEN metric_value ELSE 0 END) as reach'),
                DB::raw('MAX(CASE WHEN metric_name = "likes" THEN metric_value ELSE 0 END) as likes'),
                DB::raw('MAX(CASE WHEN metric_name = "comments" THEN metric_value ELSE 0 END) as comments'),
                DB::raw('MAX(CASE WHEN metric_name = "shares" THEN metric_value ELSE 0 END) as shares'),
                DB::raw('MAX(CASE WHEN metric_name = "saves" THEN metric_value ELSE 0 END) as saves'),
                DB::raw('MAX(collected_at) as last_collected_at')
            )
            ->groupBy('external_post_id', 'platform', 'brand_id', 'scheduled_post_id', 'publishing_attempt_id')
            ->orderByDesc(DB::raw('views + impressions + likes * 2 + comments * 3 + shares * 4'))
            ->take($limit)
            ->get();
    }

    /**
     * Retrieve underperforming posts for a brand based on low engagement.
     */
    public function getUnderperformingPostsForBrand(int $brandId, int $limit = 5): Collection
    {
        return PostAnalytic::where('brand_id', $brandId)
            ->select(
                'external_post_id',
                'platform',
                'brand_id',
                'scheduled_post_id',
                'publishing_attempt_id',
                DB::raw('MAX(CASE WHEN metric_name = "views" THEN metric_value ELSE 0 END) as views'),
                DB::raw('MAX(CASE WHEN metric_name = "impressions" THEN metric_value ELSE 0 END) as impressions'),
                DB::raw('MAX(CASE WHEN metric_name = "likes" THEN metric_value ELSE 0 END) as likes'),
                DB::raw('MAX(CASE WHEN metric_name = "comments" THEN metric_value ELSE 0 END) as comments')
            )
            ->groupBy('external_post_id', 'platform', 'brand_id', 'scheduled_post_id', 'publishing_attempt_id')
            ->orderBy(DB::raw('views + likes + comments'))
            ->take($limit)
            ->get();
    }

    /**
     * Retrieve top posts for a specific platform.
     */
    public function getTopPostsForPlatform(string $platform, array $brandIds, int $limit = 10): Collection
    {
        return PostAnalytic::whereIn('brand_id', $brandIds)
            ->where('platform', strtolower($platform))
            ->select(
                'external_post_id',
                'platform',
                'brand_id',
                'scheduled_post_id',
                DB::raw('MAX(CASE WHEN metric_name = "views" THEN metric_value ELSE 0 END) as views'),
                DB::raw('MAX(CASE WHEN metric_name = "impressions" THEN metric_value ELSE 0 END) as impressions'),
                DB::raw('MAX(CASE WHEN metric_name = "likes" THEN metric_value ELSE 0 END) as likes'),
                DB::raw('MAX(CASE WHEN metric_name = "comments" THEN metric_value ELSE 0 END) as comments')
            )
            ->groupBy('external_post_id', 'platform', 'brand_id', 'scheduled_post_id')
            ->orderByDesc(DB::raw('views + impressions + likes'))
            ->take($limit)
            ->get();
    }

    /**
     * Alias for user aggregation.
     */
    public function aggregateForUser(int|User $user, string $period = '30d', ?int $brandId = null, ?string $platform = null): array
    {
        $userModel = $user instanceof User ? $user : User::findOrFail($user);

        if ($brandId) {
            $brand = Brand::findOrFail($brandId);
            return $this->getBrandAnalytics($brand, $period);
        }

        if ($platform) {
            $brandIds = $userModel->brands()->pluck('id')->toArray();
            return $this->getPlatformAnalytics($platform, $brandIds, $period);
        }

        return $this->getUserOverview($userModel, $period);
    }

    /**
     * Alias for brand aggregation.
     */
    public function aggregateForBrand(int|Brand $brand, string $period = '30d'): array
    {
        $brandModel = $brand instanceof Brand ? $brand : Brand::findOrFail($brand);
        return $this->getBrandAnalytics($brandModel, $period);
    }

    /**
     * Alias for platform aggregation across user brands.
     */
    public function aggregateByPlatform(int|User $user, string $period = '30d', ?int $brandId = null, ?string $platform = null): array
    {
        $userModel = $user instanceof User ? $user : User::findOrFail($user);
        $brandIds = $brandId ? [$brandId] : $userModel->brands()->pluck('id')->toArray();

        if ($platform) {
            return $this->getPlatformAnalytics($platform, $brandIds, $period);
        }

        $platforms = ['facebook', 'instagram', 'tiktok', 'youtube', 'linkedin'];
        $result = [];
        foreach ($platforms as $p) {
            $result[$p] = $this->getPlatformAnalytics($p, $brandIds, $period);
        }
        return $result;
    }

    /**
     * Alias for account aggregation.
     */
    public function aggregateForAccount(int|SocialAccount $account, string $period = '30d'): array
    {
        $accountModel = $account instanceof SocialAccount ? $account : SocialAccount::findOrFail($account);
        return $this->getAccountAnalytics($accountModel, $period);
    }
}
