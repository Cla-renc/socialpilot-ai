<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PublishingQueueItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'publishing_queue_id',
        'user_id',
        'brand_id',
        'scheduled_post_id',
        'content_draft_id',
        'position',
        'priority',
        'status',
        'target_platform',
        'notes',
    ];

    protected $casts = [
        'position' => 'integer',
    ];

    public function queue(): BelongsTo
    {
        return $this->belongsTo(PublishingQueue::class, 'publishing_queue_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function scheduledPost(): BelongsTo
    {
        return $this->belongsTo(ScheduledPost::class, 'scheduled_post_id');
    }

    public function contentDraft(): BelongsTo
    {
        return $this->belongsTo(BrandContentDraft::class, 'content_draft_id');
    }
}
