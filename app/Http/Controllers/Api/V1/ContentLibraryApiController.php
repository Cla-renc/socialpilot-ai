<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\BrandContentDraft;
use App\Models\ContentRevision;
use App\Models\ContentVariation;
use App\Services\ContentImprovementService;
use App\Services\ContentVariationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContentLibraryApiController extends Controller
{
    public function __construct(
        protected ContentVariationService $variationService,
        protected ContentImprovementService $improvementService,
    ) {}

    public function index(Request $request, Brand $brand): JsonResponse
    {
        abort_unless($brand->user_id === $request->user()->id, 403, 'Unauthorized brand access.');

        $query = $brand->contentDrafts()->with(['trendSources.topic', 'variations']);

        // Filter: search
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('body', 'like', "%{$search}%")
                    ->orWhere('hook', 'like', "%{$search}%")
                    ->orWhere('caption', 'like', "%{$search}%");
            });
        }

        // Filter: platform
        if ($platform = $request->input('platform')) {
            $query->where('platform', strtolower($platform));
        }

        // Filter: category
        if ($category = $request->input('category')) {
            $query->where('category', strtolower($category));
        }

        // Filter: objective
        if ($objective = $request->input('objective')) {
            $query->where('objective', strtolower($objective));
        }

        // Filter: status
        if ($status = $request->input('status')) {
            $query->where('status', strtolower($status));
        }

        // Filter: date range
        if ($from = $request->input('from')) {
            $query->where('created_at', '>=', $from);
        }
        if ($to = $request->input('to')) {
            $query->where('created_at', '<=', $to);
        }

        // Sorting
        $sort = in_array($request->input('sort'), ['created_at', 'quality_score', 'title']) ? $request->input('sort') : 'created_at';
        $direction = strtolower($request->input('direction')) === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sort, $direction);

        $perPage = max(1, min((int) $request->input('per_page', 15), 50));
        $paginated = $query->paginate($perPage);

        return response()->json([
            'data' => $paginated->items(),
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
            ],
        ]);
    }

    public function show(Request $request, BrandContentDraft $content): JsonResponse
    {
        abort_unless($content->user_id === $request->user()->id, 403, 'Unauthorized content access.');

        $content->load(['variations', 'revisions', 'trendSources.topic.sources']);

        return response()->json([
            'data' => $content,
        ]);
    }

    public function update(Request $request, BrandContentDraft $content): JsonResponse
    {
        abort_unless($content->user_id === $request->user()->id, 403, 'Unauthorized content access.');

        $validated = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'hook' => ['nullable', 'string', 'max:1000'],
            'body' => ['required', 'string'],
            'caption' => ['nullable', 'string', 'max:2000'],
            'call_to_action' => ['nullable', 'string', 'max:500'],
            'hashtags' => ['nullable', 'array'],
            'hashtags.*' => ['string', 'max:100'],
            'platform' => ['nullable', 'string', 'in:linkedin,instagram,tiktok,youtube,facebook'],
            'status' => ['nullable', 'string', 'in:draft,scheduled,published'],
            'content_format' => ['nullable', 'string'],
            'objective' => ['nullable', 'string'],
            'category' => ['nullable', 'string'],
        ]);

        $content->fill($validated);
        $content->save();

        // Record a manual edit revision
        $nextRevision = ($content->revisions()->max('revision_number') ?? 0) + 1;
        ContentRevision::create([
            'draft_id' => $content->id,
            'user_id' => $request->user()->id,
            'revision_number' => $nextRevision,
            'action' => 'user_edit',
            'instructions' => 'Manual content edit by user.',
            'title' => $content->title,
            'hook' => $content->hook,
            'body' => $content->body,
            'caption' => $content->caption,
            'call_to_action' => $content->call_to_action,
            'hashtags' => $content->hashtags,
            'quality_score' => $content->quality_score,
        ]);

        return response()->json([
            'message' => 'Draft updated successfully.',
            'data' => $content->fresh(['variations', 'revisions', 'trendSources.topic']),
        ]);
    }

    public function destroy(Request $request, BrandContentDraft $content): JsonResponse
    {
        abort_unless($content->user_id === $request->user()->id, 403, 'Unauthorized content access.');

        $content->delete();

        return response()->json([
            'message' => 'Content draft deleted successfully.',
        ]);
    }

    public function generateVariations(Request $request, BrandContentDraft $content): JsonResponse
    {
        abort_unless($content->user_id === $request->user()->id, 403, 'Unauthorized content access.');

        $validated = $request->validate([
            'count' => ['nullable', 'integer', 'min:2', 'max:5'],
        ]);

        $count = $validated['count'] ?? 3;
        $variations = $this->variationService->generateVariations($content, $request->user(), $count);

        return response()->json([
            'message' => 'Variations generated successfully.',
            'data' => $variations,
        ], 201);
    }

    public function variations(Request $request, BrandContentDraft $content): JsonResponse
    {
        abort_unless($content->user_id === $request->user()->id, 403, 'Unauthorized content access.');

        return response()->json([
            'data' => $content->variations()->latest()->get(),
        ]);
    }

    public function selectVariation(Request $request, BrandContentDraft $content, ContentVariation $variation): JsonResponse
    {
        abort_unless($content->user_id === $request->user()->id && $variation->draft_id === $content->id, 403, 'Unauthorized variation selection.');

        $updatedDraft = $this->variationService->selectVariation($content, $variation, $request->user());

        return response()->json([
            'message' => "Variation '{$variation->style}' selected successfully.",
            'data' => $updatedDraft,
        ]);
    }

    public function improve(Request $request, BrandContentDraft $content): JsonResponse
    {
        abort_unless($content->user_id === $request->user()->id, 403, 'Unauthorized content access.');

        $validated = $request->validate([
            'operation' => ['required', 'string', 'in:rewrite,shorten,expand,improve_hook,improve_caption,change_tone,improve_clarity,correct_grammar,better_cta,better_hashtags,adapt_platform,custom'],
            'target_platform' => ['nullable', 'string', 'in:linkedin,instagram,tiktok,youtube,facebook'],
            'target_tone' => ['nullable', 'string', 'max:100'],
            'custom_instructions' => ['nullable', 'string', 'max:1000'],
        ]);

        $improvedDraft = $this->improvementService->improve(
            draft: $content,
            user: $request->user(),
            operation: $validated['operation'],
            targetPlatform: $validated['target_platform'] ?? null,
            targetTone: $validated['target_tone'] ?? null,
            customInstructions: $validated['custom_instructions'] ?? null,
        );

        return response()->json([
            'message' => "Content improvement '{$validated['operation']}' applied.",
            'data' => $improvedDraft,
        ]);
    }

    public function revisions(Request $request, BrandContentDraft $content): JsonResponse
    {
        abort_unless($content->user_id === $request->user()->id, 403, 'Unauthorized content access.');

        return response()->json([
            'data' => $content->revisions()->orderBy('revision_number', 'desc')->get(),
        ]);
    }
}
