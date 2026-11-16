<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ContentGenerationJob extends Model
{
    use HasFactory;

    protected $table = 'content_generation_jobs';

    protected $fillable = [
        'user_id',
        'brand_id',
        'status',
        'progress',
        'objective',
        'category',
        'platform',
        'content_format',
        'instructions',
        'trend_id',
        'provider',
        'model',
        'error_message',
        'result_draft_id',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'progress' => 'integer',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function trend(): BelongsTo
    {
        return $this->belongsTo(TrendingTopic::class, 'trend_id');
    }

    public function resultDraft(): BelongsTo
    {
        return $this->belongsTo(BrandContentDraft::class, 'result_draft_id');
    }
}
