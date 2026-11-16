<?php

namespace App\Services\Analytics\Providers;

use App\Models\SocialAccount;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * TikTok Analytics Provider using official TikTok Open API v2.
 *
 * Official API: https://developers.tiktok.com/doc/tiktok-api-v2-user-info/
 */
class TikTokAnalyticsProvider extends AbstractAnalyticsProvider
{
    private string $baseUrl = 'https://open.tiktokapis.com/v2';

    public function platform(): string
    {
        return 'tiktok';
    }

    public function getAvailableMetrics(): array
    {
        return [
            'followers' => [
                'name'         => 'Follower Count',
                'description'  => 'Total number of followers for this TikTok creator account.',
                'unit'         => 'count',
                'type'         => 'account',
                'availability' => 'available',
                'source'       => 'platform_api',
            ],
            'total_likes' => [
                'name'         => 'Total Profile Likes',
                'description'  => 'Cumulative likes received across all published TikTok videos.',
                'unit'         => 'count',
                'type'         => 'account',
                'availability' => 'available',
                'source'       => 'platform_api',
            ],
            'video_views' => [
                'name'         => 'Video Views',
                'description'  => 'Number of times a published TikTok video has been viewed.',
                'unit'         => 'count',
                'type'         => 'post',
                'availability' => 'available',
                'source'       => 'platform_api',
            ],
            'likes' => [
                'name'         => 'Video Likes',
                'description'  => 'Number of likes received on a published video.',
                'unit'         => 'count',
                'type'         => 'post',
                'availability' => 'available',
                'source'       => 'platform_api',
            ],
            'comments' => [
                'name'         => 'Video Comments',
                'description'  => 'Number of comments posted on a published video.',
                'unit'         => 'count',
                'type'         => 'post',
                'availability' => 'available',
                'source'       => 'platform_api',
            ],
            'shares' => [
                'name'         => 'Video Shares',
                'description'  => 'Number of times a video was shared.',
                'unit'         => 'count',
                'type'         => 'post',
                'availability' => 'available',
                'source'       => 'platform_api',
            ],
            'video_count' => [
                'name'         => 'Published Video Count',
                'description'  => 'Total number of public videos uploaded on this account.',
                'unit'         => 'count',
                'type'         => 'account',
                'availability' => 'available',
                'source'       => 'platform_api',
            ],
        ];
    }

    public function getSupportedDateRanges(): array
    {
        return ['day', 'week', 'month', 'lifetime'];
    }

    public function validatePermissions(SocialAccount $account): array
    {
        return $this->checkTokenHealth($account, ['user.info.basic']);
    }

    public function getAccountInsights(SocialAccount $account, array $options = []): array
    {
        $validation = $this->validatePermissions($account);
        if (!$validation['valid']) {
            return $this->standardResult(false, [], $validation['error_code'], $validation['reason']);
        }

        $token = $this->getAccessToken($account);

        try {
            $resp = Http::withToken($token)
                ->timeout(15)
                ->get("{$this->baseUrl}/user/info/", [
                    'fields' => 'open_id,union_id,avatar_url,display_name,follower_count,following_count,likes_count,video_count',
                ]);

            if ($resp->failed() || ($resp->json('error.code') && $resp->json('error.code') !== 'ok')) {
                $err = $resp->json('error.message', 'TikTok API request failed');
                $code = (string) ($resp->json('error.code') ?? 'TIKTOK_API_ERROR');
                return $this->standardResult(false, [], $code, $err);
            }

            $user = $resp->json('data.user', []);
            $metrics = [
                'followers'       => (float) ($user['follower_count'] ?? 0),
                'following'       => (float) ($user['following_count'] ?? 0),
                'total_likes'     => (float) ($user['likes_count'] ?? 0),
                'video_count'     => (float) ($user['video_count'] ?? 0),
                'display_name'    => (string) ($user['display_name'] ?? ''),
            ];

            return $this->standardResult(true, $metrics, null, null, ['account_id' => $account->id]);
        } catch (\Throwable $e) {
            return $this->standardResult(false, [], 'TIKTOK_FETCH_FAILED', $e->getMessage());
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
            $resp = Http::withToken($token)
                ->timeout(15)
                ->post("{$this->baseUrl}/video/query/", [
                    'filters' => [
                        'video_ids' => [$externalPostId],
                    ],
                    'fields' => [
                        'id',
                        'title',
                        'view_count',
                        'like_count',
                        'comment_count',
                        'share_count',
                    ],
                ]);

            if ($resp->failed() || ($resp->json('error.code') && $resp->json('error.code') !== 'ok')) {
                return $this->standardResult(false, [], 'TIKTOK_VIDEO_QUERY_FAILED', $resp->json('error.message', 'Could not query video metrics'));
            }

            $videos = $resp->json('data.videos', []);
            $target = $videos[0] ?? null;

            if (!$target) {
                return $this->standardResult(false, [], 'VIDEO_NOT_FOUND', "TikTok video ID {$externalPostId} was not found.");
            }

            $metrics = [
                'views'       => (float) ($target['view_count'] ?? 0),
                'likes'       => (float) ($target['like_count'] ?? 0),
                'comments'    => (float) ($target['comment_count'] ?? 0),
                'shares'      => (float) ($target['share_count'] ?? 0),
                'engagement'  => (float) (($target['like_count'] ?? 0) + ($target['comment_count'] ?? 0) + ($target['share_count'] ?? 0)),
            ];

            return $this->standardResult(true, [
                'external_post_id' => $externalPostId,
                'metrics'          => $metrics,
            ]);
        } catch (\Throwable $e) {
            return $this->standardResult(false, [], 'POST_INSIGHTS_ERROR', $e->getMessage());
        }
    }

    public function getAudienceInsights(SocialAccount $account, array $options = []): array
    {
        // TikTok audience demographic metrics require creator analytics API or verified business account
        $validation = $this->validatePermissions($account);
        if (!$validation['valid']) {
            return $this->standardResult(false, [], $validation['error_code'], $validation['reason']);
        }

        // Return empty dimensions with status note
        return $this->standardResult(true, [
            'dimensions' => [],
            'status'     => 'permission_required',
            'note'       => 'TikTok audience demographic breakdown requires verified Creator Marketplace API product access.',
        ]);
    }

    public function getFollowerMetrics(SocialAccount $account, array $options = []): array
    {
        $insights = $this->getAccountInsights($account, $options);
        if (!$insights['success']) {
            return $insights;
        }

        return $this->standardResult(true, [
            'followers'   => $insights['data']['followers'] ?? 0.0,
            'metric_unit' => 'count',
        ]);
    }

    public function normalizeMetrics(array $rawMetrics): array
    {
        return [
            'followers'   => (float) ($rawMetrics['follower_count'] ?? 0),
            'total_likes' => (float) ($rawMetrics['likes_count'] ?? 0),
            'video_count' => (float) ($rawMetrics['video_count'] ?? 0),
        ];
    }
}
