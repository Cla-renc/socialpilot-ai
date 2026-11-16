<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContentVariation extends Model
{
    use HasFactory;

    protected $table = 'content_variations';

    protected $fillable = [
        'draft_id',
        'user_id',
        'brand_id',
        'style',
        'title',
        'hook',
        'body',
        'caption',
        'call_to_action',
        'hashtags',
        'quality_score',
        'quality_evaluation',
        'is_selected',
    ];

    protected $casts = [
        'hashtags' => 'array',
        'quality_evaluation' => 'array',
        'quality_score' => 'integer',
        'is_selected' => 'boolean',
    ];

    public function draft(): BelongsTo
    {
        return $this->belongsTo(BrandContentDraft::class, 'draft_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }
}
