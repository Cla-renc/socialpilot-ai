<?php

namespace App\Services\Analytics\Providers;

use App\Models\SocialAccount;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Facebook Analytics Provider using Meta Graph API Page Insights.
 *
 * Official API: https://developers.facebook.com/docs/graph-api/reference/v22.0/page/insights
 */
class FacebookAnalyticsProvider extends AbstractAnalyticsProvider
{
    private string $graphVersion;
    private string $baseUrl;

    public function __construct()
    {
        $this->graphVersion = config('services.social.facebook.graph_version', 'v22.0');
        $this->baseUrl = "https://graph.facebook.com/{$this->graphVersion}";
    }

    public function platform(): string
    {
        return 'facebook';
    }

    public function getAvailableMetrics(): array
    {
        return [
            'followers' => [
                'name'         => 'Page Followers',
                'description'  => 'Total number of people who follow or like this Facebook Page.',
                'unit'         => 'count',
                'type'         => 'account',
                'availability' => 'available',
                'source'       => 'platform_api',
            ],
            'impressions' => [
                'name'         => 'Page Impressions',
                'description'  => 'The number of times any content from your Page or about your Page entered a person\'s screen.',
                'unit'         => 'count',
                'type'         => 'account',
                'availability' => 'available',
                'source'       => 'platform_api',
            ],
            'reach' => [
                'name'         => 'Page Reach',
                'description'  => 'The number of unique people who saw any content about your Page.',
                'unit'         => 'count',
                'type'         => 'account',
                'availability' => 'available',
                'source'       => 'platform_api',
            ],
            'engagement' => [
                'name'         => 'Page Engaged Users',
                'description'  => 'The number of people who engaged with your Page content (clicked, reacted, shared, commented).',
                'unit'         => 'count',
                'type'         => 'account',
                'availability' => 'available',
                'source'       => 'platform_api',
            ],
            'page_views' => [
                'name'         => 'Page Views',
                'description'  => 'The number of times a Facebook Page profile has been viewed.',
                'unit'         => 'count',
                'type'         => 'account',
                'availability' => 'available',
                'source'       => 'platform_api',
            ],
            'post_reactions' => [
                'name'         => 'Post Reactions',
                'description'  => 'Reactions on published posts (like, love, wow, haha, sad, angry).',
                'unit'         => 'count',
                'type'         => 'post',
                'availability' => 'available',
                'source'       => 'platform_api',
            ],
            'post_comments' => [
                'name'         => 'Post Comments',
                'description'  => 'Total number of comments left on published posts.',
                'unit'         => 'count',
                'type'         => 'post',
                'availability' => 'available',
                'source'       => 'platform_api',
            ],
            'post_shares' => [
                'name'         => 'Post Shares',
                'description'  => 'Total times published posts were shared.',
                'unit'         => 'count',
                'type'         => 'post',
                'availability' => 'available',
                'source'       => 'platform_api',
            ],
            'video_views' => [
                'name'         => 'Video Views',
                'description'  => 'Total 3-second or longer video views on video posts.',
                'unit'         => 'count',
                'type'         => 'post',
                'availability' => 'available',
                'source'       => 'platform_api',
            ],
        ];
    }

    public function getSupportedDateRanges(): array
    {
        return ['day', 'week', 'days_28', 'lifetime', 'custom'];
    }

    public function validatePermissions(SocialAccount $account): array
    {
        if ($account->account_type !== 'page') {
            return [
                'valid'          => false,
                'scopes_present' => $account->token?->scopes ?? [],
                'scopes_missing' => [],
                'reason'         => 'Only authorized Facebook Pages can access Page Insights. Personal profiles are not supported.',
                'error_code'     => 'ACCOUNT_TYPE_UNSUPPORTED',
            ];
        }

        // We check for token presence and basic read permissions
        return $this->checkTokenHealth($account, ['pages_read_engagement']);
    }

    public function getAccountInsights(SocialAccount $account, array $options = []): array
    {
        $validation = $this->validatePermissions($account);
        if (!$validation['valid']) {
            return $this->standardResult(false, [], $validation['error_code'], $validation['reason']);
        }

        $token = $this->getAccessToken($account);
        $pageId = $account->provider_account_id;
        $period = $options['period'] ?? 'day';

        $metricsToFetch = [
            'page_impressions',
            'page_engaged_users',
            'page_views_total',
            'page_fan_adds',
        ];

        try {
            $response = Http::withToken($token)
                ->timeout(15)
                ->get("{$this->baseUrl}/{$pageId}/insights", [
                    'metric' => implode(',', $metricsToFetch),
                    'period' => $period,
                    'since'  => $options['since'] ?? now()->subDays(30)->timestamp,
                    'until'  => $options['until'] ?? now()->timestamp,
                ]);

            if ($response->failed()) {
                $err = $response->json('error.message', 'Meta Graph API error');
                $code = (string) $response->json('error.code', 'META_API_ERROR');
                Log::warning("Facebook Page Insights failed for {$pageId}: {$err} ({$code})");
                return $this->standardResult(false, [], $code, $err, ['status' => $response->status()]);
            }

            $raw = $response->json('data', []);
            $normalized = $this->normalizeMetrics($raw);

            // Also retrieve current fan count (followers)
            $pageInfoResp = Http::withToken($token)
                ->timeout(10)
                ->get("{$this->baseUrl}/{$pageId}", [
                    'fields' => 'fan_count,followers_count,name',
                ]);

            if ($pageInfoResp->successful()) {
                $normalized['followers'] = (float) ($pageInfoResp->json('followers_count') ?? $pageInfoResp->json('fan_count') ?? 0);
            }

            return $this->standardResult(true, $normalized, null, null, ['page_id' => $pageId, 'period' => $period]);
        } catch (\Throwable $e) {
            Log::error("Facebook getAccountInsights exception: {$e->getMessage()}");
            return $this->standardResult(false, [], 'NETWORK_ERROR', $e->getMessage());
        }
    }

    public function getPostInsights(SocialAccount $account, string $externalPostId, array $options = []): array
    {
        $validation = $this->validatePermissions($account);
        if (!$validation['valid']) {
            return $this->standardResult(false, [], $validation['error_code'], $validation['reason']);
        }

        $token = $this->getAccessToken($account);

        try {
            // Post insights: post_impressions, post_engaged_users, post_reactions_by_type_total
            $response = Http::withToken($token)
                ->timeout(15)
                ->get("{$this->baseUrl}/{$externalPostId}/insights", [
                    'metric' => 'post_impressions,post_engaged_users,post_reactions_by_type_total,post_video_views',
                ]);

            $metrics = [
                'impressions' => 0.0,
                'reach'       => 0.0,
                'engagement'  => 0.0,
                'reactions'   => 0.0,
                'comments'    => 0.0,
                'shares'      => 0.0,
                'views'       => 0.0,
            ];

            if ($response->successful()) {
                foreach ($response->json('data', []) as $item) {
                    $name = $item['name'] ?? '';
                    $val = 0.0;
                    if (!empty($item['values'][0]['value'])) {
                        $v = $item['values'][0]['value'];
                        $val = is_numeric($v) ? (float) $v : (is_array($v) ? (float) array_sum($v) : 0.0);
                    }

                    if ($name === 'post_impressions') {
                        $metrics['impressions'] = $val;
                    } elseif ($name === 'post_engaged_users') {
                        $metrics['engagement'] = $val;
                        $metrics['reach'] = $val; // engaged reach baseline
                    } elseif ($name === 'post_reactions_by_type_total') {
                        $metrics['reactions'] = $val;
                    } elseif ($name === 'post_video_views') {
                        $metrics['views'] = $val;
                    }
                }
            }

            // Also query edge counts for likes, comments, shares on post
            $edgeResp = Http::withToken($token)
                ->timeout(10)
                ->get("{$this->baseUrl}/{$externalPostId}", [
                    'fields' => 'shares,comments.summary(true),reactions.summary(true)',
                ]);

            if ($edgeResp->successful()) {
                $shares = (float) $edgeResp->json('shares.count', 0);
                $comments = (float) $edgeResp->json('comments.summary.total_count', 0);
                $reactions = (float) $edgeResp->json('reactions.summary.total_count', 0);

                if ($shares > 0) $metrics['shares'] = $shares;
                if ($comments > 0) $metrics['comments'] = $comments;
                if ($reactions > 0) $metrics['reactions'] = $reactions;
            }

            return $this->standardResult(true, [
                'external_post_id' => $externalPostId,
                'metrics'          => $metrics,
            ]);
        } catch (\Throwable $e) {
            return $this->standardResult(false, [], 'POST_INSIGHTS_FAILED', $e->getMessage());
        }
    }

    public function getAudienceInsights(SocialAccount $account, array $options = []): array
    {
        $validation = $this->validatePermissions($account);
        if (!$validation['valid']) {
            return $this->standardResult(false, [], $validation['error_code'], $validation['reason']);
        }

        $token = $this->getAccessToken($account);
        $pageId = $account->provider_account_id;

        try {
            // Meta Page Fans demographics
            $response = Http::withToken($token)
                ->timeout(15)
                ->get("{$this->baseUrl}/{$pageId}/insights", [
                    'metric' => 'page_fans_country,page_fans_gender_age',
                    'period' => 'lifetime',
                ]);

            if ($response->failed()) {
                return $this->standardResult(false, [], 'AUDIENCE_INSIGHTS_FAILED', $response->json('error.message', 'Could not retrieve audience metrics'));
            }

            $dimensions = [];
            foreach ($response->json('data', []) as $item) {
                $name = $item['name'] ?? '';
                $valMap = $item['values'][0]['value'] ?? [];
                if (!is_array($valMap)) continue;

                if ($name === 'page_fans_country') {
                    foreach ($valMap as $country => $count) {
                        $dimensions[] = [
                            'insight_type'    => 'country',
                            'dimension'       => 'country_code',
                            'dimension_value' => (string) $country,
                            'metric_value'    => (float) $count,
                        ];
                    }
                } elseif ($name === 'page_fans_gender_age') {
                    foreach ($valMap as $key => $count) {
                        // key is e.g. "F.25-34" or "M.18-24"
                        $dimensions[] = [
                            'insight_type'    => 'age_gender',
                            'dimension'       => 'gender_age_bracket',
                            'dimension_value' => (string) $key,
                            'metric_value'    => (float) $count,
                        ];
                    }
                }
            }

            return $this->standardResult(true, ['dimensions' => $dimensions]);
        } catch (\Throwable $e) {
            return $this->standardResult(false, [], 'AUDIENCE_ERROR', $e->getMessage());
        }
    }

    public function getFollowerMetrics(SocialAccount $account, array $options = []): array
    {
        $validation = $this->validatePermissions($account);
        if (!$validation['valid']) {
            return $this->standardResult(false, [], $validation['error_code'], $validation['reason']);
        }

        $token = $this->getAccessToken($account);
        $pageId = $account->provider_account_id;

        try {
            $resp = Http::withToken($token)
                ->timeout(10)
                ->get("{$this->baseUrl}/{$pageId}", [
                    'fields' => 'followers_count,fan_count',
                ]);

            if ($resp->failed()) {
                return $this->standardResult(false, [], 'FOLLOWER_LOOKUP_FAILED', $resp->json('error.message', 'Failed to retrieve page followers'));
            }

            $count = (float) ($resp->json('followers_count') ?? $resp->json('fan_count') ?? 0);

            return $this->standardResult(true, [
                'followers'   => $count,
                'metric_unit' => 'count',
            ]);
        } catch (\Throwable $e) {
            return $this->standardResult(false, [], 'FOLLOWER_ERROR', $e->getMessage());
        }
    }

    public function normalizeMetrics(array $rawMetrics): array
    {
        $normalized = [
            'impressions'     => 0.0,
            'engagement'      => 0.0,
            'page_views'      => 0.0,
            'followers_added' => 0.0,
        ];

        foreach ($rawMetrics as $item) {
            $name = $item['name'] ?? '';
            $latestVal = 0.0;
            if (!empty($item['values']) && is_array($item['values'])) {
                $last = end($item['values']);
                $latestVal = isset($last['value']) && is_numeric($last['value']) ? (float) $last['value'] : 0.0;
            }

            if ($name === 'page_impressions') {
                $normalized['impressions'] = $latestVal;
            } elseif ($name === 'page_engaged_users') {
                $normalized['engagement'] = $latestVal;
            } elseif ($name === 'page_views_total') {
                $normalized['page_views'] = $latestVal;
            } elseif ($name === 'page_fan_adds') {
                $normalized['followers_added'] = $latestVal;
            }
        }

        return $normalized;
    }
}
