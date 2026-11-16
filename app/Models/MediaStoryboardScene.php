<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MediaStoryboardScene extends Model
{
    use HasFactory;

    protected $fillable = [
        'storyboard_id',
        'scene_number',
        'description',
        'visual_suggestion',
        'narration_text',
        'on_screen_text',
        'duration_seconds',
        'transition',
        'background_music_note',
        'image_asset_id',
        'metadata',
    ];

    protected $casts = [
        'metadata'         => 'array',
        'duration_seconds' => 'integer',
        'scene_number'     => 'integer',
    ];

    // -------------------------------------------------------------------------
    // Relations
    // -------------------------------------------------------------------------

    public function storyboard(): BelongsTo
    {
        return $this->belongsTo(MediaStoryboard::class, 'storyboard_id');
    }

    public function imageAsset(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'image_asset_id');
    }
}
