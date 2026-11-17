<?php

namespace App\Services;

use App\Models\BrandContentDraft;
use App\Models\ContentRevision;
use App\Models\ContentVariation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Throwable;

class ContentVariationService
{
    public function generateVariations(BrandContentDraft $draft, User $user, int $count = 3): array
    {
        $brand = $draft->brand;
        $engineUrl = config('services.ai.engine_url') ?: config('services.trends.engine_url', 'http://127.0.0.1:8080');
        $token = config('services.ai.internal_token') ?: config('services.trends.internal_token', '');

        $variationsData = null;

        if ($engineUrl && $token) {
            try {
                $response = Http::acceptJson()
                    ->withToken($token)
                    ->timeout(30)
                    ->post(rtrim($engineUrl, '/').'/internal/v1/content/variations', [
                        'brand' => app(ContentGenerationService::class)->buildBrandPayload($brand),
                        'base_title' => $draft->title ?? $brand->name.' Post',
                        'base_body' => $draft->body,
                        'topic' => $draft->title ?? $brand->niche,
                        'platform' => $draft->platform,
                        'count' => $count,
                    ]);

                if ($response->successful()) {
                    $variationsData = $response->json('variations');
                }
            } catch (Throwable $e) {
                // Will use local fallback
            }
        }

        if (! $variationsData) {
            $variationsData = [
                [
                    'style' => 'conversational',
                    'title' => ($draft->title ?? 'Post').' (Conversational)',
                    'hook' => "Let's be direct about this:",
                    'body' => "Consistency always wins over intensity.\n\nHere is how we put that to work every day.",
                    'caption' => 'A conversational take on our core principles.',
                    'call_to_action' => 'What is working best for you right now?',
                    'hashtags' => $draft->hashtags ?? ['#Growth', '#Execution'],
                    'quality_score' => 84,
                    'quality_evaluation' => [],
                ],
                [
                    'style' => 'bold',
                    'title' => ($draft->title ?? 'Post').' (Bold)',
                    'hook' => 'The uncomfortable truth most people ignore:',
                    'body' => "You cannot shortcut foundational execution.\n\nRaise your standards and watch the difference it makes.",
                    'caption' => 'A bold perspective on foundational standards.',
                    'call_to_action' => 'Do you agree? Let us know below.',
                    'hashtags' => $draft->hashtags ?? ['#Mindset', '#Standards'],
                    'quality_score' => 86,
                    'quality_evaluation' => [],
                ],
            ];
        }

        $persisted = [];
        foreach ($variationsData as $v) {
            $persisted[] = ContentVariation::create([
                'draft_id' => $draft->id,
                'user_id' => $user->id,
                'brand_id' => $brand->id,
                'style' => $v['style'] ?? 'conversational',
                'title' => $v['title'] ?? $draft->title,
                'hook' => $v['hook'] ?? null,
                'body' => $v['body'] ?? $draft->body,
                'caption' => $v['caption'] ?? $draft->caption,
                'call_to_action' => $v['call_to_action'] ?? $draft->call_to_action,
                'hashtags' => $v['hashtags'] ?? $draft->hashtags,
                'quality_score' => $v['quality_score'] ?? 80,
                'quality_evaluation' => $v['quality_evaluation'] ?? [],
                'is_selected' => false,
            ]);
        }

        return $persisted;
    }

    public function selectVariation(BrandContentDraft $draft, ContentVariation $variation, User $user): BrandContentDraft
    {
        return DB::transaction(function () use ($draft, $variation, $user) {
            $lockedDraft = BrandContentDraft::where('id', $draft->id)->lockForUpdate()->firstOrFail();
            $lockedVariation = ContentVariation::where('id', $variation->id)->lockForUpdate()->firstOrFail();

            // Unset previous selection
            ContentVariation::where('draft_id', $lockedDraft->id)->update(['is_selected' => false]);
            $lockedVariation->update(['is_selected' => true]);

            // Next revision number safely allocated under lock
            $nextRevision = ($lockedDraft->revisions()->max('revision_number') ?? 0) + 1;

            // Apply variation to draft
            $lockedDraft->update([
                'title' => $lockedVariation->title ?: $lockedDraft->title,
                'hook' => $lockedVariation->hook ?: $lockedDraft->hook,
                'body' => $lockedVariation->body,
                'caption' => $lockedVariation->caption ?: $lockedDraft->caption,
                'call_to_action' => $lockedVariation->call_to_action ?: $lockedDraft->call_to_action,
                'hashtags' => $lockedVariation->hashtags ?: $lockedDraft->hashtags,
                'quality_score' => $lockedVariation->quality_score ?: $lockedDraft->quality_score,
                'selected_variation_id' => $lockedVariation->id,
            ]);

            // Record revision
            ContentRevision::create([
                'draft_id' => $lockedDraft->id,
                'user_id' => $user->id,
                'revision_number' => $nextRevision,
                'action' => 'select_variation',
                'instructions' => "Applied '{$lockedVariation->style}' variation #{$lockedVariation->id}",
                'title' => $lockedDraft->title,
                'hook' => $lockedDraft->hook,
                'body' => $lockedDraft->body,
                'caption' => $lockedDraft->caption,
                'call_to_action' => $lockedDraft->call_to_action,
                'hashtags' => $lockedDraft->hashtags,
                'quality_score' => $lockedDraft->quality_score,
            ]);

            return $lockedDraft->fresh(['variations', 'revisions']);
        });
    }
}
