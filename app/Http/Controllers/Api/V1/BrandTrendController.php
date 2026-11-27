<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\BrandTrendPreference;
use App\Models\TrendFeedback;
use App\Models\TrendRecommendation;
use App\Models\TrendResearchJob;
use App\Models\TrendingTopic;
use App\Services\TrendResearchService;
use App\Services\TrendSourceRegistry;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BrandTrendController extends Controller
{
    public function __construct(
        private readonly TrendResearchService $research,
        private readonly TrendSourceRegistry $sources,
    ) {}

    public function startResearch(Request $request, Brand $brand)
    {
        $this->authorizeBrand($request, $brand);
        $validated = $request->validate([
            'categories' => ['nullable', 'array', 'max:20'],
            'categories.*' => ['string', Rule::in([...array_keys(config('brand.content_categories')), 'general'])],
            'geographic_market' => ['nullable', 'string', 'max:128'],
            'days' => ['nullable', 'integer', Rule::in([7, 30, 90])],
            'sources' => ['nullable', 'array', 'max:10'],
            'sources.*' => ['string', Rule::in(['rss', 'news_api', 'reddit', 'youtube', 'google_trends'])],
            'sync' => ['nullable', 'boolean'],
            'force' => ['nullable', 'boolean'],
        ]);
        $sync = $request->boolean('sync');
        [$job, $created] = $this->research->start($brand, $request->user(), $validated, $sync);

        $statusCode = $sync ? ($job->status === 'failed' ? 502 : 200) : ($created ? 202 : 200);

        return response()->json([
            'data' => $this->jobPayload($job, $sync),
            'message' => $sync
                ? ($job->status === 'completed' || $job->status === 'partial' ? 'Trend research completed successfully.' : 'Trend research failed.')
                : ($created ? 'Trend research queued.' : 'Matching trend research is already active.'),
        ], $statusCode);
    }

    public function researchHistory(Request $request, Brand $brand)
    {
        $this->authorizeBrand($request, $brand);
        $jobs = $brand->trendResearchJobs()->latest()->paginate(20);

        return response()->json([
            'data' => $jobs->getCollection()->map(fn (TrendResearchJob $job): array => $this->jobPayload($job)),
            'meta' => [
                'current_page' => $jobs->currentPage(),
                'last_page' => $jobs->lastPage(),
                'per_page' => $jobs->perPage(),
                'total' => $jobs->total(),
            ],
        ]);
    }

    public function researchStatus(Request $request, Brand $brand)
    {
        $this->authorizeBrand($request, $brand);
        $job = $brand->trendResearchJobs()->latest()->first();

        return response()->json(['data' => $job ? $this->jobPayload($job) : null]);
    }

    public function showResearch(Request $request, Brand $brand, TrendResearchJob $researchJob)
    {
        $this->authorizeBrand($request, $brand);
        abort_unless($researchJob->brand_id === $brand->id, 404);
        $researchJob->load('topics.sources');

        return response()->json(['data' => $this->jobPayload($researchJob, true)]);
    }

    public function index(Request $request, Brand $brand)
    {
        $this->authorizeBrand($request, $brand);
        $validated = $request->validate([
            'category' => ['nullable', 'string', 'max:80'],
            'min_relevance' => ['nullable', 'integer', 'between:0,100'],
            'momentum' => ['nullable', Rule::in(['emerging', 'growing', 'established', 'declining', 'time_sensitive', 'evergreen'])],
            'source' => ['nullable', 'string', 'max:80'],
            'geographic_market' => ['nullable', 'string', 'max:128'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'saved_only' => ['nullable', 'boolean'],
            'cross_platform_only' => ['nullable', 'boolean'],
            'cluster_id' => ['nullable', 'string', 'max:64'],
            'sort' => ['nullable', Rule::in(['opportunity', 'relevance', 'recent'])],
            'per_page' => ['nullable', 'integer', 'between:1,50'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $query = $brand->trendingTopics()
            ->with([
                'sources',
                'researchJob:id,status,created_at',
                'feedback' => fn ($feedback) => $feedback->where('user_id', $request->user()->id),
            ])
            ->whereDoesntHave('feedback', fn ($feedback) => $feedback
                ->where('user_id', $request->user()->id)
                ->where('feedback_type', 'dismissed'));

        foreach ([
            'category' => 'category',
            'momentum' => 'momentum',
            'geographic_market' => 'geographic_market',
        ] as $parameter => $column) {
            if (! empty($validated[$parameter])) {
                $query->where($column, $validated[$parameter]);
            }
        }
        if (isset($validated['min_relevance'])) {
            $query->where('relevance_score', '>=', $validated['min_relevance']);
        }
        if (! empty($validated['source'])) {
            $query->whereHas('sources', fn ($sources) => $sources->where('source_key', $validated['source']));
        }
        if (! empty($validated['from'])) {
            $query->whereDate('researched_at', '>=', $validated['from']);
        }
        if (! empty($validated['to'])) {
            $query->whereDate('researched_at', '<=', $validated['to']);
        }
        if ($request->boolean('saved_only')) {
            $query->whereHas('feedback', fn ($feedback) => $feedback
                ->where('user_id', $request->user()->id)
                ->where('feedback_type', 'saved'));
        }
        if ($request->boolean('cross_platform_only')) {
            $query->where('is_cross_platform', true);
        }
        if (! empty($validated['cluster_id'])) {
            $query->where('cluster_id', $validated['cluster_id']);
        }

        match ($validated['sort'] ?? 'recent') {
            'opportunity' => $query->orderByDesc('opportunity_score')->orderByDesc('researched_at'),
            'relevance' => $query->orderByDesc('relevance_score')->orderByDesc('researched_at'),
            default => $query->orderByDesc('researched_at'),
        };
        $topics = $query->paginate($validated['per_page'] ?? 20);

        return response()->json([
            'data' => $topics->getCollection()->map(fn (TrendingTopic $topic): array => $this->topicPayload($topic)),
            'meta' => [
                'current_page' => $topics->currentPage(),
                'last_page' => $topics->lastPage(),
                'per_page' => $topics->perPage(),
                'total' => $topics->total(),
            ],
        ]);
    }

    public function show(Request $request, Brand $brand, TrendingTopic $topic)
    {
        $this->authorizeBrand($request, $brand);
        abort_unless($topic->brand_id === $brand->id, 404);
        $topic->load([
            'sources',
            'researchJob:id,status,created_at',
            'feedback' => fn ($feedback) => $feedback->where('user_id', $request->user()->id),
        ]);

        return response()->json(['data' => $this->topicPayload($topic)]);
    }

    public function categories(Request $request, Brand $brand)
    {
        $this->authorizeBrand($request, $brand);
        $categories = $brand->trendingTopics()->distinct()->orderBy('category')->pluck('category');

        return response()->json(['data' => $categories]);
    }

    public function sourceList(Request $request, Brand $brand)
    {
        $this->authorizeBrand($request, $brand);

        return response()->json(['data' => $this->sources->all()]);
    }

    public function preferences(Request $request, Brand $brand)
    {
        $this->authorizeBrand($request, $brand);
        $preference = $brand->trendPreference()->first();

        return response()->json([
            'data' => $preference?->toArray() ?? [
                'brand_id' => $brand->id,
                'research_frequency' => 'manual',
                'research_time' => null,
                'timezone' => 'UTC',
                'preferred_sources' => [],
                'categories' => [],
                'geographic_market' => $brand->region,
            ],
        ]);
    }

    public function updatePreferences(Request $request, Brand $brand)
    {
        $this->authorizeBrand($request, $brand);
        $validated = $request->validate([
            'research_frequency' => ['required', Rule::in(['manual', 'daily', 'weekly'])],
            'research_time' => ['nullable', 'date_format:H:i'],
            'timezone' => ['required', 'timezone'],
            'preferred_sources' => ['nullable', 'array', 'max:10'],
            'preferred_sources.*' => ['string', Rule::in(['rss', 'news_api', 'reddit', 'youtube', 'google_trends'])],
            'categories' => ['nullable', 'array', 'max:20'],
            'categories.*' => ['string', Rule::in([...array_keys(config('brand.content_categories')), 'general'])],
            'geographic_market' => ['nullable', 'string', 'max:128'],
        ]);
        $preference = BrandTrendPreference::query()->updateOrCreate(
            ['brand_id' => $brand->id],
            $validated,
        );

        return response()->json(['data' => $preference], 200);
    }

    public function feedback(Request $request, Brand $brand, TrendingTopic $topic)
    {
        $this->authorizeBrand($request, $brand);
        abort_unless($topic->brand_id === $brand->id, 404);
        $validated = $request->validate([
            'feedback' => ['required', Rule::in(['relevant', 'irrelevant', 'saved', 'dismissed'])],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);
        $feedback = TrendFeedback::query()->updateOrCreate(
            ['trend_topic_id' => $topic->id, 'user_id' => $request->user()->id],
            ['feedback_type' => $validated['feedback'], 'note' => $validated['note'] ?? null],
        );

        return response()->json(['data' => $feedback]);
    }

    public function recommendations(Request $request, Brand $brand)
    {
        $this->authorizeBrand($request, $brand);
        $validated = $request->validate([
            'status' => ['nullable', Rule::in(['generated', 'reviewed', 'saved', 'dismissed', 'used'])],
            'classification' => ['nullable', Rule::in(['direct', 'adjacent', 'weak'])],
            'platform' => ['nullable', 'string', 'max:32'],
            'format' => ['nullable', 'string', 'max:40'],
            'min_score' => ['nullable', 'integer', 'between:0,100'],
            'sort' => ['nullable', Rule::in(['score', 'recent'])],
            'per_page' => ['nullable', 'integer', 'between:1,50'],
        ]);

        $query = $brand->trendRecommendations()->with(['topic.sources']);

        if (! empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }
        if (! empty($validated['classification'])) {
            $query->where('relevance_classification', $validated['classification']);
        }
        if (! empty($validated['platform'])) {
            $query->where('target_platform', $validated['platform']);
        }
        if (! empty($validated['format'])) {
            $query->where('suggested_format', $validated['format']);
        }
        if (isset($validated['min_score'])) {
            $query->where('score', '>=', $validated['min_score']);
        }

        match ($validated['sort'] ?? 'score') {
            'recent' => $query->latest('updated_at'),
            default => $query->orderByDesc('score')->latest('updated_at'),
        };

        $recommendations = $query->paginate($validated['per_page'] ?? 20);

        return response()->json([
            'data' => $recommendations->getCollection()->map(fn (TrendRecommendation $rec): array => $this->recommendationPayload($rec)),
            'meta' => [
                'current_page' => $recommendations->currentPage(),
                'last_page' => $recommendations->lastPage(),
                'per_page' => $recommendations->perPage(),
                'total' => $recommendations->total(),
            ],
        ]);
    }

    public function showRecommendation(Request $request, Brand $brand, TrendRecommendation $recommendation)
    {
        $this->authorizeBrand($request, $brand);
        abort_unless($recommendation->brand_id === $brand->id, 404);
        $recommendation->load(['topic.sources']);

        return response()->json(['data' => $this->recommendationPayload($recommendation)]);
    }

    public function updateRecommendationStatus(Request $request, Brand $brand, TrendRecommendation $recommendation)
    {
        $this->authorizeBrand($request, $brand);
        abort_unless($recommendation->brand_id === $brand->id, 404);
        $validated = $request->validate([
            'status' => ['required', Rule::in(['generated', 'reviewed', 'saved', 'dismissed', 'used'])],
        ]);

        $recommendation->update(['status' => $validated['status']]);

        return response()->json(['data' => $this->recommendationPayload($recommendation)]);
    }

    public function recommendationFeedback(Request $request, Brand $brand, TrendRecommendation $recommendation)
    {
        $this->authorizeBrand($request, $brand);
        abort_unless($recommendation->brand_id === $brand->id, 404);
        $validated = $request->validate([
            'feedback' => ['required', Rule::in(['relevant', 'not_relevant'])],
        ]);

        $recommendation->update(['feedback_status' => $validated['feedback']]);

        // Sync feedback to topic
        if ($recommendation->trend_topic_id) {
            TrendFeedback::query()->updateOrCreate(
                ['trend_topic_id' => $recommendation->trend_topic_id, 'user_id' => $request->user()->id],
                ['feedback_type' => $validated['feedback'] === 'relevant' ? 'relevant' : 'irrelevant'],
            );
        }

        return response()->json(['data' => $this->recommendationPayload($recommendation)]);
    }

    private function authorizeBrand(Request $request, Brand $brand): void
    {
        abort_unless($brand->user_id === $request->user()->id, 404, 'Brand not found.');
    }

    private function jobPayload(TrendResearchJob $job, bool $includeTopics = false): array
    {
        $payload = [
            'id' => $job->id,
            'brand_id' => $job->brand_id,
            'status' => $job->status,
            'result_state' => $job->result_state ?? null,
            'result_meta' => $job->result_meta ?? null,
            'filters' => $job->filters,
            'sources' => $job->source_status ?? [],
            'error_message' => $job->error_message,
            'created_at' => $job->created_at,
            'started_at' => $job->started_at,
            'completed_at' => $job->completed_at,
        ];
        if ($includeTopics) {
            $payload['topics'] = $job->topics->map(fn (TrendingTopic $topic): array => $this->topicPayload($topic));
        }

        return $payload;
    }

    private function topicPayload(TrendingTopic $topic): array
    {
        return [
            'id' => $topic->id,
            'brand_id' => $topic->brand_id,
            'research_job_id' => $topic->research_job_id,
            'title' => $topic->title,
            'description' => $topic->description,
            'summary' => $topic->summary,
            'content_angles' => $topic->content_angles ?? [],
            'content_angle_status' => $topic->content_angle_status,
            'content_angle_error' => $topic->content_angle_error,
            'category' => $topic->category,
            'relevance_score' => $topic->relevance_score,
            'relevance_reason' => $topic->relevance_reason,
            'momentum' => $topic->momentum,
            'momentum_evidence' => $topic->momentum_evidence,
            'geographic_market' => $topic->geographic_market,
            'opportunity_score' => $topic->opportunity_score,
            'cluster_id' => $topic->cluster_id,
            'participating_platforms' => $topic->participating_platforms ?? [],
            'is_cross_platform' => (bool) $topic->is_cross_platform,
            'cluster_confidence' => $topic->cluster_confidence,
            'researched_at' => $topic->researched_at,
            'source_count' => $topic->sources->count(),
            'sources' => $topic->sources->map(fn ($source): array => [
                'source_key' => $source->source_key,
                'source_name' => $source->source_name,
                'title' => $source->title,
                'url' => $source->url,
                'published_at' => $source->published_at,
                'summary' => $source->summary,
                'evidence' => $source->evidence,
                'metrics' => $source->metrics,
            ]),
            'feedback' => $topic->feedback->first()?->feedback_type,
            'recommendation' => $topic->relationLoaded('recommendation') && $topic->recommendation
                ? $this->recommendationPayload($topic->recommendation)
                : null,
        ];
    }

    private function recommendationPayload(TrendRecommendation $rec): array
    {
        return [
            'id' => $rec->id,
            'brand_id' => $rec->brand_id,
            'trend_topic_id' => $rec->trend_topic_id,
            'cluster_id' => $rec->cluster_id,
            'score' => $rec->score,
            'relevance_classification' => $rec->relevance_classification,
            'explanation' => $rec->explanation,
            'content_package' => $rec->content_package,
            'target_platform' => $rec->target_platform,
            'suggested_format' => $rec->suggested_format,
            'status' => $rec->status,
            'feedback_status' => $rec->feedback_status,
            'created_at' => $rec->created_at,
            'updated_at' => $rec->updated_at,
            'topic' => $rec->relationLoaded('topic') && $rec->topic ? [
                'id' => $rec->topic->id,
                'title' => $rec->topic->title,
                'category' => $rec->topic->category,
                'relevance_score' => $rec->topic->relevance_score,
                'opportunity_score' => $rec->topic->opportunity_score,
                'momentum' => $rec->topic->momentum,
                'sources_count' => $rec->topic->relationLoaded('sources') ? $rec->topic->sources->count() : 0,
            ] : null,
        ];
    }
}
