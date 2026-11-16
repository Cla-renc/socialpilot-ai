<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\MediaStoryboard;
use App\Models\MediaStoryboardScene;
use App\Services\VideoStoryboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MediaStoryboardController extends Controller
{
    public function __construct(
        protected VideoStoryboardService $storyboardService,
    ) {}

    public function generate(Request $request, Brand $brand): JsonResponse
    {
        abort_unless($brand->user_id === $request->user()->id, 403, 'Unauthorized brand access.');

        $validated = $request->validate([
            'title'            => ['nullable', 'string', 'max:255'],
            'concept'          => ['nullable', 'string', 'max:1000'],
            'platform'         => ['nullable', 'string', 'in:instagram,facebook,tiktok,youtube,linkedin'],
            'aspect_ratio'     => ['nullable', 'string', 'in:1:1,16:9,9:16,4:5,1.91:1'],
            'scene_count'      => ['nullable', 'integer', 'min:1', 'max:20'],
            'instructions'     => ['nullable', 'string', 'max:1000'],
            'content_draft_id' => ['nullable', 'integer', 'exists:brand_content_drafts,id'],
        ]);

        $storyboard = $this->storyboardService->generateStoryboard(
            $request->user(),
            $brand,
            $validated
        );

        return response()->json([
            'message'    => 'Storyboard generated successfully.',
            'data'       => $storyboard,
            'storyboard' => $storyboard,
        ], 201);
    }

    public function show(Request $request, MediaStoryboard $storyboard): JsonResponse
    {
        abort_unless($storyboard->user_id === $request->user()->id, 403, 'Unauthorized storyboard access.');

        $storyboard->load('scenes');

        return response()->json([
            'data'       => $storyboard,
            'storyboard' => $storyboard,
        ]);
    }

    public function update(Request $request, MediaStoryboard $storyboard): JsonResponse
    {
        abort_unless($storyboard->user_id === $request->user()->id, 403, 'Unauthorized storyboard access.');

        $validated = $request->validate([
            'title'              => ['nullable', 'string', 'max:255'],
            'concept'            => ['nullable', 'string', 'max:1000'],
            'platform'           => ['nullable', 'string', 'in:instagram,facebook,tiktok,youtube,linkedin'],
            'aspect_ratio'       => ['nullable', 'string', 'in:1:1,16:9,9:16,4:5,1.91:1'],
            'estimated_duration' => ['nullable', 'integer', 'min:1', 'max:3600'],
            'status'             => ['nullable', 'string', 'in:draft,approved,exported'],
            'metadata'           => ['nullable', 'array'],
        ]);

        $updated = $this->storyboardService->updateStoryboard($storyboard, $validated);

        return response()->json([
            'message'    => 'Storyboard updated successfully.',
            'data'       => $updated,
            'storyboard' => $updated,
        ]);
    }

    public function destroy(Request $request, MediaStoryboard $storyboard): JsonResponse
    {
        abort_unless($storyboard->user_id === $request->user()->id, 403, 'Unauthorized storyboard access.');

        $storyboard->delete();

        return response()->json([
            'message' => 'Storyboard deleted successfully.',
        ]);
    }

    public function updateScene(Request $request, MediaStoryboardScene $scene): JsonResponse
    {
        abort_unless($scene->storyboard->user_id === $request->user()->id, 403, 'Unauthorized scene access.');

        $validated = $request->validate([
            'description'           => ['nullable', 'string', 'max:1000'],
            'visual_suggestion'     => ['nullable', 'string', 'max:1000'],
            'narration_text'        => ['nullable', 'string', 'max:1000'],
            'on_screen_text'        => ['nullable', 'string', 'max:500'],
            'duration_seconds'      => ['nullable', 'integer', 'min:1', 'max:300'],
            'transition'            => ['nullable', 'string', 'max:100'],
            'background_music_note' => ['nullable', 'string', 'max:500'],
            'image_asset_id'        => ['nullable', 'integer', 'exists:media_assets,id'],
            'metadata'              => ['nullable', 'array'],
        ]);

        $updated = $this->storyboardService->updateScene($scene, $validated);

        return response()->json([
            'message' => 'Storyboard scene updated successfully.',
            'data'    => $updated,
            'scene'   => $updated,
        ]);
    }
}
