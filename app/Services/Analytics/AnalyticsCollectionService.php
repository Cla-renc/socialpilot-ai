<?php

namespace App\Services\Analytics;

use App\Models\AnalyticsCollectionLog;
use App\Models\AnalyticsSnapshot;
use App\Models\AudienceInsight;
use App\Models\PostAnalytic;
use App\Models\PublishingAttempt;
use App\Models\SocialAccount;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AnalyticsCollectionService
{
    public function __construct(
        protected AnalyticsManager $analyticsManager,
        protected AnalyticsCalculationService $calculationService
    ) {}

    /**
     * Run full analytics collection for a specific SocialAccount.
     */
    public function collectAccount(SocialAccount $account, array $options = []): array
    {
        $platform = $account->provider;
        $brandId = $account->brand_id;
        $startedAt = now();

        $log = AnalyticsCollectionLog::create([
            'brand_id'          => $brandId,
            'social_account_id' => $account->id,
            'platform'          => $platform,
            'collection_type'   => $options['type'] ?? 'full',
            'status'            => 'started',
            'started_at'        => $startedAt,
        ]);

        try {
            $provider = $this->analyticsManager->forAccount($account);

            // Validate permissions before calling API
            $validation = $provider->validatePermissions($account);
            if (!$validation['valid']) {
                $log->update([
                    'status'        => 'failed',
                    'error_code'    => $validation['error_code'] ?? 'PERMISSION_FAILED',
                    'error_message' => $validation['reason'] ?? 'Permissions check failed',
                    'completed_at'  => now(),
                ]);

                return [
                    'success'    => false,
                    'account_id' => $account->id,
                    'error'      => $validation['reason'],
                    'code'       => $validation['error_code'],
                ];
            }

            $recordsCollected = 0;

            // 1. Collect Account-level Insights
            $accountResult = $provider->getAccountInsights($account, $options);
            if ($accountResult['success'] && !empty($accountResult['data'])) {
                $recordsCollected += $this->persistAccountSnapshots($account, $accountResult['data']);
            }

            // 2. Collect Follower Metrics
            $followerResult = $provider->getFollowerMetrics($account, $options);
            if ($followerResult['success'] && isset($followerResult['data']['followers'])) {
                $recordsCollected += $this->persistFollowerSnapshot(
                    $account,
                    (float) $followerResult['data']['followers']
                );
            }

            // 3. Collect Audience Insights if supported
            $audienceResult = $provider->getAudienceInsights($account, $options);
            if ($audienceResult['success'] && !empty($audienceResult['data']['dimensions'])) {
                $recordsCollected += $this->persistAudienceInsights(
                    $account,
                    $audienceResult['data']['dimensions']
                );
            }

            // 4. Collect Post-level analytics for recent publishing attempts
            $recordsCollected += $this->collectRecentPostAnalytics($account, $options);

            $log->update([
                'status'            => 'success',
                'records_collected' => $recordsCollected,
                'completed_at'      => now(),
            ]);

            return [
                'success'           => true,
                'account_id'        => $account->id,
                'records_collected' => $recordsCollected,
                'status'            => 'success',
            ];
        } catch (\Throwable $e) {
            Log::error("Analytics collection failed for account {$account->id} ({$platform}): {$e->getMessage()}");

            $log->update([
                'status'        => 'failed',
                'error_code'    => 'COLLECTION_EXCEPTION',
                'error_message' => $e->getMessage(),
                'completed_at'  => now(),
            ]);

            return [
                'success'    => false,
                'account_id' => $account->id,
                'error'      => $e->getMessage(),
                'code'       => 'COLLECTION_EXCEPTION',
            ];
        }
    }

    /**
     * Persist normalized account metrics as historical snapshots.
     * Prevents duplicate snapshots for same account, metric, period, and period_start.
     */
    public function persistAccountSnapshots(SocialAccount $account, array $metrics): int
    {
        $count = 0;
        $now = now();
        $periodStart = $now->copy()->startOfDay();
        $periodEnd = $now->copy()->endOfDay();

        foreach ($metrics as $metricName => $value) {
            if ($metricName === 'followers' || !is_numeric($value)) {
                continue;
            }

            $unit = in_array($metricName, ['engagement_rate'], true) ? 'percentage' :
                (in_array($metricName, ['watch_time'], true) ? 'minutes' : 'count');

            AnalyticsSnapshot::updateOrCreate(
                [
                    'social_account_id' => $account->id,
                    'metric_name'       => $metricName,
                    'reporting_period'  => 'day',
                    'period_start'      => $periodStart,
                ],
                [
                    'brand_id'     => $account->brand_id,
                    'platform'     => $account->provider,
                    'metric_type'  => 'account',
                    'metric_value' => (float) $value,
                    'metric_unit'  => $unit,
                    'period_end'   => $periodEnd,
                    'collected_at' => $now,
                    'source'       => 'platform_api',
                ]
            );
            $count++;
        }

        return $count;
    }

    /**
     * Persist follower count snapshot and compute growth.
     */
    public function persistFollowerSnapshot(SocialAccount $account, float $followerCount): int
    {
        $now = now();
        $periodStart = $now->copy()->startOfDay();

        // Retrieve latest previous snapshot for baseline growth comparison
        $previousSnapshot = AnalyticsSnapshot::where('social_account_id', $account->id)
            ->where('metric_name', 'followers')
            ->where('period_start', '<', $periodStart)
            ->latest('period_start')
            ->first();

        $previousFollowers = $previousSnapshot ? (float) $previousSnapshot->metric_value : null;
        $growthCalc = $this->calculationService->calculateFollowerGrowth($followerCount, $previousFollowers);

        AnalyticsSnapshot::updateOrCreate(
            [
                'social_account_id' => $account->id,
                'metric_name'       => 'followers',
                'reporting_period'  => 'day',
                'period_start'      => $periodStart,
            ],
            [
                'brand_id'     => $account->brand_id,
                'platform'     => $account->provider,
                'metric_type'  => 'follower',
                'metric_value' => $followerCount,
                'metric_unit'  => 'count',
                'period_end'   => $now->copy()->endOfDay(),
                'collected_at' => $now,
                'source'       => 'platform_api',
                'metadata'     => [
                    'growth' => $growthCalc,
                ],
            ]
        );

        return 1;
    }

    /**
     * Persist audience demographics and dimensional breakdowns.
     */
    public function persistAudienceInsights(SocialAccount $account, array $dimensions): int
    {
        $count = 0;
        $now = now();

        foreach ($dimensions as $dim) {
            if (empty($dim['insight_type']) || empty($dim['dimension']) || !isset($dim['dimension_value'])) {
                continue;
            }

            AudienceInsight::updateOrCreate(
                [
                    'social_account_id' => $account->id,
                    'insight_type'      => $dim['insight_type'],
                    'dimension'         => $dim['dimension'],
                    'dimension_value'   => (string) $dim['dimension_value'],
                    'reporting_period'  => 'lifetime',
                ],
                [
                    'brand_id'     => $account->brand_id,
                    'platform'     => $account->provider,
                    'metric_value' => (float) ($dim['metric_value'] ?? 0),
                    'collected_at' => $now,
                ]
            );
            $count++;
        }

        return $count;
    }

    /**
     * Collect post-level analytics for recent published posts on this account.
     */
    public function collectRecentPostAnalytics(SocialAccount $account, array $options = []): int
    {
        $days = $options['post_days'] ?? 30;
        $attempts = PublishingAttempt::where('social_account_id', $account->id)
            ->where('status', 'published')
            ->whereNotNull('external_post_id')
            ->where('published_at', '>=', now()->subDays($days))
            ->latest('published_at')
            ->take(50)
            ->get();

        $count = 0;
        $provider = $this->analyticsManager->forAccount($account);

        foreach ($attempts as $attempt) {
            $postResult = $provider->getPostInsights($account, $attempt->external_post_id);
            if (!$postResult['success'] || empty($postResult['data']['metrics'])) {
                continue;
            }

            $metrics = $postResult['data']['metrics'];
            foreach ($metrics as $metricName => $value) {
                if (!is_numeric($value)) continue;

                PostAnalytic::updateOrCreate(
                    [
                        'social_account_id' => $account->id,
                        'external_post_id'  => $attempt->external_post_id,
                        'metric_name'       => $metricName,
                        'reporting_period'  => 'lifetime',
                    ],
                    [
                        'brand_id'              => $account->brand_id,
                        'publishing_attempt_id' => $attempt->id,
                        'scheduled_post_id'     => $attempt->scheduled_post_id,
                        'platform'              => $account->provider,
                        'metric_value'          => (float) $value,
                        'metric_unit'           => in_array($metricName, ['engagement_rate'], true) ? 'percentage' : 'count',
                        'collected_at'          => now(),
                    ]
                );
                $count++;
            }
        }

        return $count;
    }

    /**
     * Collect analytics for all connected accounts of a Brand.
     */
    public function collectBrand(int $brandId, array $options = []): array
    {
        $accounts = SocialAccount::where('brand_id', $brandId)
            ->where('status', 'connected')
            ->get();

        $results = [];
        foreach ($accounts as $account) {
            $results[$account->id] = $this->collectAccount($account, $options);
        }

        return $results;
    }
}
