<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrendRecommendation extends Model
{
    use HasFactory;

    protected $fillable = [
        'brand_id',
        'trend_topic_id',
        'cluster_id',
        'score',
        'relevance_classification',
        'explanation',
        'content_package',
        'target_platform',
        'suggested_format',
        'status',
        'feedback_status',
    ];

    protected function casts(): array
    {
        return [
            'score' => 'integer',
            'explanation' => 'array',
            'content_package' => 'array',
        ];
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function topic(): BelongsTo
    {
        return $this->belongsTo(TrendingTopic::class, 'trend_topic_id');
    }
}
