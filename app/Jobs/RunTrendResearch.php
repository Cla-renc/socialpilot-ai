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
        $engineUrl = trim((string) (config('services.trends.engine_url') ?: config('services.ai.engine_url', 'http://127.0.0.1:8080')));
        $internalToken = trim((string) (config('services.trends.internal_token') ?: config('services.ai.internal_token', '')));

        if ($engineUrl === '' || $internalToken === '') {
            throw new RuntimeException('Trend research engine is not configured.');
        }

        $research->update([
            'status'        => 'running',
            'started_at'    => now(),
            'error_message' => null,
        ]);

        $brand = $research->brand;
        $response = Http::acceptJson()
            ->withToken($internalToken)
            ->connectTimeout(5)
            ->timeout(60)
            ->post(rtrim($engineUrl, '/').'/internal/v1/research', [
                'research_id' => $research->id,
                'brand'       => [
                    'id'                 => $brand->id,
                    'name'               => $brand->name,
                    'industry'           => $brand->industry,
                    'niche'              => $brand->niche,
                    'category'           => $brand->category ?? null,
                    'description'        => $brand->description ?? null,
                    'audience'           => $brand->audience,
                    'audience_interests' => $brand->audience_interests,
                    'region'             => $brand->region,
                    'language'           => $brand->language,
                    'tone'               => $brand->tone,
                    'settings'           => $brand->settings?->only([
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
                            'title'    => $feedback->topic?->title,
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
                            'id'               => $historical->id,
                            'title'            => $historical->title,
                            'normalized_title' => $historical->normalized_title,
                            'relevance_score'  => $historical->relevance_score,
                            'opportunity_score' => $historical->opportunity_score,
                            'momentum'         => $historical->momentum,
                            'category'         => $historical->category,
                            'researched_at'    => $historical->researched_at?->toIso8601String(),
                            'source_count'     => $historical->sources_count ?? 1,
                        ])
                        ->values()
                        ->all(),
                ],
                'filters' => array_merge($research->filters, [
                    'rss_feeds'          => array_values(config('services.trends.rss_feeds', [])),
                    'min_relevance_score' => (int) config('services.trends.min_relevance_score', 25),
                ]),
            ]);

        $response->throw();
        $payload   = $response->json();
        $validated = Validator::make($payload, [
            'topics'                           => ['present', 'array', 'max:100'],
            'topics.*.title'                   => ['required', 'string', 'max:255'],
            'topics.*.description'             => ['nullable', 'string', 'max:5000'],
            'topics.*.summary'                 => ['nullable', 'string', 'max:10000'],
            'topics.*.content_angles'          => ['nullable', 'array', 'max:10'],
            'topics.*.content_angles.*'        => ['string', 'max:1000'],
            'topics.*.content_angle_status'    => ['nullable', 'string', 'in:disabled,configuration_required,completed,no_suggestions,failed'],
            'topics.*.content_angle_error'     => ['nullable', 'string', 'max:1000'],
            'topics.*.category'                => ['nullable', 'string', 'max:80'],
            'topics.*.relevance_score'         => ['required', 'integer', 'between:0,100'],
            'topics.*.relevance_reason'        => ['required', 'string', 'max:5000'],
            'topics.*.momentum'                => ['nullable', 'string', 'in:emerging,growing,established,declining,time_sensitive,evergreen'],
            'topics.*.momentum_evidence'       => ['nullable', 'string', 'max:5000'],
            'topics.*.geographic_market'       => ['nullable', 'string', 'max:128'],
            'topics.*.opportunity_score'       => ['required', 'integer', 'between:0,100'],
            'topics.*.sources'                 => ['required', 'array', 'min:1'],
            'topics.*.sources.*.source_key'    => ['required', 'string', 'max:80'],
            'topics.*.sources.*.source_name'   => ['required', 'string', 'max:255'],
            'topics.*.sources.*.title'         => ['required', 'string', 'max:500'],
            'topics.*.sources.*.url'           => ['required', 'url', 'max:2048'],
            'topics.*.sources.*.published_at'  => ['nullable', 'date'],
            'topics.*.sources.*.summary'       => ['nullable', 'string', 'max:5000'],
            'topics.*.sources.*.evidence'      => ['nullable', 'string', 'max:5000'],
            'topics.*.sources.*.metrics'       => ['nullable', 'array'],
            'sources'                          => ['required', 'array'],
            'sources.*.source_key'             => ['required', 'string', 'max:80'],
            'sources.*.source_name'            => ['nullable', 'string', 'max:255'],
            'sources.*.status'                 => ['required', 'string', 'in:ready,success,partial,error,configuration_required,not_implemented'],
            'sources.*.message'                => ['nullable', 'string', 'max:1000'],
            'sources.*.result_count'           => ['nullable', 'integer', 'min:0'],
            'result_state'                     => ['nullable', 'string', 'in:success,no_relevant_topics,all_sources_unavailable,configuration_required'],
            'filtered_out_count'               => ['nullable', 'integer', 'min:0'],
            'min_relevance_score'              => ['nullable', 'integer', 'between:0,100'],
        ])->validate();

        $sourceStatuses   = $validated['sources'];
        $successfulSources = collect($sourceStatuses)
            ->whereIn('status', ['ready', 'success', 'partial'])
            ->count();

        // ── Update TrendSource registry ───────────────────────────────────────
        foreach ($sourceStatuses as $sourceStatus) {
            $source = TrendSource::query()->firstOrCreate(
                ['source_key' => $sourceStatus['source_key']],
                [
                    'name'              => $sourceStatus['source_name'] ?? Str::headline($sourceStatus['source_key']),
                    'type'              => $sourceStatus['source_key'],
                    'connection_status' => 'configuration_required',
                ],
            );
            if ($source && in_array($sourceStatus['status'], ['error', 'configuration_required'], true)) {
                $source->update([
                    'connection_status' => $sourceStatus['status'],
                    'last_error'        => $sourceStatus['message'] ?? null,
                ]);
            } elseif ($source && in_array($sourceStatus['status'], ['success', 'partial'], true)) {
                $source->update([
                    'connection_status'          => 'ready',
                    'last_successful_research_at' => now(),
                    'last_error'                 => null,
                ]);
            }
        }

        // ── Derive result_state: prefer value from FastAPI; derive locally as fallback ──
        $resultState = $validated['result_state'] ?? null;
        if ($resultState === null) {
            $allConfigRequired = collect($sourceStatuses)->every(
                fn (array $s): bool => in_array($s['status'], ['configuration_required', 'not_implemented'], true)
            );
            $resultState = match (true) {
                $allConfigRequired              => 'configuration_required',
                $successfulSources === 0        => 'all_sources_unavailable',
                count($validated['topics']) === 0 => 'no_relevant_topics',
                default                         => 'success',
            };
        }

        // ── Build result_meta preserving zero values explicitly ───────────────
        $resultMeta = [];
        if (isset($validated['filtered_out_count'])) {
            $resultMeta['filtered_out_count'] = (int) $validated['filtered_out_count'];
        }
        if (isset($validated['min_relevance_score'])) {
            $resultMeta['min_relevance_score'] = (int) $validated['min_relevance_score'];
        }

        // ── When all sources are unavailable, fail the job immediately ────────
        if ($successfulSources === 0) {
            $messages = collect($sourceStatuses)->pluck('message')->filter()->implode(' ');
            // Persist source_status and result_state before failing
            $research->update([
                'source_status' => $sourceStatuses,
                'result_state'  => $resultState,
                'result_meta'   => $resultMeta ?: null,
            ]);
            throw new RuntimeException($messages ?: 'No configured research source returned results.');
        }

        // ── Persist topics and sources ────────────────────────────────────────
        foreach ($validated['topics'] as $topicData) {
            $title     = Str::squish($topicData['title']);
            $normalized = Str::lower($title);
            $topic = TrendingTopic::query()->firstOrCreate(
                [
                    'research_job_id' => $research->id,
                    'normalized_title' => $normalized,
                ],
                [
                    'brand_id'             => $research->brand_id,
                    'title'                => $title,
                    'description'          => $topicData['description'] ?? null,
                    'summary'              => $topicData['summary'] ?? null,
                    'content_angles'       => $topicData['content_angles'] ?? [],
                    'content_angle_status' => $topicData['content_angle_status'] ?? 'unavailable',
                    'content_angle_error'  => $topicData['content_angle_error'] ?? null,
                    'category'             => $topicData['category'] ?? 'general',
                    'relevance_score'      => $topicData['relevance_score'],
                    'relevance_reason'     => $topicData['relevance_reason'],
                    'momentum'             => $topicData['momentum'] ?? null,
                    'momentum_evidence'    => $topicData['momentum_evidence'] ?? null,
                    'geographic_market'    => $topicData['geographic_market'] ?? null,
                    'opportunity_score'    => $topicData['opportunity_score'],
                    'researched_at'        => now(),
                ],
            );

            foreach ($topicData['sources'] as $sourceData) {
                $source = TrendSource::query()->firstOrCreate(
                    ['source_key' => $sourceData['source_key']],
                    [
                        'name'              => $sourceData['source_name'],
                        'type'              => $sourceData['source_key'],
                        'connection_status' => 'ready',
                    ],
                );
                $source->update([
                    'last_successful_research_at' => now(),
                    'last_error'                  => null,
                ]);
                TrendTopicSource::query()->firstOrCreate(
                    [
                        'trend_topic_id' => $topic->id,
                        'url_hash'       => hash('sha256', $sourceData['url']),
                    ],
                    [
                        'trend_source_id' => $source->id,
                        'source_key'      => $sourceData['source_key'],
                        'source_name'     => $sourceData['source_name'],
                        'title'           => $sourceData['title'],
                        'url'             => $sourceData['url'],
                        'published_at'    => $sourceData['published_at'] ?? null,
                        'summary'         => $sourceData['summary'] ?? null,
                        'evidence'        => $sourceData['evidence'] ?? null,
                        'metrics'         => Arr::only($sourceData['metrics'] ?? [], ['views', 'likes', 'comments', 'interest']),
                    ],
                );
            }
        }

        $hasFailures = collect($sourceStatuses)->contains(fn (array $source): bool => in_array(
            $source['status'],
            ['error', 'configuration_required', 'not_implemented'],
            true,
        ));

        // ── Single atomic final update: status + result_state + result_meta ───
        // This is the ONE authoritative write for all completion fields.
        // result_state and result_meta must always be written here together
        // with status and completed_at to guarantee consistency.
        $research->update([
            'status'        => $hasFailures ? 'partial' : 'completed',
            'completed_at'  => now(),
            'source_status' => $sourceStatuses,
            'result_state'  => $resultState,
            'result_meta'   => $resultMeta ?: null,
        ]);
    }

    public function failed(?Throwable $exception): void
    {
        $research = TrendResearchRecord::query()->find($this->researchJobId);
        if (! $research) {
            return;
        }

        $research->update([
            'status'        => 'failed',
            'result_state'  => $research->result_state ?? 'all_sources_unavailable',
            'error_message' => Str::limit($exception?->getMessage() ?? 'Trend research failed.', 2000),
            'completed_at'  => now(),
        ]);
    }
}
