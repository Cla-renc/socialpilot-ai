<?php

namespace App\Services\Analytics\Providers;

use App\Models\SocialAccount;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Instagram Analytics Provider using Instagram Graph API Insights.
 *
 * Official API: https://developers.facebook.com/docs/instagram-api/reference/ig-user/insights
 */
class InstagramAnalyticsProvider extends AbstractAnalyticsProvider
{
    private string $graphVersion;
    private string $baseUrl;

    public function __construct()
    {
        $this->graphVersion = config('services.social.instagram.graph_version', 'v22.0');
        $this->baseUrl = "https://graph.facebook.com/{$this->graphVersion}";
    }

    public function platform(): string
    {
        return 'instagram';
    }

    public function getAvailableMetrics(): array
    {
        return [
            'followers' => [
                'name'         => 'Follower Count',
                'description'  => 'Total number of Instagram accounts following this professional profile.',
                'unit'         => 'count',
                'type'         => 'account',
                'availability' => 'available',
                'source'       => 'platform_api',
            ],
            'reach' => [
                'name'         => 'Accounts Reached',
                'description'  => 'The number of unique accounts that have seen your Instagram content.',
                'unit'         => 'count',
                'type'         => 'account',
                'availability' => 'available',
                'source'       => 'platform_api',
            ],
            'impressions' => [
                'name'         => 'Impressions',
                'description'  => 'Total number of times your posts, stories, reels, or profile were on screen.',
                'unit'         => 'count',
                'type'         => 'account',
                'availability' => 'available',
                'source'       => 'platform_api',
            ],
            'accounts_engaged' => [
                'name'         => 'Accounts Engaged',
                'description'  => 'The number of unique accounts that have interacted with your content.',
                'unit'         => 'count',
                'type'         => 'account',
                'availability' => 'available',
                'source'       => 'platform_api',
            ],
            'profile_views' => [
                'name'         => 'Profile Views',
                'description'  => 'Number of times your profile was viewed.',
                'unit'         => 'count',
                'type'         => 'account',
                'availability' => 'available',
                'source'       => 'platform_api',
            ],
            'likes' => [
                'name'         => 'Media Likes',
                'description'  => 'Total likes received on published media items.',
                'unit'         => 'count',
                'type'         => 'post',
                'availability' => 'available',
                'source'       => 'platform_api',
            ],
            'comments' => [
                'name'         => 'Media Comments',
                'description'  => 'Total comments received on published media items.',
                'unit'         => 'count',
                'type'         => 'post',
                'availability' => 'available',
                'source'       => 'platform_api',
            ],
            'shares' => [
                'name'         => 'Media Shares',
                'description'  => 'Number of times your media was shared in direct messages or stories.',
                'unit'         => 'count',
                'type'         => 'post',
                'availability' => 'available',
                'source'       => 'platform_api',
            ],
            'saves' => [
                'name'         => 'Media Saves',
                'description'  => 'Number of unique accounts that saved your media.',
                'unit'         => 'count',
                'type'         => 'post',
                'availability' => 'available',
                'source'       => 'platform_api',
            ],
            'video_views' => [
                'name'         => 'Reel Plays / Video Views',
                'description'  => 'Number of times a reel or video started playing.',
                'unit'         => 'count',
                'type'         => 'post',
                'availability' => 'available',
                'source'       => 'platform_api',
            ],
        ];
    }

    public function getSupportedDateRanges(): array
    {
        return ['day', 'days_28', 'lifetime', 'custom'];
    }

    public function validatePermissions(SocialAccount $account): array
    {
        // Instagram Insights requires Business or Creator account
        $metadata = $account->metadata ?? [];
        $accountType = $account->account_type ?? $metadata['account_type'] ?? '';
        if ($accountType && in_array(strtolower($accountType), ['personal', 'private'])) {
            return [
                'valid'          => false,
                'scopes_present' => $account->token?->scopes ?? [],
                'scopes_missing' => [],
                'reason'         => 'Instagram Graph API insights only support Professional (Business or Creator) accounts.',
                'error_code'     => 'ACCOUNT_TYPE_UNSUPPORTED',
            ];
        }

        return $this->checkTokenHealth($account, ['instagram_basic']);
    }

    public function getAccountInsights(SocialAccount $account, array $options = []): array
    {
        $validation = $this->validatePermissions($account);
        if (!$validation['valid']) {
            return $this->standardResult(false, [], $validation['error_code'], $validation['reason']);
        }

        $token = $this->getAccessToken($account);
        $igUserId = $account->provider_account_id;
        $period = $options['period'] ?? 'day';

        try {
            $response = Http::withToken($token)
                ->timeout(15)
                ->get("{$this->baseUrl}/{$igUserId}/insights", [
                    'metric' => 'reach,impressions,accounts_engaged,profile_views',
                    'metric_type' => 'total_value',
                    'period' => $period,
                    'since'  => $options['since'] ?? now()->subDays(30)->timestamp,
                    'until'  => $options['until'] ?? now()->timestamp,
                ]);

            if ($response->failed()) {
                $err = $response->json('error.message', 'Instagram Insights API error');
                $code = (string) $response->json('error.code', 'IG_INSIGHTS_ERROR');
                return $this->standardResult(false, [], $code, $err);
            }

            $raw = $response->json('data', []);
            $normalized = $this->normalizeMetrics($raw);

            // Fetch follower_count directly
            $userResp = Http::withToken($token)
                ->timeout(10)
                ->get("{$this->baseUrl}/{$igUserId}", [
                    'fields' => 'followers_count,media_count,name,username',
                ]);

            if ($userResp->successful()) {
                $normalized['followers'] = (float) ($userResp->json('followers_count') ?? 0);
                $normalized['media_count'] = (float) ($userResp->json('media_count') ?? 0);
            }

            return $this->standardResult(true, $normalized, null, null, ['ig_user_id' => $igUserId]);
        } catch (\Throwable $e) {
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
            // Instagram media insights: engagement, impressions, reach, saved
            $response = Http::withToken($token)
                ->timeout(15)
                ->get("{$this->baseUrl}/{$externalPostId}/insights", [
                    'metric' => 'engagement,impressions,reach,saved,shares,video_views',
                ]);

            $metrics = [
                'engagement'  => 0.0,
                'impressions' => 0.0,
                'reach'       => 0.0,
                'saves'       => 0.0,
                'shares'      => 0.0,
                'likes'       => 0.0,
                'comments'    => 0.0,
                'views'       => 0.0,
            ];

            if ($response->successful()) {
                foreach ($response->json('data', []) as $item) {
                    $name = $item['name'] ?? '';
                    $val = (float) ($item['values'][0]['value'] ?? 0);

                    if ($name === 'engagement') $metrics['engagement'] = $val;
                    elseif ($name === 'impressions') $metrics['impressions'] = $val;
                    elseif ($name === 'reach') $metrics['reach'] = $val;
                    elseif ($name === 'saved') $metrics['saves'] = $val;
                    elseif ($name === 'shares') $metrics['shares'] = $val;
                    elseif ($name === 'video_views' || $name === 'plays') $metrics['views'] = $val;
                }
            }

            // Retrieve public counts for like_count and comments_count
            $mediaResp = Http::withToken($token)
                ->timeout(10)
                ->get("{$this->baseUrl}/{$externalPostId}", [
                    'fields' => 'like_count,comments_count,media_type',
                ]);

            if ($mediaResp->successful()) {
                $metrics['likes'] = (float) ($mediaResp->json('like_count') ?? 0);
                $metrics['comments'] = (float) ($mediaResp->json('comments_count') ?? 0);
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
        $igUserId = $account->provider_account_id;

        try {
            // Instagram audience insights: audience_country, audience_gender_age, audience_locale
            $response = Http::withToken($token)
                ->timeout(15)
                ->get("{$this->baseUrl}/{$igUserId}/insights", [
                    'metric' => 'audience_country,audience_gender_age',
                    'period' => 'lifetime',
                ]);

            if ($response->failed()) {
                return $this->standardResult(false, [], 'AUDIENCE_INSIGHTS_FAILED', $response->json('error.message', 'Audience metrics unavailable'));
            }

            $dimensions = [];
            foreach ($response->json('data', []) as $item) {
                $name = $item['name'] ?? '';
                $valMap = $item['values'][0]['value'] ?? [];
                if (!is_array($valMap)) continue;

                if ($name === 'audience_country') {
                    foreach ($valMap as $country => $count) {
                        $dimensions[] = [
                            'insight_type'    => 'country',
                            'dimension'       => 'country_code',
                            'dimension_value' => (string) $country,
                            'metric_value'    => (float) $count,
                        ];
                    }
                } elseif ($name === 'audience_gender_age') {
                    foreach ($valMap as $bracket => $count) {
                        $dimensions[] = [
                            'insight_type'    => 'age_gender',
                            'dimension'       => 'gender_age_bracket',
                            'dimension_value' => (string) $bracket,
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
        $igUserId = $account->provider_account_id;

        try {
            $resp = Http::withToken($token)
                ->timeout(10)
                ->get("{$this->baseUrl}/{$igUserId}", [
                    'fields' => 'followers_count',
                ]);

            if ($resp->failed()) {
                return $this->standardResult(false, [], 'FOLLOWER_LOOKUP_FAILED', $resp->json('error.message', 'Failed to retrieve Instagram followers'));
            }

            return $this->standardResult(true, [
                'followers'   => (float) ($resp->json('followers_count') ?? 0),
                'metric_unit' => 'count',
            ]);
        } catch (\Throwable $e) {
            return $this->standardResult(false, [], 'FOLLOWER_ERROR', $e->getMessage());
        }
    }

    public function normalizeMetrics(array $rawMetrics): array
    {
        $normalized = [
            'reach'            => 0.0,
            'impressions'      => 0.0,
            'accounts_engaged' => 0.0,
            'profile_views'    => 0.0,
        ];

        foreach ($rawMetrics as $item) {
            $name = $item['name'] ?? '';
            $val = 0.0;
            if (!empty($item['total_value']['value'])) {
                $val = (float) $item['total_value']['value'];
            } elseif (!empty($item['values']) && is_array($item['values'])) {
                $last = end($item['values']);
                $val = isset($last['value']) ? (float) $last['value'] : 0.0;
            }

            if ($name === 'reach') {
                $normalized['reach'] = $val;
            } elseif ($name === 'impressions') {
                $normalized['impressions'] = $val;
            } elseif ($name === 'accounts_engaged') {
                $normalized['accounts_engaged'] = $val;
            } elseif ($name === 'profile_views') {
                $normalized['profile_views'] = $val;
            }
        }

        return $normalized;
    }
}
