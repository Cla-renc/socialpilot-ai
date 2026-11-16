<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BrandContentDraft extends Model
{
    use HasFactory;

    protected $table = 'brand_content_drafts';

    protected $fillable = [
        'user_id',
        'brand_id',
        'title',
        'platform',
        'content_type',
        'content_format',
        'objective',
        'category',
        'status',
        'prompt',
        'body',
        'hook',
        'caption',
        'call_to_action',
        'hashtags',
        'quality_score',
        'quality_evaluation',
        'generation_job_id',
        'selected_variation_id',
        'metadata',
        'scheduled_for',
        'ai_provider',
        'ai_model',
        'is_ai_generated',
    ];

    protected $casts = [
        'hashtags' => 'array',
        'quality_evaluation' => 'array',
        'quality_score' => 'integer',
        'metadata' => 'array',
        'scheduled_for' => 'datetime',
        'is_ai_generated' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function generationJob(): BelongsTo
    {
        return $this->belongsTo(ContentGenerationJob::class, 'generation_job_id');
    }

    public function variations(): HasMany
    {
        return $this->hasMany(ContentVariation::class, 'draft_id');
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(ContentRevision::class, 'draft_id')->orderBy('revision_number', 'desc');
    }

    public function trendSources(): HasMany
    {
        return $this->hasMany(ContentTrendSource::class, 'draft_id');
    }

    public function trendingTopics(): BelongsToMany
    {
        return $this->belongsToMany(TrendingTopic::class, 'content_trend_sources', 'draft_id', 'trending_topic_id')
            ->withPivot(['trend_source_id', 'relationship_type'])
            ->withTimestamps();
    }
}
