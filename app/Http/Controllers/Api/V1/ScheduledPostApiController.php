<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\ScheduledPost;
use App\Services\ContentSchedulingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ScheduledPostApiController extends Controller
{
    public function __construct(
        protected ContentSchedulingService $schedulingService
    ) {}

    /**
     * List scheduled posts for a brand.
     */
    public function index(Request $request, Brand $brand): JsonResponse
    {
        $this->authorizeBrandOwner($request, $brand);

        $query = ScheduledPost::query()
            ->where('brand_id', $brand->id)
            ->with(['platforms.socialAccount', 'mediaAssets', 'contentDraft']);

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        if ($request->filled('platform')) {
            $query->whereHas('platforms', fn ($q) => $q->where('platform', strtolower($request->query('platform'))));
        }

        if ($request->filled('search')) {
            $search = '%' . $request->query('search') . '%';
            $query->where(fn ($q) => $q->where('title', 'like', $search)->orWhere('caption', 'like', $search));
        }

        $perPage = min(50, max(5, (int) $request->query('per_page', 15)));
        $posts = $query->orderBy('scheduled_at', 'asc')->paginate($perPage);

        return response()->json([
            'data'  => $posts->items(),
            'meta'  => [
                'current_page' => $posts->currentPage(),
                'last_page'    => $posts->lastPage(),
                'per_page'     => $posts->perPage(),
                'total'        => $posts->total(),
            ],
        ]);
    }

    /**
     * Create a scheduled post.
     */
    public function store(Request $request, Brand $brand): JsonResponse
    {
        $this->authorizeBrandOwner($request, $brand);

        $validated = $request->validate([
            'content_draft_id'          => ['nullable', 'integer', 'exists:brand_content_drafts,id'],
            'title'                     => ['nullable', 'string', 'max:255'],
            'caption'                   => ['nullable', 'string'],
            'body'                      => ['nullable', 'string'],
            'hashtags'                  => ['nullable', 'array'],
            'scheduled_at'              => ['required', 'string'],
            'timezone'                  => ['nullable', 'string', 'max:64'],
            'requires_approval'         => ['nullable', 'boolean'],
            'is_approved'               => ['nullable', 'boolean'],
            'platform'                  => ['nullable', 'string', 'max:32'],
            'social_account_id'         => ['nullable', 'integer', 'exists:social_accounts,id'],
            'platforms'                 => ['nullable', 'array'],
            'platforms.*.platform'      => ['required_with:platforms', 'string', 'max:32'],
            'platforms.*.social_account_id' => ['nullable', 'integer', 'exists:social_accounts,id'],
            'platforms.*.custom_title'  => ['nullable', 'string', 'max:255'],
            'platforms.*.custom_caption'=> ['nullable', 'string'],
            'platforms.*.custom_body'   => ['nullable', 'string'],
            'platforms.*.custom_hashtags'=> ['nullable', 'array'],
            'media_asset_ids'           => ['nullable', 'array'],
            'media_asset_ids.*'         => ['integer', 'exists:media_assets,id'],
            'allow_past'                => ['nullable', 'boolean'],
            'force'                     => ['nullable', 'boolean'],
        ]);

        $post = $this->schedulingService->schedulePost($request->user(), $brand, $validated);

        return response()->json([
            'message' => 'Post successfully scheduled.',
            'data'    => $post,
        ], 201);
    }

    /**
     * Show scheduled post detail.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $post = ScheduledPost::with(['brand', 'platforms.socialAccount', 'mediaAssets', 'contentDraft'])->findOrFail($id);
        $this->authorizeBrandOwner($request, $post->brand);

        return response()->json(['data' => $post]);
    }

    /**
     * Update scheduled post.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $post = ScheduledPost::with('brand')->findOrFail($id);
        $this->authorizeBrandOwner($request, $post->brand);

        $validated = $request->validate([
            'title'             => ['nullable', 'string', 'max:255'],
            'caption'           => ['nullable', 'string'],
            'body'              => ['nullable', 'string'],
            'hashtags'          => ['nullable', 'array'],
            'requires_approval' => ['nullable', 'boolean'],
            'media_asset_ids'   => ['nullable', 'array'],
        ]);

        $post->update(array_filter([
            'title'             => $validated['title'] ?? null,
            'caption'           => $validated['caption'] ?? null,
            'body'              => $validated['body'] ?? null,
            'hashtags'          => $validated['hashtags'] ?? null,
            'requires_approval' => $validated['requires_approval'] ?? null,
        ], fn ($v) => !is_null($v)));

        if (isset($validated['media_asset_ids'])) {
            $post->mediaAssets()->sync($validated['media_asset_ids']);
        }

        return response()->json([
            'message' => 'Scheduled post updated successfully.',
            'data'    => $post->fresh(['platforms.socialAccount', 'mediaAssets', 'contentDraft']),
        ]);
    }

    /**
     * Soft delete scheduled post.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $post = ScheduledPost::with('brand')->findOrFail($id);
        $this->authorizeBrandOwner($request, $post->brand);

        $post->delete();

        return response()->json(['message' => 'Scheduled post removed.']);
    }

    /**
     * Reschedule post.
     */
    public function reschedule(Request $request, int $id): JsonResponse
    {
        $post = ScheduledPost::with('brand')->findOrFail($id);
        $this->authorizeBrandOwner($request, $post->brand);

        $validated = $request->validate([
            'scheduled_at' => ['required', 'string'],
            'timezone'     => ['nullable', 'string', 'max:64'],
        ]);

        $updated = $this->schedulingService->reschedulePost(
            $post,
            $validated['scheduled_at'],
            $validated['timezone'] ?? null
        );

        return response()->json([
            'message' => 'Post rescheduled successfully.',
            'data'    => $updated,
        ]);
    }

    /**
     * Cancel scheduled post.
     */
    public function cancel(Request $request, int $id): JsonResponse
    {
        $post = ScheduledPost::with('brand')->findOrFail($id);
        $this->authorizeBrandOwner($request, $post->brand);

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $cancelled = $this->schedulingService->cancelScheduledPost($post, $validated['reason'] ?? null);

        return response()->json([
            'message' => 'Post scheduling cancelled.',
            'data'    => $cancelled,
        ]);
    }

    /**
     * Approve scheduled post.
     */
    public function approve(Request $request, int $id): JsonResponse
    {
        $post = ScheduledPost::with('brand')->findOrFail($id);
        $this->authorizeBrandOwner($request, $post->brand);

        $approved = $this->schedulingService->approveScheduledPost($post, $request->user());

        return response()->json([
            'message' => 'Post approved for scheduling.',
            'data'    => $approved,
        ]);
    }

    protected function authorizeBrandOwner(Request $request, Brand $brand): void
    {
        if ($brand->user_id !== $request->user()->id) {
            abort(403, 'Unauthorized access to brand resources.');
        }
    }
}
