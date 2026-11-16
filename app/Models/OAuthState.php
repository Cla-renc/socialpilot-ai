<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OAuthState extends Model
{
    use HasFactory;

    protected $table = 'oauth_states';

    protected $fillable = [
        'user_id',
        'brand_id',
        'social_account_id',
        'provider',
        'state_hash',
        'mode',
        'code_verifier',
        'metadata',
        'expires_at',
        'used_at',
    ];

    protected $casts = [
        'metadata'   => 'array',
        'expires_at' => 'datetime',
        'used_at'    => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function socialAccount(): BelongsTo
    {
        return $this->belongsTo(SocialAccount::class);
    }

    public function isValid(): bool
    {
        return $this->used_at === null && $this->expires_at->isFuture();
    }
}
