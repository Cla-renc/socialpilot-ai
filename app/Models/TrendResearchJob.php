<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TrendResearchJob extends Model
{
    protected $fillable = [
        'user_id',
        'brand_id',
        'status',
        'result_state',
        'criteria_hash',
        'filters',
        'source_status',
        'result_meta',
        'error_message',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'filters' => 'array',
            'source_status' => 'array',
            'result_meta' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function topics(): HasMany
    {
        return $this->hasMany(TrendingTopic::class, 'research_job_id');
    }
}
