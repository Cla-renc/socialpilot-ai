<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use App\Models\MediaAsset;
use App\Models\MediaGenerationJob;
use App\Models\MediaStoryboard;
use App\Models\MediaVisualSetting;
use Illuminate\Support\Facades\Storage;

class Brand extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'description',
        'category',
        'industry',
        'niche',
        'website_url',
        'audience',
        'target_age_group',
        'audience_interests',
        'region',
        'language',
        'tone',
        'status',
        'logo_path',
        'profile_image_path',
    ];

    protected $casts = [
        'audience_interests' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function settings(): HasOne
    {
        return $this->hasOne(BrandSetting::class);
    }

    public function socialAccounts(): HasMany
    {
        return $this->hasMany(SocialAccount::class)
            ->where('social_accounts.user_id', $this->user_id);
    }

    public function contentDrafts(): HasMany
    {
        return $this->hasMany(BrandContentDraft::class);
    }

    public function posts(): HasMany
    {
        return $this->hasMany(BrandPost::class);
    }

    public function analytics(): HasMany
    {
        return $this->hasMany(BrandAnalytics::class);
    }

    public function campaigns(): HasMany
    {
        return $this->hasMany(Campaign::class);
    }

    public function members(): HasMany
    {
        return $this->hasMany(BrandMember::class);
    }

    public function approvalRequests(): HasMany
    {
        return $this->hasMany(BrandApprovalRequest::class);
    }

    public function trendResearchJobs(): HasMany
    {
        return $this->hasMany(TrendResearchJob::class);
    }

    public function trendingTopics(): HasMany
    {
        return $this->hasMany(TrendingTopic::class);
    }

    public function trendRecommendations(): HasMany
    {
        return $this->hasMany(TrendRecommendation::class);
    }

    public function trendPreference(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(BrandTrendPreference::class);
    }

    public function contentGenerationSetting(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(ContentGenerationSetting::class);
    }

    public function contentGenerationJobs(): HasMany
    {
        return $this->hasMany(ContentGenerationJob::class);
    }

    public function mediaAssets(): HasMany
    {
        return $this->hasMany(MediaAsset::class);
    }

    public function mediaGenerationJobs(): HasMany
    {
        return $this->hasMany(MediaGenerationJob::class);
    }

    public function mediaStoryboards(): HasMany
    {
        return $this->hasMany(MediaStoryboard::class);
    }

    public function visualSettings(): HasOne
    {
        return $this->hasOne(MediaVisualSetting::class);
    }

    public function scheduledPosts(): HasMany
    {
        return $this->hasMany(ScheduledPost::class);
    }

    public function publishingQueues(): HasMany
    {
        return $this->hasMany(PublishingQueue::class);
    }

    public function publishingQueueItems(): HasMany
    {
        return $this->hasMany(PublishingQueueItem::class);
    }

    public function autopilotSetting(): HasOne
    {
        return $this->hasOne(AutopilotSetting::class);
    }

    public function autopilotRules(): HasMany
    {
        return $this->hasMany(AutopilotRule::class)->orderBy('priority', 'desc');
    }

    public function autopilotActivityLogs(): HasMany
    {
        return $this->hasMany(AutopilotActivityLog::class)->latest();
    }

    public function schedulingRecommendations(): HasMany
    {
        return $this->hasMany(SchedulingRecommendation::class);
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

    public function analyticsReports(): HasMany
    {
        return $this->hasMany(AnalyticsReport::class);
    }

    public function aiOptimizationRecommendations(): HasMany
    {
        return $this->hasMany(AiOptimizationRecommendation::class);
    }

    public function getLogoUrlAttribute(): ?string
    {
        return $this->logo_path ? Storage::disk('public')->url($this->logo_path) : null;
    }
}
