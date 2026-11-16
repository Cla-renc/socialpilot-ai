<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ScheduledPost extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'brand_id',
        'content_draft_id',
        'title',
        'caption',
        'body',
        'hashtags',
        'scheduled_at',
        'timezone',
        'status',
        'requires_approval',
        'is_approved',
        'approved_by',
        'approved_at',
        'autopilot_managed',
        'autopilot_rule_id',
        'cancellation_reason',
        'failure_reason',
        'metadata',
    ];

    protected $casts = [
        'hashtags'           => 'array',
        'metadata'           => 'array',
        'scheduled_at'       => 'datetime',
        'approved_at'        => 'datetime',
        'requires_approval'  => 'boolean',
        'is_approved'        => 'boolean',
        'autopilot_managed'  => 'boolean',
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
        return $this->belongsTo(BrandContentDraft::class, 'content_draft_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function platforms(): HasMany
    {
        return $this->hasMany(ScheduledPostPlatform::class, 'scheduled_post_id');
    }

    public function mediaPivot(): HasMany
    {
        return $this->hasMany(ScheduledPostMedia::class, 'scheduled_post_id')->orderBy('order');
    }

    public function mediaAssets(): BelongsToMany
    {
        return $this->belongsToMany(MediaAsset::class, 'scheduled_post_media', 'scheduled_post_id', 'media_asset_id')
            ->withPivot(['order', 'platform_variant'])
            ->withTimestamps()
            ->orderByPivot('order');
    }

    public function queueItems(): HasMany
    {
        return $this->hasMany(PublishingQueueItem::class, 'scheduled_post_id');
    }

    public function autopilotRule(): BelongsTo
    {
        return $this->belongsTo(AutopilotRule::class, 'autopilot_rule_id');
    }

    public function publishingAttempts(): HasMany
    {
        return $this->hasMany(PublishingAttempt::class, 'scheduled_post_id');
    }

    public function postAnalytics(): HasMany
    {
        return $this->hasMany(PostAnalytic::class, 'scheduled_post_id');
    }

    public function publishingNotifications(): HasMany
    {
        return $this->hasMany(PublishingNotification::class, 'scheduled_post_id');
    }
}
