<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BrandSetting extends Model
{
    protected $fillable = [
        'brand_id',
        'brand_voice',
        'writing_style',
        'content_formats',
        'preferred_publishing_times',
        'brand_colours',
        'content_restrictions',
        'hashtag_preferences',
        'publishing_frequency',
        'timezone',
        'brand_personality',
        'brand_values',
        'brand_keywords',
        'topics_to_avoid',
        'marketing_objectives',
        'content_preferences',
    ];

    protected $casts = [
        'content_formats' => 'array',
        'preferred_publishing_times' => 'array',
        'brand_colours' => 'array',
        'content_restrictions' => 'array',
        'hashtag_preferences' => 'array',
        'brand_personality' => 'array',
        'brand_values' => 'array',
        'brand_keywords' => 'array',
        'topics_to_avoid' => 'array',
        'marketing_objectives' => 'array',
        'content_preferences' => 'array',
    ];

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }
}
