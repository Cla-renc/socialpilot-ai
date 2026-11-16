<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\BrandTrendPreference;
use App\Models\TrendFeedback;
use App\Models\TrendingTopic;
use App\Services\TrendResearchService;
use App\Services\TrendSourceRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TrendIntelligenceController extends Controller
{
    public function __construct(
        private readonly TrendResearchService $research,
        private readonly TrendSourceRegistry $sources,
    ) {}

    public function index(Request $request, Brand $brand): View
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
            'saved' => ['nullable', 'boolean'],
            'sort' => ['nullable', Rule::in(['opportunity', 'relevance', 'recent'])],
        ]);
        $topics = $brand->trendingTopics()
            ->with([
                'sources',
                'feedback' => fn ($feedback) => $feedback->where('user_id', $request->user()->id),
            ])
            ->whereDoesntHave('feedback', fn ($feedback) => $feedback
                ->where('user_id', $request->user()->id)
                ->where('feedback_type', 'dismissed'));

        foreach (['category', 'momentum', 'geographic_market'] as $field) {
            if (! empty($validated[$field])) {
                $topics->where($field, $validated[$field]);
            }
        }
        if (isset($validated['min_relevance'])) {
            $topics->where('relevance_score', '>=', $validated['min_relevance']);
        }
        if (! empty($validated['source'])) {
            $topics->whereHas('sources', fn ($sourceQuery) => $sourceQuery->where('source_key', $validated['source']));
        }
        if (! empty($validated['from'])) {
            $topics->whereDate('researched_at', '>=', $validated['from']);
        }
        if (! empty($validated['to'])) {
            $topics->whereDate('researched_at', '<=', $validated['to']);
        }
        if ($request->boolean('saved')) {
            $topics->whereHas('feedback', fn ($feedback) => $feedback
                ->where('user_id', $request->user()->id)
                ->where('feedback_type', 'saved'));
        }

        match ($validated['sort'] ?? 'recent') {
            'opportunity' => $topics->orderByDesc('opportunity_score')->orderByDesc('researched_at'),
            'relevance' => $topics->orderByDesc('relevance_score')->orderByDesc('researched_at'),
            default => $topics->orderByDesc('researched_at'),
        };

        return view('brands.trends.index', [
            'brand' => $brand->loadMissing('settings'),
            'brands' => $request->user()->brands()->orderBy('name')->get(['id', 'name']),
            'topics' => $topics->paginate(20)->withQueryString(),
            'jobs' => $brand->trendResearchJobs()->latest()->limit(5)->get(),
            'categories' => $brand->trendingTopics()->distinct()->orderBy('category')->pluck('category'),
            'categoryOptions' => config('brand.content_categories'),
            'sources' => $this->sources->all(),
            'preference' => $brand->trendPreference()->first(),
            'sourceStatuses' => $this->sources->all(),
            'metrics' => [
                'topics' => $brand->trendingTopics()->count(),
                'high_relevance' => $brand->trendingTopics()->where('relevance_score', '>=', 75)->count(),
                'emerging' => $brand->trendingTopics()->whereIn('momentum', ['emerging', 'growing'])->count(),
                'running' => $brand->trendResearchJobs()->whereIn('status', ['queued', 'running'])->count(),
            ],
            'lastResearch' => $brand->trendResearchJobs()->latest()->first(),
        ]);
    }

    public function show(Request $request, Brand $brand, TrendingTopic $topic): View
    {
        $this->authorizeBrand($request, $brand);
        abort_unless($topic->brand_id === $brand->id, 404);
        $topic->load([
            'sources',
            'researchJob:id,status,created_at',
            'feedback' => fn ($feedback) => $feedback->where('user_id', $request->user()->id),
        ]);

        return view('brands.trends.show', compact('brand', 'topic'));
    }

    public function startResearch(Request $request, Brand $brand): RedirectResponse
    {
        $this->authorizeBrand($request, $brand);
        $validated = $request->validate([
            'categories' => ['nullable', 'array', 'max:20'],
            'categories.*' => ['string', Rule::in([...array_keys(config('brand.content_categories')), 'general'])],
            'geographic_market' => ['nullable', 'string', 'max:128'],
            'days' => ['required', 'integer', Rule::in([7, 30, 90])],
            'sources' => ['nullable', 'array', 'max:10'],
            'sources.*' => ['string', Rule::in(['rss', 'news_api', 'reddit', 'youtube', 'google_trends'])],
        ]);
        [$job, $created] = $this->research->start($brand, $request->user(), $validated);

        return redirect()->route('brands.trends.index', $brand)
            ->with('success', $created ? 'Trend research was queued. Refresh this page to check its status.' : 'Matching trend research is already active.');
    }

    public function feedback(Request $request, Brand $brand, TrendingTopic $topic): RedirectResponse
    {
        $this->authorizeBrand($request, $brand);
        abort_unless($topic->brand_id === $brand->id, 404);
        $validated = $request->validate([
            'feedback' => ['required', Rule::in(['relevant', 'irrelevant', 'saved', 'dismissed'])],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);
        TrendFeedback::query()->updateOrCreate(
            ['trend_topic_id' => $topic->id, 'user_id' => $request->user()->id],
            ['feedback_type' => $validated['feedback'], 'note' => $validated['note'] ?? null],
        );

        return redirect()->route(
            $validated['feedback'] === 'dismissed' ? 'brands.trends.index' : 'brands.trends.show',
            $validated['feedback'] === 'dismissed' ? $brand : [$brand, $topic],
        )->with('success', 'Trend feedback saved.');
    }

    public function updatePreferences(Request $request, Brand $brand): RedirectResponse
    {
        $this->authorizeBrand($request, $brand);
        $validated = $request->validate([
            'research_frequency' => ['required', Rule::in(['manual', 'daily', 'weekly'])],
            'research_time' => ['required_if:research_frequency,daily,weekly', 'nullable', 'date_format:H:i'],
            'timezone' => ['required', 'timezone'],
            'preferred_sources' => ['nullable', 'array', 'max:10'],
            'preferred_sources.*' => ['string', Rule::in(['rss', 'news_api', 'reddit', 'youtube', 'google_trends'])],
            'categories' => ['nullable', 'array', 'max:20'],
            'categories.*' => ['string', Rule::in([...array_keys(config('brand.content_categories')), 'general'])],
            'geographic_market' => ['nullable', 'string', 'max:128'],
        ]);
        BrandTrendPreference::query()->updateOrCreate(['brand_id' => $brand->id], $validated);

        return redirect()->route('brands.trends.index', $brand)->with('success', 'Trend research preferences saved.');
    }

    private function authorizeBrand(Request $request, Brand $brand): void
    {
        abort_unless($brand->user_id === $request->user()->id, 404, 'Brand not found.');
    }
}
