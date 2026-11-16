<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MediaStoryboard extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'brand_id',
        'media_asset_id',
        'content_draft_id',
        'title',
        'concept',
        'platform',
        'aspect_ratio',
        'estimated_duration',
        'status',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    // -------------------------------------------------------------------------
    // Relations
    // -------------------------------------------------------------------------

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function contentDraft(): BelongsTo
    {
        return $this->belongsTo(BrandContentDraft::class, 'content_draft_id');
    }

    public function scenes(): HasMany
    {
        return $this->hasMany(MediaStoryboardScene::class, 'storyboard_id')->orderBy('scene_number');
    }

    public function mediaAsset(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'media_asset_id');
    }
}
