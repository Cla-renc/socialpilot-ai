<?php

namespace App\Providers;

use App\Services\Analytics\AiOptimizationService;
use App\Services\Analytics\AnalyticsAggregationService;
use App\Services\Analytics\AnalyticsCalculationService;
use App\Services\Analytics\AnalyticsCollectionService;
use App\Services\Analytics\AnalyticsComparisonService;
use App\Services\Analytics\AnalyticsFreshnessService;
use App\Services\Analytics\AnalyticsManager;
use App\Services\Analytics\AnalyticsReportingService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(AnalyticsManager::class);
        $this->app->singleton(AnalyticsCollectionService::class);
        $this->app->singleton(AnalyticsAggregationService::class);
        $this->app->singleton(AnalyticsCalculationService::class);
        $this->app->singleton(AnalyticsComparisonService::class);
        $this->app->singleton(AnalyticsFreshnessService::class);
        $this->app->singleton(AnalyticsReportingService::class);
        $this->app->singleton(AiOptimizationService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if ($this->app->environment('production') || env('FORCE_HTTPS', false)) {
            URL::forceScheme('https');
        }

        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(120)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('auth-api', function (Request $request) {
            return Limit::perMinute(15)->by($request->ip());
        });

        $localCert = base_path('.venv/Lib/site-packages/certifi/cacert.pem');
        if (file_exists($localCert)) {
            Http::globalOptions(['verify' => $localCert]);
        }
    }
}
