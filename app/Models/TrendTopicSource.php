<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrendTopicSource extends Model
{
    protected $fillable = [
        'trend_topic_id',
        'trend_source_id',
        'source_key',
        'source_name',
        'title',
        'url',
        'url_hash',
        'published_at',
        'summary',
        'evidence',
        'metrics',
    ];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'metrics' => 'array',
        ];
    }

    public function topic(): BelongsTo
    {
        return $this->belongsTo(TrendingTopic::class, 'trend_topic_id');
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(TrendSource::class, 'trend_source_id');
    }
}
