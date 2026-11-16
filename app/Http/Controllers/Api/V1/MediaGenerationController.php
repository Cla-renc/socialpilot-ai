<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessMediaGenerationJob;
use App\Models\Brand;
use App\Models\MediaAsset;
use App\Models\MediaGenerationJob;
use App\Services\MediaGenerationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MediaGenerationController extends Controller
{
    public function __construct(
        protected MediaGenerationService $generationService,
    ) {}

    public function generateImage(Request $request, Brand $brand): JsonResponse
    {
        abort_unless($brand->user_id === $request->user()->id, 403, 'Unauthorized brand access.');

        $validated = $request->validate([
            'objective' => [
                'required',
                'string',
                'in:social_post,promotional_poster,quote_card,educational_graphic,product_ad,brand_awareness,event_announcement,campaign_creative,thumbnail,background,custom',
            ],
            'visual_style'     => ['nullable', 'string', 'max:100'],
            'platform'         => ['nullable', 'string', 'in:instagram,facebook,tiktok,youtube,linkedin,x,twitter,pinterest'],
            'aspect_ratio'     => ['nullable', 'string', 'in:1:1,16:9,9:16,4:5,1.91:1'],
            'instructions'     => ['nullable', 'string', 'max:500'],
            'content_draft_id' => ['nullable', 'integer', 'exists:brand_content_drafts,id'],
            'provider'         => ['nullable', 'string', 'max:50'],
            'title'            => ['nullable', 'string', 'max:255'],
        ]);

        $job = $this->generationService->createImageGenerationJob(
            $request->user(),
            $brand,
            $validated
        );

        $asset = $job->mediaAsset;

        return response()->json([
            'message' => 'Image generation queued successfully.',
            'data'    => [
                'job'   => $job,
                'asset' => $asset,
            ],
            'job'     => $job,
            'asset'   => $asset,
        ], 202);
    }

    public function imageStatus(Request $request, int $id): JsonResponse
    {
        $asset = MediaAsset::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $job = MediaGenerationJob::where('media_asset_id', $asset->id)
            ->latest()
            ->first();

        return response()->json([
            'data' => [
                'asset'      => $asset,
                'job'        => $job,
                'status'     => $asset->generation_status,
                'public_url' => $asset->public_url,
            ],
            'asset'      => $asset,
            'job'        => $job,
            'status'     => $asset->generation_status,
            'public_url' => $asset->public_url,
        ]);
    }

    public function generateVideo(Request $request, Brand $brand): JsonResponse
    {
        abort_unless($brand->user_id === $request->user()->id, 403, 'Unauthorized brand access.');

        $validated = $request->validate([
            'objective'        => ['nullable', 'string', 'max:100'],
            'visual_style'     => ['nullable', 'string', 'max:100'],
            'platform'         => ['nullable', 'string', 'in:instagram,facebook,tiktok,youtube,linkedin,x,twitter,pinterest'],
            'aspect_ratio'     => ['nullable', 'string', 'in:1:1,16:9,9:16,4:5,1.91:1'],
            'instructions'     => ['nullable', 'string', 'max:1000'],
            'duration'         => ['nullable', 'integer', 'min:1', 'max:300'],
            'content_draft_id' => ['nullable', 'integer', 'exists:brand_content_drafts,id'],
            'provider'         => ['nullable', 'string', 'max:50'],
            'title'            => ['nullable', 'string', 'max:255'],
        ]);

        $job = $this->generationService->createVideoGenerationJob(
            $request->user(),
            $brand,
            $validated
        );

        $asset = $job->mediaAsset;

        return response()->json([
            'message' => 'Video generation queued successfully.',
            'data'    => [
                'job'   => $job,
                'asset' => $asset,
            ],
            'job'     => $job,
            'asset'   => $asset,
        ], 202);
    }

    public function videoStatus(Request $request, int $id): JsonResponse
    {
        $asset = MediaAsset::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $job = MediaGenerationJob::where('media_asset_id', $asset->id)
            ->latest()
            ->first();

        return response()->json([
            'data' => [
                'asset'      => $asset,
                'job'        => $job,
                'status'     => $asset->generation_status,
                'public_url' => $asset->public_url,
            ],
            'asset'      => $asset,
            'job'        => $job,
            'status'     => $asset->generation_status,
            'public_url' => $asset->public_url,
        ]);
    }

    public function retryJob(Request $request, MediaGenerationJob $job): JsonResponse
    {
        abort_unless($job->user_id === $request->user()->id, 403, 'Unauthorized job access.');
        abort_unless($job->status === 'failed', 422, 'Only failed jobs can be retried.');
        abort_if($job->retry_count >= $job->max_retries, 422, 'Max retry limit reached.');

        $job->increment('retry_count');
        $job->update([
            'status'        => 'queued',
            'progress'      => 0,
            'error_message' => null,
            'queued_at'     => now(),
        ]);

        if ($job->media_asset_id) {
            MediaAsset::where('id', $job->media_asset_id)->update([
                'generation_status' => 'queued',
                'generation_error'  => null,
            ]);
        }

        ProcessMediaGenerationJob::dispatch($job)->afterCommit();

        return response()->json([
            'message' => 'Job retried successfully.',
            'data'    => [
                'job' => $job->fresh(),
            ],
            'job'     => $job->fresh(),
        ], 202);
    }

    public function composeGraphic(Request $request, Brand $brand): JsonResponse
    {
        abort_unless($brand->user_id === $request->user()->id, 403, 'Unauthorized brand access.');

        $validated = $request->validate([
            'template'             => ['nullable', 'string', 'in:quote_card,motivational_poster,product_ad,educational,announcement,poster,graphic'],
            'headline'             => ['nullable', 'string', 'max:255'],
            'quote'                => ['nullable', 'string', 'max:500'],
            'author'               => ['nullable', 'string', 'max:150'],
            'caption'              => ['nullable', 'string', 'max:255'],
            'call_to_action'       => ['nullable', 'string', 'max:100'],
            'bullets'              => ['nullable', 'array'],
            'aspect_ratio'         => ['nullable', 'string', 'in:1:1,16:9,9:16,4:5,1.91:1'],
            'platform'             => ['nullable', 'string', 'max:50'],
            'title'                => ['nullable', 'string', 'max:255'],
            'content_draft_id'     => ['nullable', 'integer', 'exists:brand_content_drafts,id'],
            'primary_color'        => ['nullable', 'string', 'max:20'],
            'secondary_color'      => ['nullable', 'string', 'max:20'],
            'accent_color'         => ['nullable', 'string', 'max:20'],
            'background_color'     => ['nullable', 'string', 'max:20'],
            'text_color'           => ['nullable', 'string', 'max:20'],
            'primary_font'         => ['nullable', 'string', 'max:100'],
            'secondary_font'       => ['nullable', 'string', 'max:100'],
            'background_image_url' => ['nullable', 'string', 'max:500'],
        ]);

        $asset = $this->generationService->composeGraphic(
            $request->user(),
            $brand,
            $validated
        );

        return response()->json([
            'message' => 'Branded graphic composed successfully.',
            'data'    => $asset,
            'asset'   => $asset,
        ], 201);
    }
}
