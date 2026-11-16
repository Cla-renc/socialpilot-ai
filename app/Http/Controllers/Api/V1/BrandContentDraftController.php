<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreContentDraftRequest;
use App\Models\Brand;
use App\Models\BrandContentDraft;
use App\Services\BrandContentDraftService;
use Illuminate\Http\Request;

class BrandContentDraftController extends Controller
{
    public function __construct(protected BrandContentDraftService $draftService) {}

    public function index(Request $request, Brand $brand)
    {
        abort_unless($brand->user_id === $request->user()->id, 403, 'Unauthorized brand access.');

        $drafts = $brand->contentDrafts()->latest()->get();

        return response()->json([
            'data' => $drafts->map(fn (BrandContentDraft $draft) => $this->transformDraft($draft)),
        ]);
    }

    public function store(StoreContentDraftRequest $request, Brand $brand)
    {
        abort_unless($brand->user_id === $request->user()->id, 403, 'Unauthorized brand access.');

        $draft = $this->draftService->generateForBrand($brand, $request->user(), $request->validated());

        return response()->json([
            'data' => $this->transformDraft($draft),
        ], 201);
    }

    public function show(Request $request, Brand $brand, BrandContentDraft $draft)
    {
        abort_unless($brand->id === $draft->brand_id && $brand->user_id === $request->user()->id, 403, 'Unauthorized draft access.');

        return response()->json([
            'data' => $this->transformDraft($draft),
        ]);
    }

    public function update(StoreContentDraftRequest $request, Brand $brand, BrandContentDraft $draft)
    {
        abort_unless($brand->id === $draft->brand_id && $brand->user_id === $request->user()->id, 403, 'Unauthorized draft access.');

        $draft->fill($request->validated());
        $draft->save();

        return response()->json([
            'data' => $this->transformDraft($draft->fresh()),
        ]);
    }

    public function destroy(Request $request, Brand $brand, BrandContentDraft $draft)
    {
        abort_unless($brand->id === $draft->brand_id && $brand->user_id === $request->user()->id, 403, 'Unauthorized draft access.');

        $draft->delete();

        return response()->json([
            'message' => 'Draft deleted successfully.',
        ]);
    }

    protected function transformDraft(BrandContentDraft $draft): array
    {
        return [
            'id' => $draft->id,
            'brand_id' => $draft->brand_id,
            'title' => $draft->title,
            'platform' => $draft->platform,
            'content_type' => $draft->content_type,
            'status' => $draft->status,
            'prompt' => $draft->prompt,
            'body' => $draft->body,
            'hashtags' => $draft->hashtags,
            'metadata' => $draft->metadata,
            'scheduled_for' => $draft->scheduled_for?->toISOString(),
            'ai_provider' => $draft->ai_provider,
            'ai_model' => $draft->ai_model,
            'created_at' => $draft->created_at?->toISOString(),
            'updated_at' => $draft->updated_at?->toISOString(),
        ];
    }
}
