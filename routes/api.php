<?php

use App\Http\Controllers\Api\InternalOpsController;
use App\Http\Controllers\Api\V1\AiGenerationController;
use App\Http\Controllers\Api\V1\AnalyticsController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BrandAnalyticsController;
use App\Http\Controllers\Api\V1\BrandApprovalRequestController;
use App\Http\Controllers\Api\V1\BrandContentDraftController;
use App\Http\Controllers\Api\V1\BrandController;
use App\Http\Controllers\Api\V1\BrandMemberController;
use App\Http\Controllers\Api\V1\BrandPostController;
use App\Http\Controllers\Api\V1\BrandSummaryController;
use App\Http\Controllers\Api\V1\BrandTrendController;
use App\Http\Controllers\Api\V1\CampaignController;
use App\Http\Controllers\Api\V1\ContentGenerationApiController;
use App\Http\Controllers\Api\V1\ContentLibraryApiController;
use App\Http\Controllers\Api\V1\ContentSettingsApiController;
use App\Http\Controllers\Api\V1\DashboardApiController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\MediaGenerationController;
use App\Http\Controllers\Api\V1\MediaLibraryController;
use App\Http\Controllers\Api\V1\MediaStoryboardController;
use App\Http\Controllers\Api\V1\MediaVisualSettingsController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\SocialAccountController;
use App\Http\Controllers\Api\V1\SocialConnectionController;
use App\Http\Controllers\Api\V1\AutopilotApiController;
use App\Http\Controllers\Api\V1\PublishingQueueApiController;
use App\Http\Controllers\Api\V1\ScheduledPostApiController;
use App\Http\Controllers\Api\V1\SchedulingCalendarApiController;
use App\Http\Controllers\Api\V1\SchedulingRecommendationApiController;
use App\Http\Controllers\Api\V1\SchedulingSettingsApiController;
use App\Http\Controllers\Api\V1\SubscriptionController;
use Illuminate\Support\Facades\Route;

Route::post('/internal/ops/cycle', [InternalOpsController::class, 'cycle']);

Route::prefix('v1')->group(function () {
    Route::post('/internal/ops/cycle', [InternalOpsController::class, 'cycle']);
    Route::post('/auth/register', [AuthController::class, 'register']);
    Route::post('/auth/login', [AuthController::class, 'login']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/auth/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/auth/reset-password', [AuthController::class, 'resetPassword']);
    Route::get('/health', [HealthController::class, 'index']);
    Route::get('/health/ready', [HealthController::class, 'ready']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/auth/user', [AuthController::class, 'me']);
        Route::get('/me', [AuthController::class, 'me']);
        Route::match(['put', 'patch'], '/auth/profile', [AuthController::class, 'updateProfile']);
        Route::match(['put', 'patch'], '/user/profile', [AuthController::class, 'updateProfile']);
        Route::put('/auth/password', [AuthController::class, 'updatePassword']);
        Route::put('/user/password', [AuthController::class, 'updatePassword']);
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/subscriptions', [SubscriptionController::class, 'index']);
        Route::get('/dashboard/overview', [DashboardApiController::class, 'overview']);
        Route::get('/dashboard/brands', [DashboardApiController::class, 'brands']);
        Route::get('/dashboard/content-activity', [DashboardApiController::class, 'contentActivity']);
        Route::get('/dashboard/publishing-calendar', [DashboardApiController::class, 'publishingCalendar']);
        Route::get('/dashboard/social-accounts', [DashboardApiController::class, 'socialAccounts']);
        Route::get('/dashboard/agent-activity', [DashboardApiController::class, 'agentActivity']);
        Route::get('/notifications', [NotificationController::class, 'index']);
        Route::post('/notifications/{id}/read', [NotificationController::class, 'markRead'])
            ->whereUuid('id');
        Route::get('/social-providers', [SocialConnectionController::class, 'providers']);
        Route::get('/social-accounts', [SocialAccountController::class, 'index']);
        Route::delete('/social-accounts/{socialAccount}', [SocialAccountController::class, 'destroy']);
        Route::get('/social-accounts/{socialAccount}/status', [SocialAccountController::class, 'status']);

        Route::post('/brands/{brand}/social-providers/{provider}/authorize', [SocialConnectionController::class, 'beginAuthorization']);
        Route::post('/brands/{brand}/social-accounts/connect', [SocialConnectionController::class, 'connectProvider']);
        Route::post('/social-accounts/{socialAccount}/check', [SocialAccountController::class, 'check']);

        Route::post('/brands/{brand}/social-accounts', [SocialConnectionController::class, 'connect']);
        Route::get('/brands/{brand}/social-accounts', [SocialAccountController::class, 'forBrand']);

        Route::apiResource('brands', BrandController::class)->names('api.v1.brands');
        Route::get('/brands/{brand}/overview', [BrandController::class, 'overview']);
        Route::get('/brands/{brand}/settings', [BrandController::class, 'settings']);
        Route::put('/brands/{brand}/settings', [BrandController::class, 'update']);
        Route::patch('/brands/{brand}/status', [BrandController::class, 'updateStatus']);
        Route::post('/brands/{brand}/activate', [BrandController::class, 'activate']);

        Route::get('/brands/{brand}/content-drafts', [BrandContentDraftController::class, 'index']);
        Route::post('/brands/{brand}/content-drafts', [BrandContentDraftController::class, 'store']);
        Route::get('/brands/{brand}/content-drafts/{draft}', [BrandContentDraftController::class, 'show']);
        Route::put('/brands/{brand}/content-drafts/{draft}', [BrandContentDraftController::class, 'update']);
        Route::delete('/brands/{brand}/content-drafts/{draft}', [BrandContentDraftController::class, 'destroy']);

        Route::post('/brands/{brand}/generate-content', [AiGenerationController::class, 'generate']);

        // Phase 6: AI Content Generation Agent Endpoints
        Route::post('/brands/{brand}/content/generate', [ContentGenerationApiController::class, 'generate']);
        Route::get('/content/generations/{generation}', [ContentGenerationApiController::class, 'status']);
        Route::get('/brands/{brand}/content-strategy', [ContentGenerationApiController::class, 'strategy']);

        // Phase 7: Media Generation
        Route::post('/brands/{brand}/media/images/generate', [MediaGenerationController::class, 'generateImage']);
        Route::post('/brands/{brand}/media/videos/generate', [MediaGenerationController::class, 'generateVideo']);
        Route::post('/brands/{brand}/media/storyboards/generate', [MediaStoryboardController::class, 'generate']);
        Route::post('/brands/{brand}/media/compose', [MediaGenerationController::class, 'composeGraphic']);
        Route::get('/brands/{brand}/media', [MediaLibraryController::class, 'index']);
        Route::post('/brands/{brand}/media/upload', [MediaLibraryController::class, 'upload']);
        Route::get('/brands/{brand}/visual-settings', [MediaVisualSettingsController::class, 'show']);
        Route::put('/brands/{brand}/visual-settings', [MediaVisualSettingsController::class, 'update']);

        Route::get('/media/images/{id}/status', [MediaGenerationController::class, 'imageStatus']);
        Route::get('/media/videos/{id}/status', [MediaGenerationController::class, 'videoStatus']);
        Route::post('/media/jobs/{job}/retry', [MediaGenerationController::class, 'retryJob']);
        Route::get('/media/{id}', [MediaLibraryController::class, 'show']);
        Route::put('/media/{id}', [MediaLibraryController::class, 'update']);
        Route::delete('/media/{id}', [MediaLibraryController::class, 'destroy']);
        Route::post('/media/upload', [MediaLibraryController::class, 'upload']);

        Route::get('/media/storyboards/{storyboard}', [MediaStoryboardController::class, 'show']);
        Route::put('/media/storyboards/{storyboard}', [MediaStoryboardController::class, 'update']);
        Route::delete('/media/storyboards/{storyboard}', [MediaStoryboardController::class, 'destroy']);
        Route::put('/media/storyboard-scenes/{scene}', [MediaStoryboardController::class, 'updateScene']);

        Route::get('/brands/{brand}/content', [ContentLibraryApiController::class, 'index']);
        Route::get('/content/{content}', [ContentLibraryApiController::class, 'show']);
        Route::put('/content/{content}', [ContentLibraryApiController::class, 'update']);
        Route::delete('/content/{content}', [ContentLibraryApiController::class, 'destroy']);

        Route::post('/content/{content}/variations', [ContentLibraryApiController::class, 'generateVariations']);
        Route::get('/content/{content}/variations', [ContentLibraryApiController::class, 'variations']);
        Route::post('/content/{content}/variations/{variation}/select', [ContentLibraryApiController::class, 'selectVariation']);

        Route::post('/content/{content}/improve', [ContentLibraryApiController::class, 'improve']);
        Route::get('/content/{content}/revisions', [ContentLibraryApiController::class, 'revisions']);

        Route::get('/brands/{brand}/content-settings', [ContentSettingsApiController::class, 'show']);
        Route::put('/brands/{brand}/content-settings', [ContentSettingsApiController::class, 'update']);

        Route::get('/brands/{brand}/analytics', [BrandAnalyticsController::class, 'index']);
        Route::post('/brands/{brand}/analytics', [BrandAnalyticsController::class, 'store']);
        Route::get('/brands/{brand}/summary', [BrandSummaryController::class, 'index']);

        Route::middleware('throttle:6,1')->post('/brands/{brand}/trends/research', [BrandTrendController::class, 'startResearch']);
        Route::get('/brands/{brand}/trends/research', [BrandTrendController::class, 'researchHistory']);
        Route::get('/brands/{brand}/trends/research/{researchJob}', [BrandTrendController::class, 'showResearch']);
        Route::get('/brands/{brand}/trends/status', [BrandTrendController::class, 'researchStatus']);
        Route::get('/brands/{brand}/trends/categories', [BrandTrendController::class, 'categories']);
        Route::get('/brands/{brand}/trends/sources', [BrandTrendController::class, 'sourceList']);
        Route::get('/brands/{brand}/trends', [BrandTrendController::class, 'index']);
        Route::get('/brands/{brand}/trends/{topic}', [BrandTrendController::class, 'show']);
        Route::post('/brands/{brand}/trends/{topic}/feedback', [BrandTrendController::class, 'feedback']);
        Route::get('/brands/{brand}/trend-preferences', [BrandTrendController::class, 'preferences']);
        Route::put('/brands/{brand}/trend-preferences', [BrandTrendController::class, 'updatePreferences']);
        Route::get('/brands/{brand}/recommendations', [BrandTrendController::class, 'recommendations']);
        Route::get('/brands/{brand}/recommendations/{recommendation}', [BrandTrendController::class, 'showRecommendation']);
        Route::post('/brands/{brand}/recommendations/{recommendation}/status', [BrandTrendController::class, 'updateRecommendationStatus']);
        Route::post('/brands/{brand}/recommendations/{recommendation}/feedback', [BrandTrendController::class, 'recommendationFeedback']);

        Route::get('/brands/{brand}/members', [BrandMemberController::class, 'index']);
        Route::post('/brands/{brand}/members', [BrandMemberController::class, 'store']);
        Route::put('/brands/{brand}/members/{member}', [BrandMemberController::class, 'update']);
        Route::delete('/brands/{brand}/members/{member}', [BrandMemberController::class, 'destroy']);

        Route::get('/brands/{brand}/approval-requests', [BrandApprovalRequestController::class, 'index']);
        Route::post('/brands/{brand}/approval-requests', [BrandApprovalRequestController::class, 'store']);
        Route::put('/brands/{brand}/approval-requests/{approvalRequest}', [BrandApprovalRequestController::class, 'update']);

        Route::get('/brands/{brand}/campaigns', [CampaignController::class, 'index']);
        Route::post('/brands/{brand}/campaigns', [CampaignController::class, 'store']);
        Route::get('/brands/{brand}/campaigns/{campaign}', [CampaignController::class, 'show']);

        Route::get('/brands/{brand}/posts', [BrandPostController::class, 'index']);
        Route::post('/brands/{brand}/posts', [BrandPostController::class, 'store']);
        Route::get('/brands/{brand}/posts/{post}', [BrandPostController::class, 'show']);

        // Phase 8: Scheduled Posts
        Route::get('/brands/{brand}/scheduled-posts', [ScheduledPostApiController::class, 'index']);
        Route::post('/brands/{brand}/scheduled-posts', [ScheduledPostApiController::class, 'store']);
        Route::get('/scheduled-posts/{id}', [ScheduledPostApiController::class, 'show']);
        Route::put('/scheduled-posts/{id}', [ScheduledPostApiController::class, 'update']);
        Route::delete('/scheduled-posts/{id}', [ScheduledPostApiController::class, 'destroy']);
        Route::post('/scheduled-posts/{id}/reschedule', [ScheduledPostApiController::class, 'reschedule']);
        Route::post('/scheduled-posts/{id}/cancel', [ScheduledPostApiController::class, 'cancel']);
        Route::post('/scheduled-posts/{id}/approve', [ScheduledPostApiController::class, 'approve']);

        // Phase 8: Scheduling Calendar
        Route::get('/brands/{brand}/calendar', [SchedulingCalendarApiController::class, 'index']);
        Route::get('/brands/{brand}/calendar/summary', [SchedulingCalendarApiController::class, 'summary']);

        // Phase 8: Publishing Queue
        Route::get('/brands/{brand}/publishing-queue', [PublishingQueueApiController::class, 'index']);
        Route::post('/brands/{brand}/publishing-queue', [PublishingQueueApiController::class, 'store']);
        Route::put('/publishing-queue/{id}', [PublishingQueueApiController::class, 'update']);
        Route::post('/publishing-queue/{id}/pause', [PublishingQueueApiController::class, 'pause']);
        Route::post('/publishing-queue/{id}/resume', [PublishingQueueApiController::class, 'resume']);
        Route::post('/publishing-queue/{id}/reorder', [PublishingQueueApiController::class, 'reorder']);
        Route::post('/publishing-queue/{id}/items', [PublishingQueueApiController::class, 'addItem']);
        Route::delete('/publishing-queue/items/{itemId}', [PublishingQueueApiController::class, 'removeItem']);

        // Phase 8: Scheduling Recommendations
        Route::post('/brands/{brand}/scheduling/recommendations', [SchedulingRecommendationApiController::class, 'recommendations']);
        Route::get('/brands/{brand}/scheduling/best-times', [SchedulingRecommendationApiController::class, 'bestTimes']);
        Route::post('/brands/{brand}/scheduling/conflicts', [SchedulingRecommendationApiController::class, 'conflicts']);

        // Phase 8: Autopilot
        Route::get('/brands/{brand}/autopilot', [AutopilotApiController::class, 'show']);
        Route::put('/brands/{brand}/autopilot', [AutopilotApiController::class, 'update']);
        Route::post('/brands/{brand}/autopilot/enable', [AutopilotApiController::class, 'enable']);
        Route::post('/brands/{brand}/autopilot/pause', [AutopilotApiController::class, 'pause']);
        Route::post('/brands/{brand}/autopilot/resume', [AutopilotApiController::class, 'resume']);
        Route::post('/brands/{brand}/autopilot/disable', [AutopilotApiController::class, 'disable']);
        Route::post('/brands/{brand}/autopilot/trigger', [AutopilotApiController::class, 'trigger']);
        Route::get('/brands/{brand}/autopilot/activity', [AutopilotApiController::class, 'activity']);
        Route::post('/brands/{brand}/autopilot/rules', [AutopilotApiController::class, 'storeRule']);
        Route::put('/brands/{brand}/autopilot/rules/{ruleId}', [AutopilotApiController::class, 'updateRule']);
        Route::delete('/brands/{brand}/autopilot/rules/{ruleId}', [AutopilotApiController::class, 'destroyRule']);

        // Phase 8: Scheduling Settings
        Route::get('/brands/{brand}/scheduling-settings', [SchedulingSettingsApiController::class, 'show']);
        Route::put('/brands/{brand}/scheduling-settings', [SchedulingSettingsApiController::class, 'update']);

        // Phase 9: Live Publishing Agent
        Route::prefix('publishing')->group(function () {
            Route::post('/publish-now', [\App\Http\Controllers\Api\V1\PublishingController::class, 'publishNow']);
            Route::get('/attempts', [\App\Http\Controllers\Api\V1\PublishingController::class, 'attempts']);
            Route::get('/attempts/{attempt}', [\App\Http\Controllers\Api\V1\PublishingController::class, 'attempt']);
            Route::post('/attempts/{attempt}/retry', [\App\Http\Controllers\Api\V1\PublishingController::class, 'retry']);
            Route::post('/attempts/{attempt}/cancel', [\App\Http\Controllers\Api\V1\PublishingController::class, 'cancel']);
            Route::get('/history', [\App\Http\Controllers\Api\V1\PublishingController::class, 'history']);
            Route::get('/failed', [\App\Http\Controllers\Api\V1\PublishingController::class, 'failed']);
            Route::get('/statistics', [\App\Http\Controllers\Api\V1\PublishingController::class, 'statistics']);
            Route::post('/scheduled-posts/{post}/publish', [\App\Http\Controllers\Api\V1\PublishingController::class, 'executeScheduled']);
            Route::post('/scheduled-posts/{post}/cancel', [\App\Http\Controllers\Api\V1\PublishingController::class, 'cancelScheduled']);
            Route::get('/notifications', [\App\Http\Controllers\Api\V1\PublishingNotificationController::class, 'index']);
            Route::get('/notifications/unread-count', [\App\Http\Controllers\Api\V1\PublishingNotificationController::class, 'unreadCount']);
            Route::post('/notifications/{notification}/read', [\App\Http\Controllers\Api\V1\PublishingNotificationController::class, 'markRead']);
            Route::post('/notifications/mark-all-read', [\App\Http\Controllers\Api\V1\PublishingNotificationController::class, 'markAllRead']);
        });

        // Phase 10: Social Media Analytics & AI Performance Optimization
        Route::prefix('analytics')->group(function () {
            Route::get('/overview', [AnalyticsController::class, 'overview']);
            Route::get('/summary', [AnalyticsController::class, 'summary']);
            Route::get('/brands/{brand}', [AnalyticsController::class, 'brandAnalytics']);
            Route::get('/brands/{brand}/comparison', [AnalyticsController::class, 'brandComparison']);
            Route::get('/platforms', [AnalyticsController::class, 'platformAnalytics']);
            Route::get('/accounts/{socialAccount}', [AnalyticsController::class, 'accountAnalytics']);
            Route::get('/posts', [AnalyticsController::class, 'posts']);
            Route::get('/posts/{externalPostId}', [AnalyticsController::class, 'postShow']);
            Route::get('/top-performing', [AnalyticsController::class, 'topPerforming']);
            Route::get('/underperforming', [AnalyticsController::class, 'underperforming']);
            Route::get('/audience', [AnalyticsController::class, 'audience']);
            Route::get('/history', [AnalyticsController::class, 'history']);
            Route::post('/collect', [AnalyticsController::class, 'triggerCollection']);
            Route::get('/collection-status', [AnalyticsController::class, 'collectionStatus']);
            Route::get('/recommendations', [AnalyticsController::class, 'recommendations']);
            Route::post('/recommendations/generate', [AnalyticsController::class, 'generateRecommendations']);
            Route::post('/recommendations/{id}/accept', [AnalyticsController::class, 'acceptRecommendation']);
            Route::post('/recommendations/{id}/dismiss', [AnalyticsController::class, 'dismissRecommendation']);
            Route::get('/reports', [AnalyticsController::class, 'reports']);
            Route::post('/reports/generate', [AnalyticsController::class, 'generateReport']);
            Route::get('/reports/{id}', [AnalyticsController::class, 'reportShow']);
            Route::get('/reports/{id}/download', [AnalyticsController::class, 'downloadReport']);
        });
    });
});
