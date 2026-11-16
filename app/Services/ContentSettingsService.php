<?php

namespace App\Services;

use App\Models\Brand;
use App\Models\ContentGenerationSetting;

class ContentSettingsService
{
    public function getSettingsForBrand(Brand $brand): ContentGenerationSetting
    {
        return ContentGenerationSetting::firstOrCreate(
            ['brand_id' => $brand->id],
            [
                'default_objective' => $brand->settings?->marketing_objectives[0] ?? 'engagement',
                'default_category' => $brand->settings?->content_formats[0] ?? 'educational',
                'default_platform' => 'linkedin',
                'default_format' => 'post',
                'default_tone' => $brand->tone ?? 'confident and practical',
                'preferred_hashtags' => $brand->settings?->hashtag_preferences ?? [],
                'avoid_hashtags' => [],
                'writing_guidelines' => $brand->settings?->writing_style ?? 'Clear, direct, and actionable.',
            ]
        );
    }

    public function updateSettingsForBrand(Brand $brand, array $data): ContentGenerationSetting
    {
        $setting = $this->getSettingsForBrand($brand);
        $setting->update($data);

        return $setting->fresh();
    }
}
