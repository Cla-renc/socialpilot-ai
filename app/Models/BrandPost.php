<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BrandPost extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'brand_id',
        'campaign_id',
        'content_draft_id',
        'provider',
        'platform',
        'status',
        'scheduled_for',
        'published_at',
        'content',
        'metadata',
        'response_payload',
        'error_message',
    ];

    protected $casts = [
        'scheduled_for' => 'datetime',
        'published_at' => 'datetime',
        'metadata' => 'array',
        'response_payload' => 'array',
    ];

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
        return $this->belongsTo(BrandContentDraft::class);
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }
}
