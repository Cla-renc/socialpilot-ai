<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class MediaAsset extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'brand_id',
        'content_draft_id',
        'parent_asset_id',
        'asset_type',
        'title',
        'file_name',
        'file_format',
        'mime_type',
        'storage_disk',
        'storage_path',
        'public_url',
        'file_size',
        'width',
        'height',
        'aspect_ratio',
        'platform',
        'provider',
        'provider_model',
        'provider_job_id',
        'generation_prompt',
        'generation_params',
        'generation_status',
        'generation_error',
        'objective',
        'visual_style',
        'is_variation',
        'metadata',
    ];

    protected $casts = [
        'generation_params' => 'array',
        'metadata'          => 'array',
        'is_variation'      => 'boolean',
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

    public function variations(): HasMany
    {
        return $this->hasMany(MediaAsset::class, 'parent_asset_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'parent_asset_id');
    }

    public function mediaGenerationJob(): BelongsTo
    {
        return $this->belongsTo(MediaGenerationJob::class);
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    public function scopeForBrand($query, $brandId)
    {
        return $query->where('brand_id', $brandId);
    }

    public function scopeCompleted($query)
    {
        return $query->where('generation_status', 'completed');
    }

    public function scopeByType($query, $type)
    {
        return $query->where('asset_type', $type);
    }
}
