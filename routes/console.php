<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('trends:schedule')->everyMinute()->withoutOverlapping();

// Phase 8: AI Scheduling and Autopilot recurring background schedules
Schedule::command('autopilot:process')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('scheduling:process-queue')->everyFifteenMinutes()->withoutOverlapping();
Schedule::job(new \App\Jobs\SendUpcomingPostReminderJob)->hourly()->withoutOverlapping();

// Phase 9: Publishing jobs and token refresh
Schedule::command('publishing:dispatch-scheduled')->everyMinute()->withoutOverlapping();
Schedule::command('publishing:refresh-tokens')->hourly()->withoutOverlapping();

// Phase 10: Social Media Analytics and AI Performance Optimization
Schedule::command('analytics:collect')->dailyAt('02:00')->withoutOverlapping();

// Phase 11: Production Database Daily Backup
Schedule::command('db:backup')->dailyAt('01:00')->withoutOverlapping();

