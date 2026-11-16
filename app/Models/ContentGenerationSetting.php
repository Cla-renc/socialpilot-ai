<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContentGenerationSetting extends Model
{
    use HasFactory;

    protected $table = 'content_generation_settings';

    protected $fillable = [
        'brand_id',
        'default_objective',
        'default_category',
        'default_platform',
        'default_format',
        'default_tone',
        'preferred_hashtags',
        'avoid_hashtags',
        'writing_guidelines',
    ];

    protected $casts = [
        'preferred_hashtags' => 'array',
        'avoid_hashtags' => 'array',
    ];

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }
}
