<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContentRevision extends Model
{
    use HasFactory;

    protected $table = 'content_revisions';

    protected $fillable = [
        'draft_id',
        'user_id',
        'revision_number',
        'action',
        'instructions',
        'title',
        'hook',
        'body',
        'caption',
        'call_to_action',
        'hashtags',
        'quality_score',
    ];

    protected $casts = [
        'revision_number' => 'integer',
        'hashtags' => 'array',
        'quality_score' => 'integer',
    ];

    public function draft(): BelongsTo
    {
        return $this->belongsTo(BrandContentDraft::class, 'draft_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
