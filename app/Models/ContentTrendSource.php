<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContentTrendSource extends Model
{
    use HasFactory;

    protected $table = 'content_trend_sources';

    protected $fillable = [
        'draft_id',
        'trending_topic_id',
        'trend_source_id',
        'relationship_type',
    ];

    public function draft(): BelongsTo
    {
        return $this->belongsTo(BrandContentDraft::class, 'draft_id');
    }

    public function topic(): BelongsTo
    {
        return $this->belongsTo(TrendingTopic::class, 'trending_topic_id');
    }

    public function trendSource(): BelongsTo
    {
        return $this->belongsTo(TrendSource::class, 'trend_source_id');
    }
}
