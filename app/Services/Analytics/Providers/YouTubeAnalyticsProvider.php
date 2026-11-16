<?php

namespace App\Services\Analytics\Providers;

use App\Models\SocialAccount;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * YouTube Analytics Provider using YouTube Analytics API v2 and YouTube Data API v3.
 *
 * Official API: https://developers.google.com/youtube/analytics/reference/reports/query
 */
class YouTubeAnalyticsProvider extends AbstractAnalyticsProvider
{
    private string $analyticsBaseUrl = 'https://youtubeanalytics.googleapis.com/v2';
    private string $dataBaseUrl = 'https://www.googleapis.com/youtube/v3';

    public function platform(): string
    {
        return 'youtube';
    }

    public function getAvailableMetrics(): array
    {
        return [
            'subscribers' => [
                'name'         => 'Total Subscribers',
                'description'  => 'Current subscriber count for the YouTube channel.',
                'unit'         => 'count',
                'type'         => 'account',
                'availability' => 'available',
                'source'       => 'platform_api',
            ],
            'views' => [
                'name'         => 'Channel Views',
                'description'  => 'Total lifetime or period views across all channel videos.',
                'unit'         => 'count',
                'type'         => 'account',
                'availability' => 'available',
                'source'       => 'platform_api',
            ],
            'watch_time' => [
                'name'         => 'Watch Time',
                'description'  => 'Estimated minutes watched by viewers.',
                'unit'         => 'minutes',
                'type'         => 'account',
                'availability' => 'available',
                'source'       => 'platform_api',
            ],
            'average_view_duration' => [
                'name'         => 'Average View Duration',
                'description'  => 'Average length of video playback in seconds.',
                'unit'         => 'seconds',
                'type'         => 'account',
                'availability' => 'available',
                'source'       => 'platform_api',
            ],
            'subscribers_gained' => [
                'name'         => 'Subscribers Gained',
                'description'  => 'Number of new subscribers acquired during the period.',
                'unit'         => 'count',
                'type'         => 'account',
                'availability' => 'available',
                'source'       => 'platform_api',
            ],
            'subscribers_lost' => [
                'name'         => 'Subscribers Lost',
                'description'  => 'Number of subscribers who unsubscribed during the period.',
                'unit'         => 'count',
                'type'         => 'account',
                'availability' => 'available',
                'source'       => 'platform_api',
            ],
            'post_views' => [
                'name'         => 'Video Views',
                'description'  => 'Total view count for an individual published video.',
                'unit'         => 'count',
                'type'         => 'post',
                'availability' => 'available',
                'source'       => 'platform_api',
            ],
            'post_likes' => [
                'name'         => 'Video Likes',
                'description'  => 'Total likes for an individual video.',
                'unit'         => 'count',
                'type'         => 'post',
                'availability' => 'available',
                'source'       => 'platform_api',
            ],
            'post_comments' => [
                'name'         => 'Video Comments',
                'description'  => 'Total public comments on the video.',
                'unit'         => 'count',
                'type'         => 'post',
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
        return $this->checkTokenHealth($account, []);
    }

    public function getAccountInsights(SocialAccount $account, array $options = []): array
    {
        $validation = $this->validatePermissions($account);
        if (!$validation['valid']) {
            return $this->standardResult(false, [], $validation['error_code'], $validation['reason']);
        }

        $token = $this->getAccessToken($account);

        try {
            // 1. YouTube Data API v3 live channel stats
            $channelResp = Http::withToken($token)
                ->timeout(12)
                ->get("{$this->dataBaseUrl}/channels", [
                    'part' => 'statistics,snippet',
                    'mine' => 'true',
                ]);

            $subscribers = 0.0;
            $totalViews = 0.0;
            $videoCount = 0.0;

            if ($channelResp->successful()) {
                $items = $channelResp->json('items', []);
                if (!empty($items[0]['statistics'])) {
                    $stats = $items[0]['statistics'];
                    $subscribers = (float) ($stats['subscriberCount'] ?? 0);
                    $totalViews = (float) ($stats['viewCount'] ?? 0);
                    $videoCount = (float) ($stats['videoCount'] ?? 0);
                }
            }

            // 2. YouTube Analytics API v2 period report
            $startDate = $options['start_date'] ?? now()->subDays(30)->toDateString();
            $endDate = $options['end_date'] ?? now()->subDays(1)->toDateString(); // YT Analytics has ~24-48h processing delay

            $analyticsResp = Http::withToken($token)
                ->timeout(15)
                ->get("{$this->analyticsBaseUrl}/reports", [
                    'ids'        => 'channel==MINE',
                    'startDate'  => $startDate,
                    'endDate'    => $endDate,
                    'metrics'    => 'views,estimatedMinutesWatched,averageViewDuration,subscribersGained,subscribersLost,likes,comments,shares',
                ]);

            $reportData = [
                'subscribers'           => $subscribers,
                'total_channel_views'   => $totalViews,
                'video_count'           => $videoCount,
                'period_views'          => 0.0,
                'watch_time'            => 0.0,
                'average_view_duration' => 0.0,
                'subscribers_gained'    => 0.0,
                'subscribers_lost'      => 0.0,
                'likes'                 => 0.0,
                'comments'              => 0.0,
                'shares'                => 0.0,
            ];

            if ($analyticsResp->successful()) {
                $rows = $analyticsResp->json('rows', []);
                $headers = array_column($analyticsResp->json('columnHeaders', []), 'name');

                if (!empty($rows[0]) && !empty($headers)) {
                    $row = $rows[0];
                    foreach ($headers as $idx => $name) {
                        $val = (float) ($row[$idx] ?? 0);
                        if ($name === 'views') $reportData['period_views'] = $val;
                        elseif ($name === 'estimatedMinutesWatched') $reportData['watch_time'] = $val;
                        elseif ($name === 'averageViewDuration') $reportData['average_view_duration'] = $val;
                        elseif ($name === 'subscribersGained') $reportData['subscribers_gained'] = $val;
                        elseif ($name === 'subscribersLost') $reportData['subscribers_lost'] = $val;
                        elseif ($name === 'likes') $reportData['likes'] = $val;
                        elseif ($name === 'comments') $reportData['comments'] = $val;
                        elseif ($name === 'shares') $reportData['shares'] = $val;
                    }
                }
            }

            return $this->standardResult(true, $reportData, null, null, [
                'start_date' => $startDate,
                'end_date'   => $endDate,
            ]);
        } catch (\Throwable $e) {
            return $this->standardResult(false, [], 'YOUTUBE_FETCH_FAILED', $e->getMessage());
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
            // YouTube Data API video statistics
            $resp = Http::withToken($token)
                ->timeout(12)
                ->get("{$this->dataBaseUrl}/videos", [
                    'part' => 'statistics,snippet',
                    'id'   => $externalPostId,
                ]);

            if ($resp->failed()) {
                return $this->standardResult(false, [], 'YOUTUBE_VIDEO_FAILED', $resp->json('error.message', 'Could not retrieve video stats'));
            }

            $items = $resp->json('items', []);
            if (empty($items)) {
                return $this->standardResult(false, [], 'VIDEO_NOT_FOUND', "YouTube video {$externalPostId} not found");
            }

            $stats = $items[0]['statistics'] ?? [];
            $views = (float) ($stats['viewCount'] ?? 0);
            $likes = (float) ($stats['likeCount'] ?? 0);
            $comments = (float) ($stats['commentCount'] ?? 0);

            $metrics = [
                'views'      => $views,
                'likes'      => $likes,
                'comments'   => $comments,
                'engagement' => $likes + $comments,
            ];

            return $this->standardResult(true, [
                'external_post_id' => $externalPostId,
                'metrics'          => $metrics,
            ]);
        } catch (\Throwable $e) {
            return $this->standardResult(false, [], 'YOUTUBE_ERROR', $e->getMessage());
        }
    }

    public function getAudienceInsights(SocialAccount $account, array $options = []): array
    {
        $validation = $this->validatePermissions($account);
        if (!$validation['valid']) {
            return $this->standardResult(false, [], $validation['error_code'], $validation['reason']);
        }

        $token = $this->getAccessToken($account);

        try {
            $startDate = $options['start_date'] ?? now()->subDays(60)->toDateString();
            $endDate = $options['end_date'] ?? now()->subDays(2)->toDateString();

            // Geography report
            $geoResp = Http::withToken($token)
                ->timeout(15)
                ->get("{$this->analyticsBaseUrl}/reports", [
                    'ids'        => 'channel==MINE',
                    'startDate'  => $startDate,
                    'endDate'    => $endDate,
                    'metrics'    => 'views',
                    'dimensions' => 'country',
                    'sort'       => '-views',
                    'maxResults' => 10,
                ]);

            $dimensions = [];
            if ($geoResp->successful()) {
                foreach ($geoResp->json('rows', []) as $row) {
                    $dimensions[] = [
                        'insight_type'    => 'country',
                        'dimension'       => 'country_code',
                        'dimension_value' => (string) ($row[0] ?? ''),
                        'metric_value'    => (float) ($row[1] ?? 0),
                    ];
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

        try {
            $channelResp = Http::withToken($token)
                ->timeout(10)
                ->get("{$this->dataBaseUrl}/channels", [
                    'part' => 'statistics',
                    'mine' => 'true',
                ]);

            $subscribers = 0.0;
            if ($channelResp->successful()) {
                $subscribers = (float) ($channelResp->json('items.0.statistics.subscriberCount') ?? 0);
            }

            return $this->standardResult(true, [
                'followers'   => $subscribers,
                'metric_unit' => 'count',
            ]);
        } catch (\Throwable $e) {
            return $this->standardResult(false, [], 'SUBSCRIBERS_ERROR', $e->getMessage());
        }
    }

    public function normalizeMetrics(array $rawMetrics): array
    {
        return $rawMetrics;
    }
}
