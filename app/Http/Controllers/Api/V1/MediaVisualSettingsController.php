<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Services\MediaVisualSettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MediaVisualSettingsController extends Controller
{
    public function __construct(
        protected MediaVisualSettingsService $visualSettingsService,
    ) {}

    public function show(Request $request, Brand $brand): JsonResponse
    {
        abort_unless($brand->user_id === $request->user()->id, 403, 'Unauthorized brand access.');

        $settings = $this->visualSettingsService->getSettings($brand);

        return response()->json([
            'data'     => $settings,
            'settings' => $settings,
        ]);
    }

    public function update(Request $request, Brand $brand): JsonResponse
    {
        abort_unless($brand->user_id === $request->user()->id, 403, 'Unauthorized brand access.');

        $validated = $request->validate([
            'visual_identity'                  => ['nullable', 'string', 'max:50'],
            'primary_color'                    => ['nullable', 'string', 'max:20'],
            'secondary_color'                  => ['nullable', 'string', 'max:20'],
            'accent_color'                     => ['nullable', 'string', 'max:20'],
            'background_color'                 => ['nullable', 'string', 'max:20'],
            'text_color'                       => ['nullable', 'string', 'max:20'],
            'primary_font'                     => ['nullable', 'string', 'max:100'],
            'secondary_font'                   => ['nullable', 'string', 'max:100'],
            'font_size_base'                   => ['nullable', 'string', 'max:20'],
            'logo_placement'                   => ['nullable', 'string', 'max:50'],
            'preferred_platforms'              => ['nullable', 'array'],
            'preferred_aspect_ratios'          => ['nullable', 'array'],
            'image_generation_style_prompt'    => ['nullable', 'string'],
            'image_generation_negative_prompt' => ['nullable', 'string'],
            'preferred_image_provider'         => ['nullable', 'string', 'max:50'],
            'preferred_video_provider'         => ['nullable', 'string', 'max:50'],
            'design_preferences'              => ['nullable', 'array'],
        ]);

        $settings = $this->visualSettingsService->updateSettings($brand, $validated);

        return response()->json([
            'message'  => 'Visual settings updated successfully.',
            'data'     => $settings,
            'settings' => $settings,
        ]);
    }
}
