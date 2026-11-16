<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TrendingTopic extends Model
{
    protected $fillable = [
        'brand_id',
        'research_job_id',
        'title',
        'normalized_title',
        'description',
        'summary',
        'content_angles',
        'content_angle_status',
        'content_angle_error',
        'category',
        'relevance_score',
        'relevance_reason',
        'momentum',
        'momentum_evidence',
        'geographic_market',
        'opportunity_score',
        'researched_at',
    ];

    protected function casts(): array
    {
        return [
            'relevance_score' => 'integer',
            'opportunity_score' => 'integer',
            'researched_at' => 'datetime',
            'content_angles' => 'array',
        ];
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function researchJob(): BelongsTo
    {
        return $this->belongsTo(TrendResearchJob::class);
    }

    public function sources(): HasMany
    {
        return $this->hasMany(TrendTopicSource::class, 'trend_topic_id');
    }

    public function feedback(): HasMany
    {
        return $this->hasMany(TrendFeedback::class, 'trend_topic_id');
    }
}
