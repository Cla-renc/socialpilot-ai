<?php

namespace App\Services;

use App\Models\Brand;
use App\Models\MediaVisualSetting;

class MediaVisualSettingsService
{
    public function getSettings(Brand $brand): MediaVisualSetting
    {
        return MediaVisualSetting::firstOrCreate(
            ['brand_id' => $brand->id],
            [
                'visual_identity'                  => 'minimalist',
                'primary_color'                    => '#1a1a2e',
                'secondary_color'                  => '#16213e',
                'accent_color'                     => '#0f3460',
                'background_color'                 => '#ffffff',
                'text_color'                       => '#111827',
                'primary_font'                     => 'Inter',
                'secondary_font'                   => 'Roboto',
                'font_size_base'                   => '16px',
                'logo_placement'                   => 'top_right',
                'preferred_platforms'              => ['instagram', 'linkedin'],
                'preferred_aspect_ratios'          => ['1:1', '16:9'],
                'image_generation_style_prompt'    => null,
                'image_generation_negative_prompt' => null,
                'preferred_image_provider'         => null,
                'preferred_video_provider'         => null,
                'design_preferences'              => [],
            ]
        );
    }

    public function updateSettings(Brand $brand, array $data): MediaVisualSetting
    {
        $fillable = [
            'visual_identity',
            'primary_color',
            'secondary_color',
            'accent_color',
            'background_color',
            'text_color',
            'primary_font',
            'secondary_font',
            'font_size_base',
            'logo_placement',
            'preferred_platforms',
            'preferred_aspect_ratios',
            'image_generation_style_prompt',
            'image_generation_negative_prompt',
            'preferred_image_provider',
            'preferred_video_provider',
            'design_preferences',
        ];

        $updateData = [];
        foreach ($fillable as $key) {
            if (array_key_exists($key, $data)) {
                $updateData[$key] = $data[$key];
            }
        }

        return MediaVisualSetting::updateOrCreate(
            ['brand_id' => $brand->id],
            $updateData
        );
    }
}
