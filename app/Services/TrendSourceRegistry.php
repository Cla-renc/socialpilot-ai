<?php

namespace App\Services;

use App\Models\TrendSource;

class TrendSourceRegistry
{
    public function all()
    {
        $sources = [
            ['rss', 'Configured RSS feeds', 'rss'],
            ['news_api', 'News API', 'news'],
            ['reddit', 'Reddit API', 'discussion'],
            ['youtube', 'YouTube Data API', 'video'],
            ['google_trends', 'Google Trends', 'search_interest'],
        ];

        return collect($sources)->map(function (array $source): array {
            [$key, $name, $type] = $source;
            $record = TrendSource::query()->firstOrCreate(
                ['source_key' => $key],
                ['name' => $name, 'type' => $type],
            );

            // All 5 providers have supported connector implementations
            $isImplemented = true;

            $isConfigured = match ($key) {
                'rss' => (bool) count(config('services.trends.rss_feeds', [])),
                'news_api' => (bool) config('services.trends.news_api_key'),
                'reddit' => (bool) (config('services.trends.reddit_client_id') && config('services.trends.reddit_client_secret')),
                'youtube' => (bool) config('services.trends.youtube_api_key'),
                'google_trends' => true, // Supported via Google Trends official Daily & Realtime Trends RSS feed
                default => false,
            };

            if ($record->name !== $name || $record->type !== $type) {
                $record->update(['name' => $name, 'type' => $type]);
            }

            $connectionStatus = ! $isImplemented
                ? 'not_implemented'
                : ($isConfigured
                    ? ($record->connection_status === 'error' ? 'error' : 'ready')
                    : 'configuration_required');

            return [
                'key' => $key,
                'name' => $name,
                'type' => $type,
                'connection_status' => $connectionStatus,
                'last_successful_research_at' => $record->last_successful_research_at,
                'last_error' => $record->last_error,
                'rate_limit_resets_at' => $record->rate_limit_resets_at,
            ];
        })->values();
    }
}
