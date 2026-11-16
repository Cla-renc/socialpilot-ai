<?php

namespace App\Jobs;

use App\Models\TrendFeedback;
use App\Models\TrendingTopic;
use App\Models\TrendResearchJob as TrendResearchRecord;
use App\Models\TrendSource;
use App\Models\TrendTopicSource;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class RunTrendResearch implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 90;

    public function __construct(public readonly int $researchJobId) {}

    /**
     * @throws ConnectionException|RequestException
     */
    public function handle(): void
    {
        $research = TrendResearchRecord::query()->with(['brand.settings'])->findOrFail($this->researchJobId);
        $engineUrl = trim((string) config('services.trends.engine_url'));
        $internalToken = trim((string) config('services.trends.internal_token'));

        if ($engineUrl === '' || $internalToken === '') {
            throw new RuntimeException('Trend research engine is not configured.');
        }

        $research->update([
            'status' => 'running',
            'started_at' => now(),
            'error_message' => null,
        ]);

        $brand = $research->brand;
        $response = Http::acceptJson()
            ->withToken($internalToken)
            ->connectTimeout(5)
            ->timeout(60)
            ->post(rtrim($engineUrl, '/').'/internal/v1/research', [
                'research_id' => $research->id,
                'brand' => [
                    'id' => $brand->id,
                    'name' => $brand->name,
                    'industry' => $brand->industry,
                    'niche' => $brand->niche,
                    'audience' => $brand->audience,
                    'audience_interests' => $brand->audience_interests,
                    'region' => $brand->region,
                    'language' => $brand->language,
                    'tone' => $brand->tone,
                    'settings' => $brand->settings?->only([
                        'brand_voice',
                        'writing_style',
                        'brand_keywords',
                        'content_formats',
                        'marketing_objectives',
                        'content_preferences',
                    ]) ?? [],
                    'trend_feedback' => TrendFeedback::query()
                        ->where('user_id', $research->user_id)
                        ->whereHas('topic', fn ($query) => $query->where('brand_id', $brand->id))
                        ->with('topic:id,title')
                        ->latest('updated_at')
                        ->limit(50)
                        ->get()
                        ->map(fn (TrendFeedback $feedback): array => [
                            'feedback' => $feedback->feedback_type,
                            'title' => $feedback->topic?->title,
                        ])
                        ->values()
                        ->all(),
                    'historical_topics' => TrendingTopic::query()
                        ->where('brand_id', $brand->id)
                        ->where('research_job_id', '!=', $research->id)
                        ->withCount('sources')
                        ->latest('researched_at')
                        ->limit(100)
                        ->get()
                        ->map(fn (TrendingTopic $historical): array => [
                            'id' => $historical->id,
                            'title' => $historical->title,
                            'normalized_title' => $historical->normalized_title,
                            'relevance_score' => $historical->relevance_score,
                            'opportunity_score' => $historical->opportunity_score,
                            'momentum' => $historical->momentum,
                            'category' => $historical->category,
                            'researched_at' => $historical->researched_at?->toIso8601String(),
                            'source_count' => $historical->sources_count ?? 1,
                        ])
                        ->values()
                        ->all(),
                ],
                'filters' => $research->filters,
            ]);

        $response->throw();
        $payload = $response->json();
        $validated = Validator::make($payload, [
            'topics' => ['present', 'array', 'max:100'],
            'topics.*.title' => ['required', 'string', 'max:255'],
            'topics.*.description' => ['nullable', 'string', 'max:5000'],
            'topics.*.summary' => ['nullable', 'string', 'max:10000'],
            'topics.*.content_angles' => ['nullable', 'array', 'max:10'],
            'topics.*.content_angles.*' => ['string', 'max:1000'],
            'topics.*.content_angle_status' => ['nullable', 'string', 'in:disabled,configuration_required,completed,no_suggestions,failed'],
            'topics.*.content_angle_error' => ['nullable', 'string', 'max:1000'],
            'topics.*.category' => ['nullable', 'string', 'max:80'],
            'topics.*.relevance_score' => ['required', 'integer', 'between:0,100'],
            'topics.*.relevance_reason' => ['required', 'string', 'max:5000'],
            'topics.*.momentum' => ['nullable', 'string', 'in:emerging,growing,established,declining,time_sensitive,evergreen'],
            'topics.*.momentum_evidence' => ['nullable', 'string', 'max:5000'],
            'topics.*.geographic_market' => ['nullable', 'string', 'max:128'],
            'topics.*.opportunity_score' => ['required', 'integer', 'between:0,100'],
            'topics.*.sources' => ['required', 'array', 'min:1'],
            'topics.*.sources.*.source_key' => ['required', 'string', 'max:80'],
            'topics.*.sources.*.source_name' => ['required', 'string', 'max:255'],
            'topics.*.sources.*.title' => ['required', 'string', 'max:500'],
            'topics.*.sources.*.url' => ['required', 'url', 'max:2048'],
            'topics.*.sources.*.published_at' => ['nullable', 'date'],
            'topics.*.sources.*.summary' => ['nullable', 'string', 'max:5000'],
            'topics.*.sources.*.evidence' => ['nullable', 'string', 'max:5000'],
            'topics.*.sources.*.metrics' => ['nullable', 'array'],
            'sources' => ['required', 'array'],
            'sources.*.source_key' => ['required', 'string', 'max:80'],
            'sources.*.source_name' => ['nullable', 'string', 'max:255'],
            'sources.*.status' => ['required', 'string', 'in:ready,success,partial,error,configuration_required,not_implemented'],
            'sources.*.message' => ['nullable', 'string', 'max:1000'],
            'sources.*.result_count' => ['nullable', 'integer', 'min:0'],
        ])->validate();

        $sourceStatuses = $validated['sources'];
        $successfulSources = collect($sourceStatuses)
            ->whereIn('status', ['ready', 'success', 'partial'])
            ->count();

        foreach ($sourceStatuses as $sourceStatus) {
            $source = TrendSource::query()->firstOrCreate(
                ['source_key' => $sourceStatus['source_key']],
                [
                    'name' => $sourceStatus['source_name'] ?? Str::headline($sourceStatus['source_key']),
                    'type' => $sourceStatus['source_key'],
                    'connection_status' => 'configuration_required',
                ],
            );
            if ($source && in_array($sourceStatus['status'], ['error', 'configuration_required'], true)) {
                $source->update([
                    'connection_status' => $sourceStatus['status'],
                    'last_error' => $sourceStatus['message'] ?? null,
                ]);
            } elseif ($source && in_array($sourceStatus['status'], ['success', 'partial'], true)) {
                $source->update([
                    'connection_status' => 'ready',
                    'last_successful_research_at' => now(),
                    'last_error' => null,
                ]);
            }
        }

        $research->update(['source_status' => $sourceStatuses]);
        if ($successfulSources === 0) {
            $messages = collect($sourceStatuses)->pluck('message')->filter()->implode(' ');
            throw new RuntimeException($messages ?: 'No configured research source returned results.');
        }

        foreach ($validated['topics'] as $topicData) {
            $title = Str::squish($topicData['title']);
            $normalized = Str::lower($title);
            $topic = TrendingTopic::query()->firstOrCreate(
                [
                    'research_job_id' => $research->id,
                    'normalized_title' => $normalized,
                ],
                [
                    'brand_id' => $research->brand_id,
                    'title' => $title,
                    'description' => $topicData['description'] ?? null,
                    'summary' => $topicData['summary'] ?? null,
                    'content_angles' => $topicData['content_angles'] ?? [],
                    'content_angle_status' => $topicData['content_angle_status'] ?? 'unavailable',
                    'content_angle_error' => $topicData['content_angle_error'] ?? null,
                    'category' => $topicData['category'] ?? 'general',
                    'relevance_score' => $topicData['relevance_score'],
                    'relevance_reason' => $topicData['relevance_reason'],
                    'momentum' => $topicData['momentum'] ?? null,
                    'momentum_evidence' => $topicData['momentum_evidence'] ?? null,
                    'geographic_market' => $topicData['geographic_market'] ?? null,
                    'opportunity_score' => $topicData['opportunity_score'],
                    'researched_at' => now(),
                ],
            );

            foreach ($topicData['sources'] as $sourceData) {
                $source = TrendSource::query()->firstOrCreate(
                    ['source_key' => $sourceData['source_key']],
                    [
                        'name' => $sourceData['source_name'],
                        'type' => $sourceData['source_key'],
                        'connection_status' => 'ready',
                    ],
                );
                $source->update([
                    'last_successful_research_at' => now(),
                    'last_error' => null,
                ]);
                TrendTopicSource::query()->firstOrCreate(
                    [
                        'trend_topic_id' => $topic->id,
                        'url_hash' => hash('sha256', $sourceData['url']),
                    ],
                    [
                        'trend_source_id' => $source->id,
                        'source_key' => $sourceData['source_key'],
                        'source_name' => $sourceData['source_name'],
                        'title' => $sourceData['title'],
                        'url' => $sourceData['url'],
                        'published_at' => $sourceData['published_at'] ?? null,
                        'summary' => $sourceData['summary'] ?? null,
                        'evidence' => $sourceData['evidence'] ?? null,
                        'metrics' => Arr::only($sourceData['metrics'] ?? [], ['views', 'likes', 'comments', 'interest']),
                    ],
                );
            }
        }

        $hasFailures = collect($sourceStatuses)->contains(fn (array $source): bool => in_array(
            $source['status'],
            ['error', 'configuration_required', 'not_implemented'],
            true,
        ));
        $research->update([
            'status' => $hasFailures ? 'partial' : 'completed',
            'source_status' => $sourceStatuses,
            'completed_at' => now(),
        ]);
    }

    public function failed(?Throwable $exception): void
    {
        $research = TrendResearchRecord::query()->find($this->researchJobId);
        if (! $research) {
            return;
        }

        $research->update([
            'status' => 'failed',
            'error_message' => Str::limit($exception?->getMessage() ?? 'Trend research failed.', 2000),
            'completed_at' => now(),
        ]);
    }
}
