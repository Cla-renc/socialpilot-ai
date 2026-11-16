<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'terms_accepted_at',
        'terms_version',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'terms_accepted_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function brands(): HasMany
    {
        return $this->hasMany(Brand::class);
    }

    public function socialAccounts(): HasMany
    {
        return $this->hasMany(SocialAccount::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function contentDrafts(): HasMany
    {
        return $this->hasMany(BrandContentDraft::class);
    }

    public function brandPosts(): HasMany
    {
        return $this->hasMany(BrandPost::class);
    }

    public function campaigns(): HasMany
    {
        return $this->hasMany(Campaign::class);
    }

    public function brandMembers(): HasMany
    {
        return $this->hasMany(BrandMember::class);
    }

    public function approvalRequests(): HasMany
    {
        return $this->hasMany(BrandApprovalRequest::class, 'requested_by');
    }

    public function trendResearchJobs(): HasMany
    {
        return $this->hasMany(TrendResearchJob::class);
    }

    public function trendFeedback(): HasMany
    {
        return $this->hasMany(TrendFeedback::class);
    }

    public function scheduledPosts(): HasMany
    {
        return $this->hasMany(ScheduledPost::class);
    }

    public function publishingQueues(): HasMany
    {
        return $this->hasMany(PublishingQueue::class);
    }

    public function publishingAttempts(): HasMany
    {
        return $this->hasMany(PublishingAttempt::class);
    }

    public function publishingNotifications(): HasMany
    {
        return $this->hasMany(PublishingNotification::class);
    }

    public function oauthStates(): HasMany
    {
        return $this->hasMany(OAuthState::class);
    }

    public function analyticsReports(): HasMany
    {
        return $this->hasMany(AnalyticsReport::class);
    }

    public function aiOptimizationRecommendations(): HasMany
    {
        return $this->hasMany(AiOptimizationRecommendation::class);
    }
}
