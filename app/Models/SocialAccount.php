<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class SocialAccount extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'brand_id',
        'provider',
        'provider_account_id',
        'display_name',
        'account_type',
        'status',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (SocialAccount $account) {
            if (!$account->user_id && $account->brand_id) {
                $account->user_id = $account->brand?->user_id ?? Brand::find($account->brand_id)?->user_id;
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function token(): HasOne
    {
        return $this->hasOne(SocialAccountToken::class);
    }

    public function publishingAttempts(): HasMany
    {
        return $this->hasMany(PublishingAttempt::class);
    }

    public function analyticsSnapshots(): HasMany
    {
        return $this->hasMany(AnalyticsSnapshot::class);
    }

    public function postAnalytics(): HasMany
    {
        return $this->hasMany(PostAnalytic::class);
    }

    public function audienceInsights(): HasMany
    {
        return $this->hasMany(AudienceInsight::class);
    }

    public function analyticsCollectionLogs(): HasMany
    {
        return $this->hasMany(AnalyticsCollectionLog::class);
    }

    public function getPlatformAttribute(): ?string
    {
        return $this->provider;
    }

    public function oauthStates(): HasMany
    {
        return $this->hasMany(OAuthState::class);
    }

    public function scopeConnected(Builder $query): Builder
    {
        return $query->where('status', 'connected')
            ->whereDoesntHave('token', fn (Builder $tokens) => $tokens
                ->whereNotNull('expires_at')
                ->where('expires_at', '<=', now()));
    }

    public function getStatusAttribute(?string $status): string
    {
        if ($status === 'connected' && $this->token?->expires_at?->isPast()) {
            return 'token_expired';
        }

        return $status ?? 'not_connected';
    }
}
