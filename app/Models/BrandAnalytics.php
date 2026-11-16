<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BrandAnalytics extends Model
{
    use HasFactory;

    protected $table = 'brand_analytics';

    protected $fillable = [
        'brand_id',
        'metric_date',
        'followers',
        'reach',
        'impressions',
        'engagements',
        'clicks',
        'shares',
        'saves',
        'replies',
        'engagement_rate',
        'metadata',
    ];

    protected $casts = [
        'metric_date' => 'date',
        'metadata' => 'array',
    ];

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }
}
