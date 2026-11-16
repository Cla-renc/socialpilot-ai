<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Services\ContentSettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContentSettingsApiController extends Controller
{
    public function __construct(
        protected ContentSettingsService $settingsService,
    ) {}

    public function show(Request $request, Brand $brand): JsonResponse
    {
        abort_unless($brand->user_id === $request->user()->id, 403, 'Unauthorized brand access.');

        $settings = $this->settingsService->getSettingsForBrand($brand);

        return response()->json([
            'data' => $settings,
        ]);
    }

    public function update(Request $request, Brand $brand): JsonResponse
    {
        abort_unless($brand->user_id === $request->user()->id, 403, 'Unauthorized brand access.');

        $validated = $request->validate([
            'default_objective' => ['nullable', 'string', 'in:awareness,engagement,education,community_building,brand_authority,product_promotion,website_traffic,lead_generation'],
            'default_category' => ['nullable', 'string', 'max:100'],
            'default_platform' => ['nullable', 'string', 'in:linkedin,instagram,tiktok,youtube,facebook'],
            'default_format' => ['nullable', 'string', 'in:post,short_script,video_outline,article,thread'],
            'default_tone' => ['nullable', 'string', 'max:255'],
            'preferred_hashtags' => ['nullable', 'array'],
            'preferred_hashtags.*' => ['string', 'max:50'],
            'avoid_hashtags' => ['nullable', 'array'],
            'avoid_hashtags.*' => ['string', 'max:50'],
            'writing_guidelines' => ['nullable', 'string', 'max:2000'],
        ]);

        $settings = $this->settingsService->updateSettingsForBrand($brand, $validated);

        return response()->json([
            'message' => 'Content settings updated successfully.',
            'data' => $settings,
        ]);
    }
}
