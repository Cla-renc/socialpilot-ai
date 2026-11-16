<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\BrandContentDraft;
use App\Models\PublishingQueue;
use App\Models\PublishingQueueItem;
use App\Models\ScheduledPost;
use App\Services\PublishingQueueService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublishingQueueApiController extends Controller
{
    public function __construct(
        protected PublishingQueueService $queueService
    ) {}

    /**
     * Get or show brand publishing queue.
     */
    public function index(Request $request, Brand $brand): JsonResponse
    {
        $this->authorizeBrandOwner($request, $brand);

        $queue = $this->queueService->getOrCreateDefaultQueue($brand);
        $queue->load(['items.contentDraft', 'items.scheduledPost']);

        return response()->json([
            'data' => $queue,
        ]);
    }

    /**
     * Update or create queue configuration.
     */
    public function store(Request $request, Brand $brand): JsonResponse
    {
        $this->authorizeBrandOwner($request, $brand);

        $validated = $request->validate([
            'name'                     => ['nullable', 'string', 'max:100'],
            'status'                   => ['nullable', 'string', 'in:active,paused'],
            'posting_interval_minutes' => ['nullable', 'integer', 'min:15'],
            'preferred_days'           => ['nullable', 'array'],
            'preferred_hours'          => ['nullable', 'array'],
            'timezone'                 => ['nullable', 'string', 'max:64'],
        ]);

        $queue = $this->queueService->getOrCreateDefaultQueue($brand);
        $queue->update(array_filter($validated, fn ($v) => !is_null($v)));

        return response()->json([
            'message' => 'Publishing queue updated.',
            'data'    => $queue->fresh(['items']),
        ]);
    }

    /**
     * Update specific queue by ID.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $queue = PublishingQueue::with('brand')->findOrFail($id);
        $this->authorizeBrandOwner($request, $queue->brand);

        $validated = $request->validate([
            'name'                     => ['nullable', 'string', 'max:100'],
            'status'                   => ['nullable', 'string', 'in:active,paused'],
            'posting_interval_minutes' => ['nullable', 'integer', 'min:15'],
            'preferred_days'           => ['nullable', 'array'],
            'preferred_hours'          => ['nullable', 'array'],
            'timezone'                 => ['nullable', 'string', 'max:64'],
        ]);

        $queue->update(array_filter($validated, fn ($v) => !is_null($v)));

        return response()->json([
            'message' => 'Publishing queue updated.',
            'data'    => $queue->fresh(['items']),
        ]);
    }

    /**
     * Pause queue.
     */
    public function pause(Request $request, int $id): JsonResponse
    {
        $queue = PublishingQueue::with('brand')->findOrFail($id);
        $this->authorizeBrandOwner($request, $queue->brand);

        $updated = $this->queueService->pauseQueue($queue);

        return response()->json([
            'message' => 'Publishing queue paused.',
            'data'    => $updated,
        ]);
    }

    /**
     * Resume queue.
     */
    public function resume(Request $request, int $id): JsonResponse
    {
        $queue = PublishingQueue::with('brand')->findOrFail($id);
        $this->authorizeBrandOwner($request, $queue->brand);

        $updated = $this->queueService->resumeQueue($queue);

        return response()->json([
            'message' => 'Publishing queue resumed.',
            'data'    => $updated,
        ]);
    }

    /**
     * Reorder queue items.
     */
    public function reorder(Request $request, int $id): JsonResponse
    {
        $queue = PublishingQueue::with('brand')->findOrFail($id);
        $this->authorizeBrandOwner($request, $queue->brand);

        $validated = $request->validate([
            'ordered_item_ids'   => ['required', 'array'],
            'ordered_item_ids.*' => ['integer'],
        ]);

        $updated = $this->queueService->reorderQueue($queue, $validated['ordered_item_ids']);

        return response()->json([
            'message' => 'Queue items reordered successfully.',
            'data'    => $updated,
        ]);
    }

    /**
     * Add item to queue.
     */
    public function addItem(Request $request, int $id): JsonResponse
    {
        $queue = PublishingQueue::with('brand')->findOrFail($id);
        $this->authorizeBrandOwner($request, $queue->brand);

        $validated = $request->validate([
            'content_draft_id'  => ['nullable', 'integer', 'exists:brand_content_drafts,id'],
            'scheduled_post_id' => ['nullable', 'integer', 'exists:scheduled_posts,id'],
            'priority'          => ['nullable', 'string', 'in:low,normal,high,urgent'],
            'target_platform'   => ['nullable', 'string', 'max:32'],
            'notes'             => ['nullable', 'string'],
        ]);

        if (empty($validated['content_draft_id']) && empty($validated['scheduled_post_id'])) {
            return response()->json(['message' => 'Either content_draft_id or scheduled_post_id is required.'], 422);
        }

        if (!empty($validated['content_draft_id'])) {
            $draft = BrandContentDraft::findOrFail($validated['content_draft_id']);
            $item = $this->queueService->addDraftToQueue($queue, $draft, $validated);
        } else {
            $post = ScheduledPost::findOrFail($validated['scheduled_post_id']);
            $item = $this->queueService->addPostToQueue($queue, $post, $validated);
        }

        return response()->json([
            'message' => 'Item added to publishing queue.',
            'data'    => $item->load(['contentDraft', 'scheduledPost']),
        ], 201);
    }

    /**
     * Remove item from queue.
     */
    public function removeItem(Request $request, int $itemId): JsonResponse
    {
        $item = PublishingQueueItem::with('queue.brand')->findOrFail($itemId);
        $this->authorizeBrandOwner($request, $item->queue->brand);

        $item->delete();

        return response()->json(['message' => 'Queue item removed.']);
    }

    protected function authorizeBrandOwner(Request $request, Brand $brand): void
    {
        if ($brand->user_id !== $request->user()->id) {
            abort(403, 'Unauthorized access to brand resources.');
        }
    }
}
