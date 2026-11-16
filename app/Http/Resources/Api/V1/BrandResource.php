<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BrandResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $brand = $this->resource;

        return [
            'id' => $brand->id,
            'user_id' => $brand->user_id,
            'name' => $brand->name,
            'description' => $brand->description,
            'category' => $brand->category,
            'industry' => $brand->industry ?? $brand->category,
            'niche' => $brand->niche,
            'website_url' => $brand->website_url,
            'audience' => $brand->audience,
            'target_age_group' => $brand->target_age_group,
            'audience_interests' => $brand->audience_interests,
            'region' => $brand->region,
            'language' => $brand->language,
            'tone' => $brand->tone,
            'status' => $brand->status,
            'logo_url' => $brand->logo_url,
            'connected_accounts_count' => $brand->connected_accounts_count ?? 0,
            'settings' => $brand->settings ? [
                'brand_voice' => $brand->settings->brand_voice,
                'writing_style' => $brand->settings->writing_style,
                'content_formats' => $brand->settings->content_formats,
                'brand_colours' => $brand->settings->brand_colours,
                'publishing_frequency' => $brand->settings->publishing_frequency,
                'timezone' => $brand->settings->timezone,
                'brand_personality' => $brand->settings->brand_personality,
                'brand_values' => $brand->settings->brand_values,
                'brand_keywords' => $brand->settings->brand_keywords,
                'topics_to_avoid' => $brand->settings->topics_to_avoid,
                'content_restrictions' => $brand->settings->content_restrictions,
                'hashtag_preferences' => $brand->settings->hashtag_preferences,
                'preferred_publishing_times' => $brand->settings->preferred_publishing_times,
                'marketing_objectives' => $brand->settings->marketing_objectives,
                'content_preferences' => $brand->settings->content_preferences,
            ] : null,
            'created_at' => $brand->created_at?->toISOString(),
            'updated_at' => $brand->updated_at?->toISOString(),
        ];
    }
}
