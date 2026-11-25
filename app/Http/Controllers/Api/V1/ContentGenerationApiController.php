<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Jobs\RunContentGeneration;
use App\Models\Brand;
use App\Models\ContentGenerationJob;
use App\Models\TrendingTopic;
use App\Services\ContentGenerationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Throwable;

class ContentGenerationApiController extends Controller
{
    public function __construct(
        protected ContentGenerationService $generationService,
    ) {}

    public function generate(Request $request, Brand $brand): JsonResponse
    {
        abort_unless($brand->user_id === $request->user()->id, 403, 'Unauthorized brand access.');

        // Normalize empty string values for optional attributes from web forms
        if ($request->has('trend_id') && ($request->input('trend_id') === '' || $request->input('trend_id') === null)) {
            $request->merge(['trend_id' => null]);
        }
        if ($request->has('category') && ($request->input('category') === '' || $request->input('category') === null)) {
            $request->merge(['category' => null]);
        }
        if ($request->has('content_format') && ($request->input('content_format') === '' || $request->input('content_format') === null)) {
            $request->merge(['content_format' => null]);
        }

        $validated = $request->validate([
            'objective' => ['nullable', 'string', 'in:awareness,engagement,education,community_building,brand_authority,product_promotion,website_traffic,lead_generation'],
            'category' => ['nullable', 'string', 'max:100'],
            'platform' => ['nullable', 'string', 'in:linkedin,instagram,tiktok,youtube,facebook'],
            'content_format' => ['nullable', 'string', 'in:post,text_post,caption,short_script,long_form,educational_post,storytelling_post,linkedin_article,thread,video_outline,article'],
            'instructions' => ['nullable', 'string', 'max:2000'],
            'trend_id' => ['nullable', 'integer', 'exists:trending_topics,id'],
            'provider' => ['nullable', 'string', 'in:internal,ollama,openai,gemini,groq,openrouter'],
            'model' => ['nullable', 'string', 'max:100'],
            'sync' => ['nullable', 'boolean'],
        ]);

        // If trend_id provided, ensure topic belongs to the brand
        if (! empty($validated['trend_id'])) {
            $topic = TrendingTopic::query()->where('brand_id', $brand->id)->find($validated['trend_id']);
            abort_unless($topic, 404, 'Trending topic not found for this brand.');
        }

        $job = ContentGenerationJob::create([
            'user_id' => $request->user()->id,
            'brand_id' => $brand->id,
            'status' => 'queued',
            'progress' => 0,
            'objective' => $validated['objective'] ?? 'engagement',
            'category' => $validated['category'] ?? 'educational',
            'platform' => $validated['platform'] ?? 'linkedin',
            'content_format' => $validated['content_format'] ?? 'post',
            'instructions' => $validated['instructions'] ?? null,
            'trend_id' => $validated['trend_id'] ?? null,
            'provider' => $validated['provider'] ?? 'internal',
            'model' => $validated['model'] ?? null,
        ]);

        if ($request->boolean('sync')) {
            $draft = $this->generationService->generateForJob($job);

            return response()->json([
                'message' => 'Content generation completed.',
                'data' => $this->transformJob($job->fresh()),
                'draft' => $draft,
            ], 201);
        }

        RunContentGeneration::dispatch($job->id)->afterCommit();

        return response()->json([
            'message' => 'Content generation queued successfully.',
            'data' => $this->transformJob($job),
        ], 202);
    }

    public function status(Request $request, ContentGenerationJob $generation): JsonResponse
    {
        abort_unless($generation->user_id === $request->user()->id, 403, 'Unauthorized generation job access.');

        return response()->json([
            'data' => $this->transformJob($generation),
        ]);
    }

    public function strategy(Request $request, Brand $brand): JsonResponse
    {
        abort_unless($brand->user_id === $request->user()->id, 403, 'Unauthorized brand access.');

        $validated = $request->validate([
            'objective' => ['nullable', 'string'],
            'category' => ['nullable', 'string'],
            'platform' => ['nullable', 'string'],
            'trend_id' => ['nullable', 'integer', 'exists:trending_topics,id'],
            'instructions' => ['nullable', 'string'],
        ]);

        $trendData = null;
        if (! empty($validated['trend_id'])) {
            $topic = TrendingTopic::query()->with('sources')->where('brand_id', $brand->id)->find($validated['trend_id']);
            if ($topic) {
                $trendData = [
                    'id' => $topic->id,
                    'title' => $topic->title,
                    'momentum' => $topic->momentum,
                    'relevance_score' => $topic->relevance_score,
                    'sources' => $topic->sources->map(fn ($s) => ['source_name' => $s->source_name, 'url' => $s->source_url])->all(),
                ];
            }
        }

        $engineUrl = config('services.ai.engine_url') ?: config('services.trends.engine_url', 'http://127.0.0.1:8080');
        $token = config('services.ai.internal_token') ?: config('services.trends.internal_token', '');

        $strategy = null;
        if ($engineUrl && $token) {
            try {
                $resp = Http::acceptJson()->withToken($token)->timeout(15)->post(rtrim($engineUrl, '/').'/internal/v1/content/strategy', [
                    'brand' => $this->generationService->buildBrandPayload($brand),
                    'objective' => $validated['objective'] ?? 'engagement',
                    'category' => $validated['category'] ?? 'educational',
                    'platform' => $validated['platform'] ?? 'linkedin',
                    'trend' => $trendData,
                    'instructions' => $validated['instructions'] ?? null,
                ]);
                if ($resp->successful()) {
                    $strategy = $resp->json();
                }
            } catch (Throwable $e) {
                // local fallback
            }
        }

        if (! $strategy) {
            $strategy = [
                'topic' => $trendData ? $trendData['title'] : (($validated['instructions'] ?? null) ?: ($brand->niche ?: 'Foundational Standards')),
                'objective' => $validated['objective'] ?? 'engagement',
                'category' => $validated['category'] ?? 'educational',
                'recommended_angle' => "Practical breakdown of core principles for {$brand->name}.",
                'main_message' => "Consistent daily standards yield compounding results in {$brand->industry}.",
                'suggested_format' => 'thought_leadership',
                'reason' => 'Anchored in verified brand attributes and core audience objectives.',
                'trend_references' => $trendData ? [$trendData] : [],
            ];
        }

        return response()->json([
            'data' => $strategy,
        ]);
    }

    protected function transformJob(ContentGenerationJob $job): array
    {
        return [
            'id' => $job->id,
            'brand_id' => $job->brand_id,
            'status' => $job->status,
            'progress' => $job->progress,
            'objective' => $job->objective,
            'category' => $job->category,
            'platform' => $job->platform,
            'content_format' => $job->content_format,
            'instructions' => $job->instructions,
            'trend_id' => $job->trend_id,
            'provider' => $job->provider,
            'model' => $job->model,
            'error_message' => $job->error_message,
            'result_draft_id' => $job->result_draft_id,
            'started_at' => $job->started_at?->toISOString(),
            'completed_at' => $job->completed_at?->toISOString(),
            'created_at' => $job->created_at?->toISOString(),
            'updated_at' => $job->updated_at?->toISOString(),
            'result_draft' => $job->result_draft_id ? $job->resultDraft : null,
        ];
    }
}
