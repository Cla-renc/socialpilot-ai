<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PublishingNotification extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'brand_id',
        'publishing_attempt_id',
        'scheduled_post_id',
        'notification_type',
        'platform',
        'title',
        'message',
        'metadata',
        'read_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'read_at'  => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function publishingAttempt(): BelongsTo
    {
        return $this->belongsTo(PublishingAttempt::class, 'publishing_attempt_id');
    }

    public function scheduledPost(): BelongsTo
    {
        return $this->belongsTo(ScheduledPost::class, 'scheduled_post_id');
    }

    public function isRead(): bool
    {
        return $this->read_at !== null;
    }

    public function markAsRead(): bool
    {
        return $this->update(['read_at' => now()]);
    }
}
