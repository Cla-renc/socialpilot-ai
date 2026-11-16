<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AutopilotSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'brand_id',
        'user_id',
        'is_enabled',
        'mode',
        'posts_per_week',
        'min_gap_hours',
        'preferred_days',
        'preferred_hours',
        'preferred_platforms',
        'preferred_categories',
        'requires_approval',
        'blackout_periods',
        'timezone',
    ];

    protected $casts = [
        'is_enabled'            => 'boolean',
        'requires_approval'     => 'boolean',
        'posts_per_week'        => 'integer',
        'min_gap_hours'         => 'integer',
        'preferred_days'        => 'array',
        'preferred_hours'       => 'array',
        'preferred_platforms'   => 'array',
        'preferred_categories'  => 'array',
        'blackout_periods'      => 'array',
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
