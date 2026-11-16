<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MediaVisualSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'brand_id',
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

    protected $casts = [
        'preferred_platforms'    => 'array',
        'preferred_aspect_ratios' => 'array',
        'design_preferences'     => 'array',
    ];

    // -------------------------------------------------------------------------
    // Relations
    // -------------------------------------------------------------------------

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }
}
