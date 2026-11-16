<?php

namespace App\Services\Analytics\Providers;

use App\Models\SocialAccount;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * LinkedIn Analytics Provider using official LinkedIn Community Management & Organization Analytics APIs.
 *
 * Official API: https://learn.microsoft.com/en-us/linkedin/marketing/community-management/shares/share-statistics
 */
class LinkedInAnalyticsProvider extends AbstractAnalyticsProvider
{
    private string $baseUrl = 'https://api.linkedin.com/rest';
    private string $apiVersion = '202601';

    public function platform(): string
    {
        return 'linkedin';
    }

    public function getAvailableMetrics(): array
    {
        return [
            'followers' => [
                'name'         => 'Organization Followers',
                'description'  => 'Total organic and sponsored followers of the LinkedIn Organization page.',
                'unit'         => 'count',
                'type'         => 'account',
                'availability' => 'available',
                'source'       => 'platform_api',
            ],
            'impressions' => [
                'name'         => 'Post Impressions',
                'description'  => 'The number of times shares were displayed on screen to LinkedIn members.',
                'unit'         => 'count',
                'type'         => 'account',
                'availability' => 'available',
                'source'       => 'platform_api',
            ],
            'reach' => [
                'name'         => 'Unique Impressions / Member Reach',
                'description'  => 'The number of unique members who had shares displayed on screen.',
                'unit'         => 'count',
                'type'         => 'account',
                'availability' => 'available',
                'source'       => 'platform_api',
            ],
            'clicks' => [
                'name'         => 'Clicks',
                'description'  => 'Number of clicks on your content, company name, or logo.',
                'unit'         => 'count',
                'type'         => 'account',
                'availability' => 'available',
                'source'       => 'platform_api',
            ],
            'likes' => [
                'name'         => 'Reactions / Likes',
                'description'  => 'Total reactions received on shares.',
                'unit'         => 'count',
                'type'         => 'post',
                'availability' => 'available',
                'source'       => 'platform_api',
            ],
            'comments' => [
                'name'         => 'Comments',
                'description'  => 'Total comments made on shares.',
                'unit'         => 'count',
                'type'         => 'post',
                'availability' => 'available',
                'source'       => 'platform_api',
            ],
            'shares' => [
                'name'         => 'Reposts / Shares',
                'description'  => 'Total times shares were reposted or shared.',
                'unit'         => 'count',
                'type'         => 'post',
                'availability' => 'available',
                'source'       => 'platform_api',
            ],
            'engagement_rate' => [
                'name'         => 'Platform Engagement Rate',
                'description'  => 'Official LinkedIn calculated engagement rate: (Clicks + Likes + Comments + Shares) / Impressions.',
                'unit'         => 'percentage',
                'type'         => 'account',
                'availability' => 'available',
                'source'       => 'platform_api',
            ],
        ];
    }

    public function getSupportedDateRanges(): array
    {
        return ['day', 'week', 'month', 'custom'];
    }

    public function validatePermissions(SocialAccount $account): array
    {
        return $this->checkTokenHealth($account, ['w_member_social']);
    }

    public function getAccountInsights(SocialAccount $account, array $options = []): array
    {
        $validation = $this->validatePermissions($account);
        if (!$validation['valid']) {
            return $this->standardResult(false, [], $validation['error_code'], $validation['reason']);
        }

        $token = $this->getAccessToken($account);
        $orgUrn = $this->resolveOrganizationUrn($account);

        try {
            // Share statistics endpoint
            $resp = Http::withToken($token)
                ->withHeaders([
                    'LinkedIn-Version' => $this->apiVersion,
                    'X-Restli-Protocol-Version' => '2.0.0',
                ])
                ->timeout(15)
                ->get("{$this->baseUrl}/organizationalEntityShareStatistics", [
                    'q' => 'organizationalEntity',
                    'organizationalEntity' => $orgUrn,
                ]);

            $metrics = [
                'impressions'     => 0.0,
                'reach'           => 0.0,
                'clicks'          => 0.0,
                'likes'           => 0.0,
                'comments'        => 0.0,
                'shares'          => 0.0,
                'engagement_rate' => 0.0,
            ];

            if ($resp->successful()) {
                $elements = $resp->json('elements', []);
                if (!empty($elements[0]['totalShareStatistics'])) {
                    $stats = $elements[0]['totalShareStatistics'];
                    $metrics['impressions'] = (float) ($stats['impressionCount'] ?? 0);
                    $metrics['reach']       = (float) ($stats['uniqueImpressionsCount'] ?? 0);
                    $metrics['clicks']      = (float) ($stats['clickCount'] ?? 0);
                    $metrics['likes']       = (float) ($stats['likeCount'] ?? 0);
                    $metrics['comments']    = (float) ($stats['commentCount'] ?? 0);
                    $metrics['shares']      = (float) ($stats['shareCount'] ?? 0);
                    $metrics['engagement_rate'] = (float) ($stats['engagement'] ?? 0) * 100;
                }
            }

            // Follower stats
            $followerStatsResp = Http::withToken($token)
                ->withHeaders([
                    'LinkedIn-Version' => $this->apiVersion,
                    'X-Restli-Protocol-Version' => '2.0.0',
                ])
                ->timeout(10)
                ->get("{$this->baseUrl}/organizationalEntityFollowerStatistics", [
                    'q' => 'organizationalEntity',
                    'organizationalEntity' => $orgUrn,
                ]);

            if ($followerStatsResp->successful()) {
                $followerElem = $followerStatsResp->json('elements.0.followerGains', []);
                $metrics['followers'] = (float) ($followerElem['organicFollowerGain'] ?? 0);
            }

            return $this->standardResult(true, $metrics, null, null, ['org_urn' => $orgUrn]);
        } catch (\Throwable $e) {
            return $this->standardResult(false, [], 'LINKEDIN_FETCH_FAILED', $e->getMessage());
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
            // LinkedIn post share stats
            $shareUrn = str_starts_with($externalPostId, 'urn:li:') ? $externalPostId : "urn:li:share:{$externalPostId}";

            $resp = Http::withToken($token)
                ->withHeaders([
                    'LinkedIn-Version' => $this->apiVersion,
                    'X-Restli-Protocol-Version' => '2.0.0',
                ])
                ->timeout(12)
                ->get("{$this->baseUrl}/organizationalEntityShareStatistics", [
                    'q' => 'shares',
                    'shares' => "List({$shareUrn})",
                ]);

            $metrics = [
                'impressions' => 0.0,
                'reach'       => 0.0,
                'clicks'      => 0.0,
                'likes'       => 0.0,
                'comments'    => 0.0,
                'shares'      => 0.0,
                'engagement'  => 0.0,
            ];

            if ($resp->successful()) {
                $elements = $resp->json('elements', []);
                if (!empty($elements[0]['totalShareStatistics'])) {
                    $stats = $elements[0]['totalShareStatistics'];
                    $metrics['impressions'] = (float) ($stats['impressionCount'] ?? 0);
                    $metrics['reach']       = (float) ($stats['uniqueImpressionsCount'] ?? 0);
                    $metrics['clicks']      = (float) ($stats['clickCount'] ?? 0);
                    $metrics['likes']       = (float) ($stats['likeCount'] ?? 0);
                    $metrics['comments']    = (float) ($stats['commentCount'] ?? 0);
                    $metrics['shares']      = (float) ($stats['shareCount'] ?? 0);
                    $metrics['engagement']  = $metrics['clicks'] + $metrics['likes'] + $metrics['comments'] + $metrics['shares'];
                }
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

        // Return empty dimensions with status note
        return $this->standardResult(true, [
            'dimensions' => [],
            'status'     => 'permission_required',
            'note'       => 'LinkedIn demographic follower breakdown requires Community Management API partner approval.',
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
        return $rawMetrics;
    }

    private function resolveOrganizationUrn(SocialAccount $account): string
    {
        $metadata = $account->metadata ?? [];
        if (!empty($metadata['organization_urn'])) {
            return (string) $metadata['organization_urn'];
        }

        $id = $account->provider_account_id;
        return str_starts_with($id, 'urn:li:') ? $id : "urn:li:organization:{$id}";
    }
}
