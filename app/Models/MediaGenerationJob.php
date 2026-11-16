<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MediaGenerationJob extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'brand_id',
        'media_asset_id',
        'content_draft_id',
        'job_type',
        'provider',
        'provider_model',
        'provider_job_id',
        'status',
        'progress',
        'request_payload',
        'result_payload',
        'error_message',
        'error_code',
        'retry_count',
        'max_retries',
        'queued_at',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'request_payload' => 'array',
        'result_payload'  => 'array',
        'progress'        => 'integer',
        'retry_count'     => 'integer',
        'max_retries'     => 'integer',
        'queued_at'       => 'datetime',
        'started_at'      => 'datetime',
        'completed_at'    => 'datetime',
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

    public function mediaAsset(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'media_asset_id');
    }
}
