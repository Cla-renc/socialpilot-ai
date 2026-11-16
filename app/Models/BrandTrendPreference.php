<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BrandTrendPreference extends Model
{
    protected $fillable = [
        'brand_id',
        'research_frequency',
        'research_time',
        'timezone',
        'preferred_sources',
        'categories',
        'geographic_market',
    ];

    protected function casts(): array
    {
        return [
            'preferred_sources' => 'array',
            'categories' => 'array',
        ];
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }
}
