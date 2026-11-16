<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SchedulingRecommendation extends Model
{
    use HasFactory;

    protected $fillable = [
        'brand_id',
        'user_id',
        'platform',
        'day_of_week',
        'suggested_time',
        'timezone',
        'reason',
        'confidence',
        'evidence_type',
        'metadata',
        'is_active',
    ];

    protected $casts = [
        'confidence' => 'float',
        'metadata'   => 'array',
        'is_active'  => 'boolean',
    ];

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
