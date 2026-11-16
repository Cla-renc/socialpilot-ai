<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PublishingAttempt extends Model
{
    use HasFactory;

    protected $fillable = [
        'scheduled_post_id',
        'scheduled_post_platform_id',
        'social_account_id',
        'brand_id',
        'user_id',
        'platform',
        'status',
        'attempt_number',
        'idempotency_key',
        'external_post_id',
        'external_post_url',
        'provider_status',
        'error_code',
        'error_message',
        'request_metadata',
        'response_metadata',
        'is_retryable',
        'next_retry_at',
        'started_at',
        'completed_at',
        'published_at',
    ];

    protected $casts = [
        'request_metadata'  => 'array',
        'response_metadata' => 'array',
        'is_retryable'      => 'boolean',
        'next_retry_at'     => 'datetime',
        'started_at'        => 'datetime',
        'completed_at'      => 'datetime',
        'published_at'      => 'datetime',
    ];

    public function scheduledPost(): BelongsTo
    {
        return $this->belongsTo(ScheduledPost::class, 'scheduled_post_id');
    }

    public function scheduledPostPlatform(): BelongsTo
    {
        return $this->belongsTo(ScheduledPostPlatform::class, 'scheduled_post_platform_id');
    }

    public function socialAccount(): BelongsTo
    {
        return $this->belongsTo(SocialAccount::class, 'social_account_id');
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class, 'brand_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(PublishingNotification::class, 'publishing_attempt_id');
    }

    public function postAnalytics(): HasMany
    {
        return $this->hasMany(PostAnalytic::class, 'publishing_attempt_id');
    }

    public function isSuccess(): bool
    {
        return $this->status === 'published';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function isProcessing(): bool
    {
        return $this->status === 'processing';
    }
}
