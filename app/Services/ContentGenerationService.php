<?php

namespace App\Services;

use App\Models\AgentExecution;
use App\Models\Brand;
use App\Models\BrandContentDraft;
use App\Models\ContentGenerationJob;
use App\Models\ContentRevision;
use App\Models\ContentTrendSource;
use App\Models\ContentVariation;
use App\Models\TrendingTopic;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class ContentGenerationService
{
    public function __construct(
        protected DashboardNotificationService $notificationService,
    ) {}

    public function generateForJob(ContentGenerationJob $job): BrandContentDraft
    {
        // 1. Fast-path check for idempotency & retry safety:
        // If a draft is already linked or exists for this job, return it without re-generating.
        $existingDraft = BrandContentDraft::where('generation_job_id', $job->id)->first();
        if ($existingDraft) {
            if ($job->status !== 'completed' || ! $job->result_draft_id) {
                $job->update([
                    'status' => 'completed',
                    'progress' => 100,
                    'result_draft_id' => $existingDraft->id,
                    'completed_at' => $job->completed_at ?? now(),
                ]);
            }

            return $existingDraft->fresh(['variations', 'revisions', 'trendSources']);
        }

        $job->update([
            'status' => 'processing',
            'progress' => 20,
            'started_at' => $job->started_at ?? now(),
            'error_message' => null,
        ]);

        $brand = $job->brand()->with(['settings', 'socialAccounts'])->firstOrFail();
        $user = $job->user;

        // Load optional trend
        $trendData = null;
        if ($job->trend_id) {
            $trend = TrendingTopic::query()->with('sources')->find($job->trend_id);
            if ($trend) {
                $trendData = [
                    'id' => $trend->id,
                    'title' => $trend->title,
                    'description' => $trend->summary ?? $trend->source_excerpt,
                    'momentum' => $trend->momentum,
                    'relevance_score' => $trend->relevance_score,
                    'geographic_market' => $trend->geographic_market,
                    'sources' => $trend->sources->map(fn ($s) => [
                        'source_name' => $s->source_name,
                        'url' => $s->source_url,
                        'title' => $s->title,
                    ])->all(),
                ];
            }
        }

        $job->update(['progress' => 40]);

        $brandPayload = $this->buildBrandPayload($brand);
        $engineUrl = config('services.ai.engine_url') ?: config('services.trends.engine_url', 'http://127.0.0.1:8080');
        $token = config('services.ai.internal_token') ?: config('services.trends.internal_token', '');

        $provider = $job->provider ?? 'internal';
        $model = $job->model ?? null;

        $responsePayload = null;

        // Attempt calling Python AI Engine (OUTSIDE DATABASE TRANSACTION)
        if ($engineUrl && $token) {
            try {
                $response = Http::acceptJson()
                    ->withToken($token)
                    ->timeout(45)
                    ->post(rtrim($engineUrl, '/').'/internal/v1/content/generate', [
                        'brand' => $brandPayload,
                        'objective' => $job->objective,
                        'category' => $job->category,
                        'platform' => $job->platform,
                        'content_format' => $job->content_format,
                        'instructions' => $job->instructions,
                        'trend' => $trendData,
                        'provider' => $provider,
                        'model' => $model,
                        'include_variations' => true,
                        'variation_count' => 3,
                    ]);

                if ($response->successful()) {
                    $responsePayload = $response->json();
                } else {
                    $errorDetail = $response->json('detail') ?? $response->body();
                    if ($provider !== 'internal') {
                        throw new RuntimeException("AI provider generation error: {$errorDetail}");
                    }
                }
            } catch (Throwable $e) {
                if ($provider !== 'internal') {
                    $job->update([
                        'status' => 'failed',
                        'error_message' => $e->getMessage(),
                        'completed_at' => now(),
                    ]);
                    $this->logExecution($user, $brand, 'failed', $e->getMessage());
                    throw $e;
                }
            }
        }

        // Fallback internal generation if Python engine is not running in tests
        if (! $responsePayload) {
            $responsePayload = $this->generateLocalDeterministic(
                $brand,
                $job->objective ?? 'engagement',
                $job->category ?? 'educational',
                $job->platform,
                $job->content_format ?? 'post',
                $job->instructions,
                $trendData
            );
        }

        $job->update(['progress' => 80]);

        // 2. Transactional persistence:
        // Wrap draft creation, initial revision, variations, trend source, and job completion in a DB transaction
        $draft = DB::transaction(function () use ($job, $user, $brand, $responsePayload, $provider, $model) {
            // Lock job record to serialize concurrent operations
            $lockedJob = ContentGenerationJob::where('id', $job->id)->lockForUpdate()->firstOrFail();

            // Double check within transaction if another worker already persisted the draft
            $existingDraft = BrandContentDraft::where('generation_job_id', $lockedJob->id)->lockForUpdate()->first();
            if ($existingDraft) {
                if ($lockedJob->status !== 'completed' || ! $lockedJob->result_draft_id) {
                    $lockedJob->update([
                        'status' => 'completed',
                        'progress' => 100,
                        'result_draft_id' => $existingDraft->id,
                        'completed_at' => $lockedJob->completed_at ?? now(),
                    ]);
                }

                return $existingDraft;
            }

            try {
                // Persist BrandContentDraft
                $newDraft = BrandContentDraft::create([
                    'user_id' => $user->id,
                    'brand_id' => $brand->id,
                    'generation_job_id' => $lockedJob->id,
                    'title' => $responsePayload['title'] ?? ($brand->name.' '.$lockedJob->platform.' post'),
                    'platform' => $lockedJob->platform,
                    'content_type' => 'post',
                    'content_format' => $lockedJob->content_format,
                    'objective' => $responsePayload['objective'] ?? $lockedJob->objective,
                    'category' => $responsePayload['category'] ?? $lockedJob->category,
                    'status' => 'draft',
                    'prompt' => $lockedJob->instructions ?? ($lockedJob->objective.' post for '.$brand->name),
                    'hook' => $responsePayload['hook'] ?? null,
                    'body' => $responsePayload['body'] ?? '',
                    'caption' => $responsePayload['caption'] ?? null,
                    'call_to_action' => $responsePayload['call_to_action'] ?? null,
                    'hashtags' => $responsePayload['hashtags'] ?? [],
                    'quality_score' => $responsePayload['quality_score'] ?? 85,
                    'quality_evaluation' => $responsePayload['quality_evaluation'] ?? [],
                    'ai_provider' => $responsePayload['ai_provider'] ?? $provider,
                    'ai_model' => $responsePayload['ai_model'] ?? $model ?? 'socialpilot-lite',
                    'is_ai_generated' => ($responsePayload['ai_provider'] ?? $provider) !== 'internal',
                    'metadata' => [
                        'strategy' => $responsePayload['strategy'] ?? null,
                        'format_notes' => $responsePayload['format_notes'] ?? null,
                        'trend_id' => $lockedJob->trend_id,
                    ],
                ]);
            } catch (UniqueConstraintViolationException $e) {
                // If a concurrent thread inserted the draft first, gracefully recover and load it
                $existingDraft = BrandContentDraft::where('generation_job_id', $lockedJob->id)->first();
                if ($existingDraft) {
                    $lockedJob->update([
                        'status' => 'completed',
                        'progress' => 100,
                        'result_draft_id' => $existingDraft->id,
                        'completed_at' => $lockedJob->completed_at ?? now(),
                    ]);

                    return $existingDraft;
                }

                throw $e;
            }

            // Create initial revision (Revision 1)
            ContentRevision::create([
                'draft_id' => $newDraft->id,
                'user_id' => $user->id,
                'revision_number' => 1,
                'action' => 'initial_generation',
                'instructions' => $lockedJob->instructions,
                'title' => $newDraft->title,
                'hook' => $newDraft->hook,
                'body' => $newDraft->body,
                'caption' => $newDraft->caption,
                'call_to_action' => $newDraft->call_to_action,
                'hashtags' => $newDraft->hashtags,
                'quality_score' => $newDraft->quality_score,
            ]);

            // Associate Trend Source if trend used
            if ($lockedJob->trend_id) {
                ContentTrendSource::firstOrCreate([
                    'draft_id' => $newDraft->id,
                    'trending_topic_id' => $lockedJob->trend_id,
                ], [
                    'relationship_type' => 'primary',
                ]);
            }

            // Persist variations if generated
            if (! empty($responsePayload['variations']) && is_array($responsePayload['variations'])) {
                foreach ($responsePayload['variations'] as $v) {
                    ContentVariation::create([
                        'draft_id' => $newDraft->id,
                        'user_id' => $user->id,
                        'brand_id' => $brand->id,
                        'style' => $v['style'] ?? 'conversational',
                        'title' => $v['title'] ?? $newDraft->title,
                        'hook' => $v['hook'] ?? null,
                        'body' => $v['body'] ?? $newDraft->body,
                        'caption' => $v['caption'] ?? $newDraft->caption,
                        'call_to_action' => $v['call_to_action'] ?? $newDraft->call_to_action,
                        'hashtags' => $v['hashtags'] ?? $newDraft->hashtags,
                        'quality_score' => $v['quality_score'] ?? 80,
                        'quality_evaluation' => $v['quality_evaluation'] ?? [],
                        'is_selected' => false,
                    ]);
                }
            }

            // Update Job as completed
            $lockedJob->update([
                'status' => 'completed',
                'progress' => 100,
                'result_draft_id' => $newDraft->id,
                'completed_at' => now(),
            ]);

            return $newDraft;
        });

        // 3. Post-transaction notifications & audit logging (outside transaction)
        $this->notificationService->notify(
            $user,
            'Content draft generated',
            "'{$draft->title}' was successfully created for {$brand->name}.",
            $brand->id,
            '/content/'.$draft->id
        );

        $this->logExecution($user, $brand, 'completed', "Draft #{$draft->id} generated for {$brand->name}");

        return $draft->fresh(['variations', 'revisions', 'trendSources']);
    }

    public function buildBrandPayload(Brand $brand): array
    {
        $settings = $brand->settings;

        return [
            'id' => $brand->id,
            'name' => $brand->name,
            'industry' => $brand->industry,
            'niche' => $brand->niche,
            'description' => $brand->description,
            'audience' => $brand->audience,
            'audience_interests' => $brand->audience_interests,
            'region' => $brand->region,
            'language' => $brand->language,
            'tone' => $brand->tone,
            'connected_platforms' => $brand->socialAccounts->pluck('provider')->unique()->values()->all(),
            'settings' => $settings ? [
                'brand_voice' => $settings->brand_voice,
                'writing_style' => $settings->writing_style,
                'brand_keywords' => $settings->brand_keywords,
                'content_formats' => $settings->content_formats,
                'marketing_objectives' => $settings->marketing_objectives,
                'content_preferences' => $settings->content_preferences,
                'restrictions' => $settings->restrictions,
                'hashtag_preferences' => $settings->hashtag_preferences ?? [],
            ] : [],
        ];
    }

    protected function logExecution(User $user, Brand $brand, string $status, ?string $message = null, string $agentKey = 'content_writing'): void
    {
        AgentExecution::create([
            'user_id' => $user->id,
            'brand_id' => $brand->id,
            'agent_key' => $agentKey,
            'status' => $status,
            'message' => $message,
            'started_at' => now(),
            'completed_at' => now(),
        ]);
    }

    protected function generateLocalDeterministic(
        Brand $brand,
        string $objective,
        string $category,
        string $platform,
        ?string $contentFormat = 'post',
        ?string $instructions = null,
        ?array $trend = null
    ): array {
        $topic = $trend ? $trend['title'] : ($instructions ?: ($brand->niche ?: 'Daily Standards'));
        $hook = "A practical principle regarding {$topic} for {$brand->name}:";
        $body = "In {$brand->industry}, consistent standards outperform intermittent motivation.\n\n"
            ."At {$brand->name}, we focus on execution, transparent systems, and sustainable momentum.\n\n"
            ."1. Protect the fundamentals.\n"
            ."2. Simplify the daily workflow.\n"
            .'3. Measure what matters.';
        $caption = "{$hook}\n\nSwipe through or save for later.";
        $cta = 'What standard are you focusing on this week? Drop your thoughts below.';
        $hashtags = ['#'.str_replace(' ', '', $brand->name), '#'.str_replace(' ', '', $brand->niche ?: 'Growth'), '#Execution'];

        return [
            'title' => "{$brand->name}: {$topic}",
            'hook' => $hook,
            'body' => $body,
            'caption' => $caption,
            'call_to_action' => $cta,
            'hashtags' => $hashtags,
            'objective' => $objective,
            'category' => $category,
            'quality_score' => 88,
            'quality_evaluation' => [
                'quality_score' => 88,
                'criteria_scores' => [
                    'brand_alignment' => 90,
                    'audience_relevance' => 85,
                    'platform_suitability' => 90,
                    'clarity' => 90,
                    'originality' => 85,
                    'readability' => 90,
                    'factual_reliability' => 90,
                    'cta_relevance' => 90,
                    'language_quality' => 95,
                ],
                'issues' => [],
                'suggestions' => [],
            ],
            'variations' => [
                [
                    'style' => 'conversational',
                    'style_label' => 'Conversational',
                    'title' => "{$brand->name}: {$topic} (Conversational)",
                    'hook' => "Let's be honest about {$topic}:",
                    'body' => "Most people overcomplicate {$topic}.\n\nShow up consistently, execute the basics, and let results speak for themselves.",
                    'caption' => "Let's be honest about {$topic}.",
                    'call_to_action' => 'What is your biggest bottleneck with this right now?',
                    'hashtags' => $hashtags,
                    'quality_score' => 85,
                    'quality_evaluation' => [],
                ],
                [
                    'style' => 'bold',
                    'style_label' => 'Bold & Contrarian',
                    'title' => "{$brand->name}: {$topic} (Bold)",
                    'hook' => "Why conventional wisdom about {$topic} is holding you back:",
                    'body' => "Shortcuts don't build long-term standards.\n\nRaise your baseline and stop accepting mediocre execution.",
                    'caption' => "Why conventional wisdom about {$topic} fails.",
                    'call_to_action' => 'Agree or disagree? Share below.',
                    'hashtags' => $hashtags,
                    'quality_score' => 86,
                    'quality_evaluation' => [],
                ],
            ],
            'ai_provider' => 'internal',
            'ai_model' => 'socialpilot-lite',
            'format_notes' => "Formatted for {$platform}.",
        ];
    }
}
