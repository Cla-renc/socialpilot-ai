<?php

use App\Http\Controllers\AnalyticsStudioController;
use App\Http\Controllers\BrandCollaborationController;
use App\Http\Controllers\BrandController;
use App\Http\Controllers\ContentStudioController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MediaStudioController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SchedulingStudioController;
use App\Http\Controllers\SocialAccountManagementController;
use App\Http\Controllers\SocialOAuthController;
use App\Http\Controllers\TrendIntelligenceController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('welcome');
Route::view('/privacy', 'legal.privacy')->name('privacy');
Route::view('/terms', 'legal.terms')->name('terms');
Route::view('/data-deletion', 'legal.data-deletion')->name('data-deletion');
Route::get('/social/oauth/{provider}/callback', [SocialOAuthController::class, 'callback'])
    ->name('social.oauth.callback');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard/calendar', [DashboardController::class, 'calendar'])->name('dashboard.calendar');
    Route::post('/notifications/{id}/read', [DashboardController::class, 'markNotificationRead'])
        ->whereUuid('id')
        ->name('notifications.read');

    Route::resource('brands', BrandController::class)
        ->parameters(['brands' => 'brand']);
    Route::get('/brands/{brand}/settings', [BrandController::class, 'settings'])
        ->name('brands.settings');
    Route::put('/brands/{brand}/settings', [BrandController::class, 'updateSettings'])
        ->name('brands.settings.update');
    Route::get('/brands/{brand}/social-accounts', [SocialAccountManagementController::class, 'index'])
        ->name('brands.social-accounts.index');
    Route::get('/brands/{brand}/social-accounts/{socialAccount}', [SocialAccountManagementController::class, 'show'])
        ->name('brands.social-accounts.show');
    Route::get('/brands/{brand}/social-accounts/{provider}/connect', [SocialOAuthController::class, 'connect'])
        ->name('brands.social-accounts.connect');
    Route::delete('/brands/{brand}/social-accounts/{socialAccount}', [SocialAccountManagementController::class, 'destroy'])
        ->name('brands.social-accounts.destroy');
    Route::post('/brands/{brand}/social-accounts/{socialAccount}/check', [SocialAccountManagementController::class, 'check'])
        ->name('brands.social-accounts.check');

    Route::get('/brands/{brand}/collaboration', [BrandCollaborationController::class, 'index'])
        ->name('brands.collaboration');
    Route::get('/brands/{brand}/trends', [TrendIntelligenceController::class, 'index'])
        ->name('brands.trends.index');
    Route::post('/brands/{brand}/trends/research', [TrendIntelligenceController::class, 'startResearch'])
        ->middleware('throttle:6,1')
        ->name('brands.trends.research');
    Route::get('/brands/{brand}/trends/{topic}', [TrendIntelligenceController::class, 'show'])
        ->name('brands.trends.show');
    Route::post('/brands/{brand}/trends/{topic}/feedback', [TrendIntelligenceController::class, 'feedback'])
        ->name('brands.trends.feedback');
    Route::put('/brands/{brand}/trend-preferences', [TrendIntelligenceController::class, 'updatePreferences'])
        ->name('brands.trends.preferences');

    // Phase 6 — Content Studio
    Route::get('/brands/{brand}/content', [ContentStudioController::class, 'index'])
        ->name('brands.content.index');
    Route::get('/brands/{brand}/content/studio', [ContentStudioController::class, 'studio'])
        ->name('brands.content.studio');
    Route::get('/brands/{brand}/content/{draft}', [ContentStudioController::class, 'show'])
        ->name('brands.content.show');

    // Phase 7: Media Studio
    Route::get('/media', [MediaStudioController::class, 'dashboard'])->name('media.dashboard');
    Route::get('/media/generator', [MediaStudioController::class, 'generator'])->name('media.generator');
    Route::get('/media/library', [MediaStudioController::class, 'library'])->name('media.library');
    Route::get('/media/video-studio', [MediaStudioController::class, 'videoStudio'])->name('media.video-studio');
    Route::get('/brands/{brand}/visual-settings', [MediaStudioController::class, 'visualSettings'])->name('brands.visual-settings');
    Route::post('/brands/{brand}/visual-settings', [MediaStudioController::class, 'saveVisualSettings'])->name('brands.visual-settings.save');

    // Phase 8: Scheduling Studio
    Route::get('/scheduling', [SchedulingStudioController::class, 'index'])->name('scheduling.dashboard');
    Route::get('/scheduling/calendar', [SchedulingStudioController::class, 'calendar'])->name('scheduling.calendar');
    Route::get('/brands/{brand}/scheduling/schedule', [SchedulingStudioController::class, 'schedulePost'])->name('brands.scheduling.schedule');
    Route::get('/brands/{brand}/scheduling/best-times', [SchedulingStudioController::class, 'bestTimes'])->name('brands.scheduling.best-times');
    Route::get('/brands/{brand}/scheduling/queue', [SchedulingStudioController::class, 'queue'])->name('brands.scheduling.queue');
    Route::get('/brands/{brand}/scheduling/autopilot', [SchedulingStudioController::class, 'autopilot'])->name('brands.scheduling.autopilot');
    Route::get('/brands/{brand}/scheduling/activity', [SchedulingStudioController::class, 'activity'])->name('brands.scheduling.activity');

    // Phase 9: Publishing Studio
    Route::prefix('publishing')->name('publishing.')->group(function () {
        Route::get('/',              [\App\Http\Controllers\PublishingStudioController::class, 'dashboard'])->name('dashboard');
        Route::get('/history',      [\App\Http\Controllers\PublishingStudioController::class, 'history'])->name('history');
        Route::get('/failed',       [\App\Http\Controllers\PublishingStudioController::class, 'failed'])->name('failed');
        Route::get('/publish-now',  [\App\Http\Controllers\PublishingStudioController::class, 'publishNow'])->name('publish-now');
        Route::get('/notifications',[\App\Http\Controllers\PublishingStudioController::class, 'notifications'])->name('notifications');
        Route::get('/{attempt}',    [\App\Http\Controllers\PublishingStudioController::class, 'show'])->name('show');
    });

    // Phase 10: Analytics Studio
    Route::prefix('analytics')->name('analytics.')->group(function () {
        Route::get('/', [AnalyticsStudioController::class, 'dashboard'])->name('dashboard');
        Route::get('/brands/{brand}', [AnalyticsStudioController::class, 'brandAnalytics'])->name('brand');
        Route::get('/platforms', [AnalyticsStudioController::class, 'platformAnalytics'])->name('platforms');
        Route::get('/content', [AnalyticsStudioController::class, 'contentPerformance'])->name('content');
        Route::get('/audience', [AnalyticsStudioController::class, 'audienceInsights'])->name('audience');
        Route::get('/recommendations', [AnalyticsStudioController::class, 'recommendations'])->name('recommendations');
        Route::get('/reports', [AnalyticsStudioController::class, 'reports'])->name('reports');
        Route::get('/reports/{analyticsReport}/download', [AnalyticsStudioController::class, 'reportDownload'])->name('reports.download');
    });

    Route::middleware('auth')->group(function () {
        Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
        Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
        Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    });
});

require __DIR__.'/auth.php';
