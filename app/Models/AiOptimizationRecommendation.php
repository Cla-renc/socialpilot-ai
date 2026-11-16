<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiOptimizationRecommendation extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'brand_id',
        'platform',
        'recommendation_type',
        'title',
        'description',
        'supporting_metrics',
        'confidence_level',
        'confidence',
        'priority',
        'recommendation_status',
        'status',
        'generated_at',
        'expires_at',
        'metadata',
    ];

    protected $appends = [
        'status',
        'confidence',
        'priority',
    ];

    protected $casts = [
        'supporting_metrics' => 'array',
        'generated_at'       => 'datetime',
        'expires_at'         => 'datetime',
        'metadata'           => 'array',
    ];

    public function getConfidenceAttribute(): ?string
    {
        return $this->attributes['confidence_level'] ?? null;
    }

    public function setConfidenceAttribute(?string $value): void
    {
        $this->attributes['confidence_level'] = $value;
    }

    public function getPriorityAttribute(): ?string
    {
        return $this->metadata['priority'] ?? 'normal';
    }

    public function setPriorityAttribute(?string $value): void
    {
        $meta = $this->metadata ?? [];
        $meta['priority'] = $value;
        $this->attributes['metadata'] = json_encode($meta);
    }

    public function getStatusAttribute(): ?string
    {
        return $this->attributes['recommendation_status'] ?? null;
    }

    public function setStatusAttribute(?string $value): void
    {
        $this->attributes['recommendation_status'] = $value;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('recommendation_status', 'active');
    }

    public function scopeHighConfidence(Builder $query): Builder
    {
        return $query->where('confidence_level', 'high');
    }
}
