<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBrandPostRequest;
use App\Models\Brand;
use App\Models\BrandPost;
use App\Services\PublishingService;
use Illuminate\Http\Request;

class BrandPostController extends Controller
{
    public function __construct(protected PublishingService $publishingService) {}

    public function index(Request $request, Brand $brand)
    {
        abort_unless($brand->user_id === $request->user()->id, 403, 'Unauthorized brand access.');

        $posts = $brand->posts()->latest()->get();

        return response()->json([
            'data' => $posts->map(fn (BrandPost $post) => $this->transformPost($post)),
        ]);
    }

    public function store(StoreBrandPostRequest $request, Brand $brand)
    {
        abort_unless($brand->user_id === $request->user()->id, 403, 'Unauthorized brand access.');

        $post = $this->publishingService->createPost($request->user(), $brand, $request->validated());

        return response()->json([
            'data' => $this->transformPost($post),
        ], 201);
    }

    public function show(Request $request, Brand $brand, BrandPost $post)
    {
        abort_unless($brand->id === $post->brand_id && $brand->user_id === $request->user()->id, 403, 'Unauthorized post access.');

        return response()->json([
            'data' => $this->transformPost($post),
        ]);
    }

    protected function transformPost(BrandPost $post): array
    {
        return [
            'id' => $post->id,
            'brand_id' => $post->brand_id,
            'content_draft_id' => $post->content_draft_id,
            'provider' => $post->provider,
            'platform' => $post->platform,
            'status' => $post->status,
            'scheduled_for' => $post->scheduled_for?->toISOString(),
            'published_at' => $post->published_at?->toISOString(),
            'content' => $post->content,
            'metadata' => $post->metadata,
            'response_payload' => $post->response_payload,
            'created_at' => $post->created_at?->toISOString(),
            'updated_at' => $post->updated_at?->toISOString(),
        ];
    }
}
