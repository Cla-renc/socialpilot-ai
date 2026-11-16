<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScheduledPostPlatform extends Model
{
    use HasFactory;

    protected $fillable = [
        'scheduled_post_id',
        'social_account_id',
        'platform',
        'custom_title',
        'custom_caption',
        'custom_body',
        'custom_hashtags',
        'scheduled_at',
        'status',
        'platform_specific_options',
        'error_message',
    ];

    protected $casts = [
        'custom_hashtags'           => 'array',
        'platform_specific_options' => 'array',
        'scheduled_at'              => 'datetime',
    ];

    public function scheduledPost(): BelongsTo
    {
        return $this->belongsTo(ScheduledPost::class, 'scheduled_post_id');
    }

    public function socialAccount(): BelongsTo
    {
        return $this->belongsTo(SocialAccount::class, 'social_account_id');
    }

    public function publishingAttempts(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(PublishingAttempt::class, 'scheduled_post_platform_id');
    }
}
