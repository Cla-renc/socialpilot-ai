<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TrendSource extends Model
{
    protected $fillable = [
        'source_key',
        'name',
        'type',
        'connection_status',
        'last_successful_research_at',
        'last_error',
        'rate_limit_resets_at',
    ];

    protected function casts(): array
    {
        return [
            'last_successful_research_at' => 'datetime',
            'rate_limit_resets_at' => 'datetime',
        ];
    }

    public function topicSources(): HasMany
    {
        return $this->hasMany(TrendTopicSource::class);
    }
}
