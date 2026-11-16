<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PostAnalytic extends Model
{
    use HasFactory;

    protected $fillable = [
        'brand_id',
        'social_account_id',
        'publishing_attempt_id',
        'scheduled_post_id',
        'platform',
        'external_post_id',
        'metric_name',
        'metric_value',
        'metric_unit',
        'reporting_period',
        'collected_at',
        'metadata',
    ];

    protected $casts = [
        'metric_value' => 'float',
        'collected_at' => 'datetime',
        'metadata'     => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (PostAnalytic $postAnalytic) {
            if (!$postAnalytic->brand_id && $postAnalytic->social_account_id) {
                $postAnalytic->brand_id = $postAnalytic->socialAccount?->brand_id ?? SocialAccount::find($postAnalytic->social_account_id)?->brand_id;
            }
        });
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function socialAccount(): BelongsTo
    {
        return $this->belongsTo(SocialAccount::class);
    }

    public function publishingAttempt(): BelongsTo
    {
        return $this->belongsTo(PublishingAttempt::class);
    }

    public function scheduledPost(): BelongsTo
    {
        return $this->belongsTo(ScheduledPost::class);
    }

    public function scopeForPlatform(Builder $query, string $platform): Builder
    {
        return $query->where('platform', strtolower($platform));
    }

    public function scopeForPost(Builder $query, string $externalPostId): Builder
    {
        return $query->where('external_post_id', $externalPostId);
    }
}
