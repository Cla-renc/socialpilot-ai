<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AudienceInsight extends Model
{
    use HasFactory;

    protected $fillable = [
        'brand_id',
        'social_account_id',
        'platform',
        'insight_type',
        'dimension',
        'dimension_value',
        'metric_value',
        'reporting_period',
        'collected_at',
        'metadata',
    ];

    protected $casts = [
        'metric_value' => 'float',
        'collected_at' => 'datetime',
        'metadata'     => 'array',
    ];

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function socialAccount(): BelongsTo
    {
        return $this->belongsTo(SocialAccount::class);
    }

    public function scopeForType(Builder $query, string $type): Builder
    {
        return $query->where('insight_type', $type);
    }
}
