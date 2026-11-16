<?php
namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\PublishingAttemptResource;
use App\Jobs\Publishing\RetryFailedPublishingJob;
use App\Jobs\Publishing\PublishScheduledPostJob;
use App\Models\PublishingAttempt;
use App\Models\ScheduledPost;
use App\Models\SocialAccount;
use App\Services\Publishing\PublishingManager;
use App\Services\Publishing\PublishingRetryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublishingController extends Controller
{
    public function __construct(
        private readonly PublishingManager    $manager,
        private readonly PublishingRetryService $retryService
    ) {}

    // POST /api/v1/publishing/publish-now
    public function publishNow(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'social_account_id' => 'required|integer|exists:social_accounts,id',
            'content'           => 'required|string|max:5000',
            'media_urls'        => 'nullable|array',
            'media_urls.*'      => 'string',
            'brand_id'          => 'required|integer|exists:brands,id',
        ]);

        $account = SocialAccount::findOrFail($validated['social_account_id']);

        // Authorization
        $brandUserId = optional($account->brand)->user_id;
        if ($brandUserId !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $attempt = $this->manager->publishWithAccount(
            [
                'text'       => $validated['content'],
                'media_urls' => $validated['media_urls'] ?? [],
                'platform'   => $account->platform,
            ],
            $account,
            []
        );

        return response()->json(['data' => new PublishingAttemptResource($attempt)], 201);
    }

    // GET /api/v1/publishing/attempts
    public function attempts(Request $request): JsonResponse
    {
        $attempts = PublishingAttempt::where('user_id', $request->user()->id)
            ->when($request->brand_id, fn($q) => $q->where('brand_id', $request->brand_id))
            ->when($request->platform, fn($q) => $q->where('platform', $request->platform))
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json(PublishingAttemptResource::collection($attempts)->response()->getData(true));
    }

    // GET /api/v1/publishing/attempts/{attempt}
    public function attempt(PublishingAttempt $attempt, Request $request): JsonResponse
    {
        if ($attempt->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        return response()->json(['data' => new PublishingAttemptResource($attempt)]);
    }

    // POST /api/v1/publishing/attempts/{attempt}/retry
    public function retry(PublishingAttempt $attempt, Request $request): JsonResponse
    {
        if ($attempt->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        if (!$this->retryService->canRetry($attempt)) {
            return response()->json(['message' => 'This attempt cannot be retried.'], 422);
        }
        RetryFailedPublishingJob::dispatch($attempt);
        return response()->json(['message' => 'Retry queued.', 'data' => new PublishingAttemptResource($attempt)]);
    }

    // POST /api/v1/publishing/attempts/{attempt}/cancel
    public function cancel(PublishingAttempt $attempt, Request $request): JsonResponse
    {
        if ($attempt->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        if (!in_array($attempt->status, ['pending', 'publishing'])) {
            return response()->json(['message' => 'Cannot cancel in current state.'], 422);
        }
        $attempt->update(['status' => 'cancelled']);
        return response()->json(['message' => 'Attempt cancelled.', 'data' => new PublishingAttemptResource($attempt->fresh())]);
    }

    // GET /api/v1/publishing/history
    public function history(Request $request): JsonResponse
    {
        $history = PublishingAttempt::where('user_id', $request->user()->id)
            ->where('status', 'published')
            ->when($request->brand_id, fn($q) => $q->where('brand_id', $request->brand_id))
            ->when($request->platform, fn($q) => $q->where('platform', $request->platform))
            ->orderByDesc('published_at')
            ->paginate(20);

        return response()->json(PublishingAttemptResource::collection($history)->response()->getData(true));
    }

    // GET /api/v1/publishing/failed
    public function failed(Request $request): JsonResponse
    {
        $failed = PublishingAttempt::where('user_id', $request->user()->id)
            ->where('status', 'failed')
            ->when($request->brand_id, fn($q) => $q->where('brand_id', $request->brand_id))
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json(PublishingAttemptResource::collection($failed)->response()->getData(true));
    }

    // GET /api/v1/publishing/statistics
    public function statistics(Request $request): JsonResponse
    {
        $userId = $request->user()->id;
        $q      = PublishingAttempt::where('user_id', $userId);
        if ($request->brand_id) $q->where('brand_id', $request->brand_id);

        $stats = [
            'total'               => (clone $q)->count(),
            'published'           => (clone $q)->where('status', 'published')->count(),
            'failed'              => (clone $q)->where('status', 'failed')->count(),
            'processing'          => (clone $q)->whereIn('status', ['processing', 'publishing'])->count(),
            'partially_published' => (clone $q)->where('status', 'partially_published')->count(),
            'cancelled'           => (clone $q)->where('status', 'cancelled')->count(),
            'by_platform'         => (clone $q)->selectRaw('platform, count(*) as cnt')->groupBy('platform')->pluck('cnt', 'platform'),
        ];

        return response()->json(['data' => $stats]);
    }

    // POST /api/v1/publishing/scheduled-posts/{post}/publish
    public function executeScheduled(ScheduledPost $post, Request $request): JsonResponse
    {
        if (optional($post->brand)->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        PublishScheduledPostJob::dispatch($post);
        return response()->json(['message' => 'Scheduled post queued for immediate publishing.']);
    }

    // POST /api/v1/publishing/scheduled-posts/{post}/cancel
    public function cancelScheduled(ScheduledPost $post, Request $request): JsonResponse
    {
        if (optional($post->brand)->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        if (!in_array($post->status, ['scheduled', 'pending', 'approved'])) {
            return response()->json(['message' => 'Post cannot be cancelled in current state.'], 422);
        }
        $post->update(['status' => 'cancelled']);
        return response()->json(['message' => 'Scheduled post cancelled.']);
    }
}
