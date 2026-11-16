<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScheduledPostMedia extends Model
{
    use HasFactory;

    protected $table = 'scheduled_post_media';

    protected $fillable = [
        'scheduled_post_id',
        'media_asset_id',
        'order',
        'platform_variant',
    ];

    protected $casts = [
        'order' => 'integer',
    ];

    public function scheduledPost(): BelongsTo
    {
        return $this->belongsTo(ScheduledPost::class, 'scheduled_post_id');
    }

    public function mediaAsset(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'media_asset_id');
    }
}
