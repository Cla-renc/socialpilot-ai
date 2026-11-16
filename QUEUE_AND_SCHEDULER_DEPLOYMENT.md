# SOCIALPILOT AI — QUEUE & SCHEDULER DEPLOYMENT GUIDE

**Engine:** Laravel Queue (Database Driver) + Laravel Scheduler (Artisan)  
**Process Manager:** Supervisord / Systemd / Cron  
**Idempotency:** Strict Single-Execution Lock Enforced  

---

## 1. QUEUE WORKER CONFIGURATION

SocialPilot AI uses the database queue connection (`QUEUE_CONNECTION=database`), storing queued jobs in the `jobs` database table. This eliminates the need for a paid Redis or Amazon SQS subscription.

### Worker Command
```bash
php artisan queue:work --sleep=3 --tries=3 --timeout=90 --rest=1
```

### Managed Jobs
- **Trend Intelligence:** `RunTrendResearchJob`
- **Content Generation:** `ProcessContentIdeaJob`
- **Media Generation:** `ProcessMediaGenerationJob`
- **Live Publishing:** `PublishScheduledPostJob`, `PublishImmediatePostJob`
- **Notifications:** `SendUpcomingPostReminderJob`
- **Analytics Collection:** `CollectPlatformAnalyticsJob`

---

## 2. SCHEDULED TASKS (8 VERIFIED COMMANDS)

Configured in `routes/console.php` with `withoutOverlapping()` mutex locks:

| Frequency | Command | Purpose |
|---|---|---|
| **Every Minute** | `php artisan trends:schedule` | Scans RSS feeds & live trends |
| **Every 5 Minutes** | `php artisan autopilot:process` | Evaluates autopilot rules and creates drafts |
| **Every 15 Minutes** | `php artisan scheduling:process-queue` | Verifies upcoming posting queue |
| **Hourly** | `SendUpcomingPostReminderJob` | Dispatches push notifications for upcoming posts |
| **Every Minute** | `php artisan publishing:dispatch-scheduled` | Dispatches posts due for publication |
| **Hourly** | `php artisan publishing:refresh-tokens` | Refreshes expiring OAuth tokens |
| **Daily (02:00)** | `php artisan analytics:collect` | Fetches social metrics from platforms |
| **Daily (01:00)** | `php artisan db:backup` | Creates timestamped database backup with retention pruning |

---

## 3. PUBLISHING IDEMPOTENCY & DUPLICATE PREVENTION

To ensure a social post is never published twice:
1. **Atomic Status Mutation:** When `DispatchScheduledPublishingCommand` queries posts due for publication (`where('status', 'scheduled')->where('scheduled_at', '<=', now())`), it dispatches `PublishScheduledPostJob`, which immediately updates the status to `publishing`:
   ```php
   $post->update(['status' => 'publishing']);
   ```
   Subsequent scheduler runs ignore posts with status `publishing` or `published`.
2. **Mutex Locks:** All scheduled commands use `->withoutOverlapping()`. Laravel creates a cache lock that prevents a second process from starting until the active process terminates.
3. **Publishing Attempt Tracking:** Each publish execution creates a unique `PublishingAttempt` record linked to the post, recording the platform's external post ID (`external_post_id`).

---

## 4. PROCESS MANAGEMENT (SUPERVISORD)

On a Linux production host or container, manage the queue worker via `supervisord.conf`:

```ini
[program:socialpilot-queue]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/html/artisan queue:work --sleep=3 --tries=3 --timeout=90
directory=/var/www/html
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/log/socialpilot-queue.log
```

---

## 5. SCHEDULER SYSTEM CRON SETUP

Add the standard Laravel scheduler entry to the server crontab:

```bash
# Open crontab editor
crontab -e -u www-data

# Add this single line (runs every minute):
* * * * * cd /var/www/html && php artisan schedule:run >> /dev/null 2>&1
```

### Alternative: External Free Cron for PaaS Environments
If deploying to a PaaS that sleeps (such as Render Free Web Services):
1. Use a free external cron monitor such as [Cron-Job.org](https://cron-job.org/) (100% free, zero credit card).
2. Create an automated HTTP job that calls `https://app.yourdomain.com/api/v1/health` or a secure internal route every 5 minutes.
3. This keeps the web instance warm and triggers scheduled dispatches reliably.
