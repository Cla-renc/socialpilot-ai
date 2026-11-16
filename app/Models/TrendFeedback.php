<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrendFeedback extends Model
{
    protected $fillable = [
        'trend_topic_id',
        'user_id',
        'feedback_type',
        'note',
    ];

    public function topic(): BelongsTo
    {
        return $this->belongsTo(TrendingTopic::class, 'trend_topic_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
