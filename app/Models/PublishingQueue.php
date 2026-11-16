<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PublishingQueue extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'brand_id',
        'name',
        'status',
        'posting_interval_minutes',
        'preferred_days',
        'preferred_hours',
        'timezone',
        'next_slot_at',
    ];

    protected $casts = [
        'posting_interval_minutes' => 'integer',
        'preferred_days'           => 'array',
        'preferred_hours'          => 'array',
        'next_slot_at'             => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PublishingQueueItem::class, 'publishing_queue_id')->orderBy('position');
    }
}
