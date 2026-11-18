# STAGE 5.4.3 — STAGING DEPLOYMENT PREPARATION REPORT

**Project:** SocialPilot AI  
**Repository:** `Cla-renc/socialpilot-ai`, branch `main`  
**Report date:** 2026-11-18  
**Audit scope:** Read-only inspection of all runtime configuration and deployment components  
**Target environment:** Render Free Tier — Staging  
**Target database:** `socialpilot_staging` (TiDB Cloud, eu-central-1)  
**Schema state:** 59 tables · 29 migrations · 0 pending · 106 foreign keys  

---

## AUDIT SCOPE

The following files and directories were inspected read-only as part of this stage:

| File / Directory | Inspected |
|---|---|
| `Dockerfile.unified` | ✅ |
| `docker/docker-entrypoint.sh` | ✅ |
| `docker/supervisor/supervisord.conf` | ✅ |
| `docker/supervisor/supervisord.laravel.conf` | ✅ |
| `docker/nginx/default.conf` | ✅ |
| `docker/php/php.ini` | ✅ |
| `docker/php/php-fpm.d/zz-docker.conf` | ✅ |
| `ai_engine/requirements.txt` | ✅ |
| `ai_engine/ai_engine/main.py` | ✅ |
| `config/database.php` | ✅ |
| `config/filesystems.php` | ✅ |
| `config/queue.php` | ✅ |
| `config/services.php` | ✅ |
| `bootstrap/app.php` | ✅ |
| `routes/api.php` | ✅ |
| `routes/console.php` | ✅ |
| `app/Http/Controllers/Api/V1/HealthController.php` | ✅ |
| `app/Http/Controllers/Api/InternalOpsController.php` | ✅ |
| `app/Console/Commands/` (directory listing) | ✅ |
| `mobile/lib/main.dart` (API config lines) | ✅ |
| `mobile/lib/services/analytics_service.dart` | ✅ |
| `mobile/lib/services/publishing_service.dart` | ✅ |
| `mobile/pubspec.yaml` | ✅ |
| `.env.example` | ✅ |
| `.env.render.example` | ✅ |
| `composer.json` (S3 dependency check) | ✅ |
| `STAGE_5_4_2_PUBLIC_STAGING_DEPLOYMENT_REPORT.md` | ✅ |

No `render.yaml` or `render.json` file exists in the project root (manual Render dashboard configuration is required).

---

## 1. ARCHITECTURE OVERVIEW

SocialPilot AI uses a **single-container unified deployment** strategy. All runtime processes run inside one Docker image under Supervisor.

```
Render Web Service
└── Docker container (php:8.2-fpm-bookworm)
    ├── Supervisor (process manager)
    │   ├── php-fpm @ 127.0.0.1:9000     (priority 10)
    │   ├── uvicorn (FastAPI) @ 127.0.0.1:8080  (priority 20)
    │   └── nginx @ $PORT                 (priority 30)
    └── Entrypoint: docker/docker-entrypoint.sh
        ├── Injects $PORT into nginx config
        ├── Creates storage directories
        ├── Runs storage:link
        └── Optionally runs config:cache / route:cache
```

**Queue and scheduler** do not run as persistent processes. They are invoked on-demand via the HTTP endpoint `/api/internal/ops/cycle` (POST, Bearer token auth), which atomically runs `schedule:run` + `queue:work --stop-when-empty --max-jobs=10 --max-time=25`. This endpoint must be pinged by an external free-tier cron service (e.g. UptimeRobot) every 25 minutes during active hours.

---

## 2. COMPONENT FINDINGS

### 2.1 Container Build — READY

| Item | Detail |
|---|---|
| Base image | `php:8.2-fpm-bookworm` (Debian 12) |
| Build stages | 2-stage: Node.js frontend builder + PHP runtime |
| Node build | Runs `npm install && npm run build` to compile Vite assets |
| PHP extensions | PDO, pdo_mysql, pdo_sqlite, opcache, gd, zip, intl, pcntl, mbstring, xml, bcmath |
| Python runtime | Installed via `python3 pip3` for FastAPI engine |
| System CA bundle | `/etc/ssl/certs/ca-certificates.crt` available in bookworm — TiDB TLS works without manual cert install |
| Composer | `composer install --no-dev --optimize-autoloader` |
| Entrypoint | `docker/docker-entrypoint.sh` — handles `$PORT` injection, storage dirs, `storage:link` |

No build-time secrets in `Dockerfile.unified`. Build is repeatable and stateless.

### 2.2 Nginx — READY

| Item | Detail |
|---|---|
| Port binding | Reads `$PORT` env var at startup via `sed` replacement — compatible with Render's dynamic port injection |
| PHP-FPM upstream | `127.0.0.1:9000` (loopback only, not exposed) |
| `/up` health check | Proxied to Laravel built-in health endpoint |
| Upload limit | `client_max_body_size 50M` — matches `upload_max_filesize = 50M` in `php.ini` |
| HTTPS termination | Handled upstream by Render's edge proxy; `FORCE_HTTPS=true` + `trustProxies(at: '*')` ensures correct URL generation |

### 2.3 PHP-FPM — READY (with RAM note)

| Item | Detail |
|---|---|
| Listen | `127.0.0.1:9000` |
| Process manager | `dynamic` |
| `pm.max_children` | **2** |
| `pm.start_servers` | 1 |
| `pm.min_spare_servers` | 1 |
| `pm.max_spare_servers` | 2 |
| `pm.max_requests` | 500 (prevents memory leaks) |
| `memory_limit` | `128M` per PHP worker |
| `opcache.memory_consumption` | `64M` |

**RAM budget on Render free tier (512MB total):**

| Process | Estimated RAM |
|---|---|
| PHP-FPM workers (max 2 × 128MB) | ~256MB |
| OPcache | 64MB |
| Nginx | ~10MB |
| Supervisor | ~5MB |
| Python/uvicorn (FastAPI) | ~80–120MB |
| OS overhead | ~30MB |
| **Total estimated peak** | **~445–465MB** |

This is within the 512MB limit with ~47–67MB of headroom. The configuration is intentionally conservative (`pm.max_children=2`). No change required; deployment should proceed with current values.

### 2.4 FastAPI / AI Engine — READY (token configuration required)

| Item | Detail |
|---|---|
| Bind address | `127.0.0.1:8080` — not publicly accessible |
| Auth mechanism | `_verify_internal_token()` checks `AI_ENGINE_INTERNAL_TOKEN` or `TREND_ENGINE_INTERNAL_TOKEN` |
| Behavior without token | Returns `503 Service Unavailable` — FastAPI starts but rejects all requests |
| Dependencies | `fastapi>=0.115`, `httpx>=0.28`, `uvicorn[standard]>=0.34` |
| Installation | Installed via `pip3 install -r ai_engine/requirements.txt` during Docker build |

`AI_ENGINE_INTERNAL_TOKEN` and `TREND_ENGINE_INTERNAL_TOKEN` **must** be set in Render env vars. Generate with `openssl rand -hex 32`.

### 2.5 TiDB TLS Connectivity — READY

| Item | Detail |
|---|---|
| Staging connection name | `tidb_staging_app` (app runtime, least-privilege, no DDL) |
| `MYSQL_ATTR_SSL_CA` | `/etc/ssl/certs/ca-certificates.crt` — present in Debian Bookworm base image |
| Staging DB | `socialpilot_staging` — 59 tables, 29 migrations, fully applied |
| Default Laravel connection | `mysql` — must be pointed to staging app credentials via `DB_*` env vars |

The `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` env vars drive the default `mysql` connection. For staging, set these to the `tidb_staging_app` account credentials pointing to `socialpilot_staging`.

### 2.6 Session, Cache, Queue — READY

| Driver | Config | Notes |
|---|---|---|
| Session | `database` | No Redis required |
| Cache | `database` | No Redis required |
| Queue | `database` | Default `jobs` table, `retry_after=90s` |
| Failed jobs | `database-uuids` | `failed_jobs` table |
| Job batches | `database` | `job_batches` table |

All three infrastructure tables (`jobs`, `failed_jobs`, `job_batches`) are part of the 59-table schema already applied.

### 2.7 Internal Ops Cycle Endpoint — READY (external ping required)

| Item | Detail |
|---|---|
| Route | `POST /api/internal/ops/cycle` |
| Authentication | `Authorization: Bearer <INTERNAL_CRON_SECRET>` (timing-safe comparison) |
| Registered in | `routes/api.php` line 37 |
| Behavior | Atomic cache-lock (60s), runs `schedule:run` + `queue:work --stop-when-empty --max-jobs=10 --max-time=25 --tries=3` |
| Required env var | `INTERNAL_CRON_SECRET` — must be set in Render env vars |

Scheduled tasks (from `routes/console.php`):

| Command | Frequency |
|---|---|
| `trends:schedule` | Every 1 minute |
| `publishing:dispatch-scheduled` | Every 1 minute |
| `autopilot:process` | Every 5 minutes |
| `scheduling:process-queue` | Every 15 minutes |
| `SendUpcomingPostReminderJob` | Hourly |
| `publishing:refresh-tokens` | Hourly |
| `analytics:collect` | Daily 02:00 UTC |
| `db:backup` | Daily 01:00 UTC |

> [!IMPORTANT]
> Strategy C (duty cycle 08:00–22:00 UTC, ping every 25 min) covers all tasks that run during active hours. The `db:backup` and `analytics:collect` tasks run at 01:00 and 02:00 UTC (outside duty cycle). These will only execute if the container is awake at that time, which is not guaranteed on Render free tier with 15-min inactivity sleep.

### 2.8 Health Check Endpoints — READY

| Endpoint | Method | Purpose |
|---|---|---|
| `/up` | GET | Laravel built-in; returns 200 when application is booted |
| `/api/v1/health` | GET | DB connectivity check; counts users and brands |
| `/api/v1/health/ready` | GET | Returns `{"ready": true}` or 503 if DB not ready |

Render should be configured to use `/up` as the health check path.

### 2.9 Reverse Proxy Trust — READY

`bootstrap/app.php` sets `trustProxies(at: '*')`. This ensures:
- `HTTPS` scheme is correctly detected behind Render's edge proxy.
- `APP_URL` is used for redirect generation.
- Sanctum authentication works correctly with HTTPS cookies.

### 2.10 File Storage / Media — BLOCKED

| Item | Status | Detail |
|---|---|---|
| Default disk | `local` | `storage/app/private` inside container |
| Public disk | `local` | `storage/app/public` with `storage:link` |
| Media storage disk | `local` (`MEDIA_STORAGE_DISK=local`) | Configured in `config/services.php` media section |
| S3 Flysystem adapter | **NOT installed** | `league/flysystem-aws-s3-v3` is absent from `composer.json` |
| Render persistent disk | **Not available** on free tier | Persistent storage requires paid plan |

**BLOCKER:** All uploaded and AI-generated media files stored to `local` disk are **ephemeral on Render**. Files are lost on every container restart or redeploy. This is an accepted limitation for the initial staging verification, as documented in `.env.render.example`:

> *"Local storage is ephemeral on Render (lost on each deploy / restart). This is acceptable for initial staging verification only."*

Mitigation path (not in scope for this stage): Install `league/flysystem-aws-s3-v3`, configure an S3-compatible bucket (e.g. Supabase Storage, Cloudflare R2 free tier), and set `FILESYSTEM_DISK=s3`.

### 2.11 Queue Continuity During Sleep — BLOCKED (by design for staging)

| Item | Detail |
|---|---|
| Render free tier inactivity sleep | Container sleeps after 15 minutes of no inbound HTTP requests |
| Jobs queued during sleep | Remain in the `jobs` table but are not processed until next wake + ops cycle ping |
| Impact | Low-priority for staging verification; no jobs are lost (they persist in TiDB) |

This is an inherent Render free tier limitation and is acceptable for staging. Production deployment would require a paid Render plan or a separate worker process.

### 2.12 Flutter Mobile App — NEEDS VERIFICATION

| Item | Detail |
|---|---|
| App location | `mobile/` directory |
| API base URL mechanism | `String.fromEnvironment('API_BASE_URL', defaultValue: 'http://10.0.2.2:8000/api/v1')` |
| Used in | `main.dart`, `analytics_service.dart`, `publishing_service.dart` |
| Analytics API | Same `API_BASE_URL` dart-define variable |
| Publishing API | Same `API_BASE_URL` dart-define variable |

The Flutter app uses a **compile-time dart-define** for the API base URL. There is no runtime-configurable API base URL. To point the app at the Render staging deployment, it must be **rebuilt** with:

```
flutter build apk --dart-define=API_BASE_URL=https://<your-app-name>.onrender.com/api/v1
```

> [!IMPORTANT]
> The Flutter app cannot be redirected to staging without a rebuild. This is a **build-time requirement**, not a runtime configuration issue. If Flutter testing against staging is a goal of this deployment, the app must be rebuilt with the staging `APP_URL` before testing.

### 2.13 PHP Memory and OPcache — READY

| Setting | Value |
|---|---|
| `memory_limit` | `128M` |
| `upload_max_filesize` | `50M` |
| `post_max_size` | `50M` |
| `max_execution_time` | `60s` |
| `opcache.enable` | `1` |
| `opcache.memory_consumption` | `64M` |
| `opcache.validate_timestamps` | `0` (production mode — files not re-read) |
| `opcache.revalidate_freq` | `0` |

OPcache is set to production mode (`validate_timestamps=0`). Config cache and route cache should be warmed at deploy time (entrypoint handles this via `config:cache` / `route:cache`).

### 2.14 Social Platform OAuth — NEEDS VERIFICATION

The following OAuth apps must be configured with Render staging redirect URIs before OAuth flows can be tested:

| Platform | Redirect URI pattern |
|---|---|
| LinkedIn | `https://<your-app>.onrender.com/social/oauth/linkedin/callback` |
| Meta (Facebook/Instagram) | `https://<your-app>.onrender.com/social/oauth/facebook/callback` |
| X (Twitter) | `https://<your-app>.onrender.com/social/oauth/x/callback` |
| TikTok | `https://<your-app>.onrender.com/social/oauth/tiktok/callback` |
| Google / YouTube | `https://<your-app>.onrender.com/social/oauth/youtube/callback` |

These are optional for initial staging deployment verification but required before publishing features can be tested.

### 2.15 Render Free Tier Limits Summary

| Limit | Value | Impact |
|---|---|---|
| RAM | 512MB | PHP-FPM max_children=2 is correctly set for this limit |
| CPU | 0.1 vCPU | Adequate for staging traffic |
| Instance hours | 750/month | Strategy C (duty cycle) uses ~432–446h/month — 40% buffer |
| Persistent disk | None on free tier | Local storage is ephemeral (see §2.10) |
| Inactivity sleep | 15 minutes | Cold start ~50–90s; external cron ping prevents sleep during duty cycle |
| Custom domains | 1 | `.onrender.com` subdomain available free |
| TLS | Automatic | Render provides free TLS for `.onrender.com` domains |

---

## 3. REQUIRED ENVIRONMENT VARIABLES

The following env var **names** must be set in the Render dashboard. No values or secrets are included in this report. Use `.env.render.example` as the template.

### 3.1 Mandatory — Application will not start correctly without these

```
APP_NAME
APP_ENV=production
APP_KEY                          # Generate: php artisan key:generate --show
APP_DEBUG=false
APP_URL                          # https://<your-app-name>.onrender.com
```

### 3.2 Mandatory — Database (TiDB Staging App Account)

```
DB_CONNECTION=mysql
DB_HOST                          # TiDB gateway hostname
DB_PORT=4000
DB_DATABASE                      # socialpilot_staging
DB_USERNAME                      # staging app account username
DB_PASSWORD                      # staging app account password
MYSQL_ATTR_SSL_CA=/etc/ssl/certs/ca-certificates.crt
```

### 3.3 Mandatory — Session, Cache, Queue

```
SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database
```

### 3.4 Mandatory — Internal Service Authentication

```
AI_ENGINE_INTERNAL_TOKEN         # Generate: openssl rand -hex 32
TREND_ENGINE_INTERNAL_TOKEN      # Generate: openssl rand -hex 32
PUBLISHING_INTERNAL_TOKEN        # Generate: openssl rand -hex 32
INTERNAL_CRON_SECRET             # Generate: openssl rand -hex 32
```

### 3.5 Mandatory — Internal Service URLs (loopback, fixed values)

```
AI_ENGINE_URL=http://127.0.0.1:8080
TREND_ENGINE_URL=http://127.0.0.1:8080
```

### 3.6 Mandatory — Security

```
FORCE_HTTPS=true
CORS_ALLOWED_ORIGINS             # https://<your-app-name>.onrender.com
SANCTUM_STATEFUL_DOMAINS         # <your-app-name>.onrender.com
SANCTUM_TOKEN_EXPIRATION_MINUTES=10080
```

### 3.7 Mandatory — Storage (initial staging)

```
FILESYSTEM_DISK=local
MEDIA_STORAGE_DISK=local
MEDIA_MAX_UPLOAD_MB=20
MEDIA_GENERATION_TIMEOUT=120
```

### 3.8 Mandatory — Logging

```
LOG_CHANNEL=stderr
LOG_LEVEL=warning
```

### 3.9 Optional — AI Content Providers (leave blank to use built-in engine)

```
AI_PROVIDER=internal
AI_MODEL=socialpilot-lite
OPENAI_API_KEY
GEMINI_API_KEY
OPENROUTER_API_KEY
GROQ_API_KEY
```

### 3.10 Optional — Social OAuth (required only to test publishing)

```
LINKEDIN_CLIENT_ID / LINKEDIN_CLIENT_SECRET / LINKEDIN_REDIRECT_URI
META_APP_ID / META_APP_SECRET / META_REDIRECT_URI
X_CLIENT_ID / X_CLIENT_SECRET / X_REDIRECT_URI
TIKTOK_CLIENT_KEY / TIKTOK_CLIENT_SECRET / TIKTOK_REDIRECT_URI
GOOGLE_CLIENT_ID / GOOGLE_CLIENT_SECRET / GOOGLE_REDIRECT_URI
```

### 3.11 Optional — Trend Research APIs

```
NEWSAPI_API_KEY
REDDIT_CLIENT_ID / REDDIT_CLIENT_SECRET
YOUTUBE_API_KEY
SERPAPI_API_KEY
TREND_RSS_FEEDS=https://feeds.bbci.co.uk/news/rss.xml
```

### 3.12 Optional — Email (log driver for staging)

```
MAIL_MAILER=log
MAIL_FROM_ADDRESS
MAIL_FROM_NAME
```

---

## 4. DEPLOYMENT SEQUENCE

The following is the recommended deployment sequence. No step in this sequence is authorized by this report — this is a preparation plan only.

| Step | Action | Notes |
|---|---|---|
| 1 | In Render dashboard, create a new **Web Service** | Select "Deploy from Git repository" → `Cla-renc/socialpilot-ai` |
| 2 | Set **Dockerfile path** to `Dockerfile.unified` | Render auto-detects Dockerfile; specify path if not auto-detected |
| 3 | Set **branch** to `main` | |
| 4 | Set **Health Check Path** to `/up` | Render will probe this after each deploy |
| 5 | Set **all mandatory env vars** from §3 above | Use `.env.render.example` as guide; paste key-value pairs into Render dashboard |
| 6 | **Deploy** | Render builds image, runs entrypoint.sh, starts Supervisor |
| 7 | Verify `/up` returns HTTP 200 | Confirms Laravel booted successfully |
| 8 | Verify `/api/v1/health/ready` returns `{"ready": true}` | Confirms TiDB TLS connection is live |
| 9 | Verify `/api/v1/health` returns DB row counts | Confirms database queries work end-to-end |
| 10 | Configure external cron service | UptimeRobot free tier: HTTP POST every 25 min, 08:00–22:00 UTC, `Authorization: Bearer <INTERNAL_CRON_SECRET>` header, URL: `https://<app>.onrender.com/api/internal/ops/cycle` |
| 11 | Trigger one manual ops cycle | POST to `/api/internal/ops/cycle` and verify 200 response with `{"ok": true}` |
| 12 | Register test user via API | `POST /api/v1/auth/register` |
| 13 | Generate test content via AI engine | Verifies FastAPI loopback + internal token auth |
| 14 | Verify post is stored in DB | Confirms queue/session/database writes work |

---

## 5. POST-DEPLOYMENT SMOKE TEST PLAN

After deployment (not authorized in this stage):

| Test | Endpoint / Action | Expected Result |
|---|---|---|
| Container boot | `/up` | HTTP 200 |
| DB connectivity | `/api/v1/health/ready` | `{"ready": true}` |
| DB row counts | `/api/v1/health` | `{"users": 0, "brands": 0, "db": true}` (empty staging DB) |
| Ops cycle ping | `POST /api/internal/ops/cycle` with Bearer token | `{"ok": true}` |
| User registration | `POST /api/v1/auth/register` | 201 Created with token |
| AI content generation | Authenticated POST to content generation endpoint | 200 with generated content |
| Media upload (staging caveat) | POST media upload endpoint | 201 Created — file stored locally (ephemeral) |
| Queue drain | Check `jobs` table row count after ops cycle | Should decrease as jobs are processed |

---

## 6. KNOWN LIMITATIONS AND WORKAROUNDS

| Limitation | Severity | Workaround for Staging |
|---|---|---|
| Ephemeral local storage | Medium | Acceptable for staging — no S3 package installed; test media features with this constraint in mind |
| No persistent disk on free tier | Medium | Same as above |
| `analytics:collect` and `db:backup` miss at 01:00–02:00 UTC | Low | Acceptable for staging; run manually via ops cycle if needed |
| Flutter app requires rebuild for staging URL | Low | Rebuild with `--dart-define=API_BASE_URL=https://<app>.onrender.com/api/v1` |
| Cold start latency (50–90s after inactivity) | Low | External cron ping every 25 min prevents sleep during duty cycle |
| `pm.max_children=2` limits concurrency | Low | Appropriate for staging and free tier RAM |

---

## 7. FINDINGS CLASSIFICATION SUMMARY

### READY ✅

| Component | Evidence |
|---|---|
| `Dockerfile.unified` build process | 2-stage build, no build-time secrets, fully deterministic |
| Nginx dynamic port binding (`$PORT`) | `sed` replacement in entrypoint; Render injects `$PORT` |
| PHP-FPM + Supervisor architecture | `pm.max_children=2`, fits within 512MB RAM budget |
| FastAPI / uvicorn loopback isolation | `127.0.0.1:8080`, not exposed publicly |
| TiDB TLS connectivity | System CA bundle at `/etc/ssl/certs/ca-certificates.crt` confirmed present in Bookworm |
| `trustProxies(at: '*')` for Render edge | Set in `bootstrap/app.php` |
| Health endpoints: `/up`, `/api/v1/health`, `/api/v1/health/ready` | All confirmed in controllers |
| Internal ops cycle endpoint `/api/internal/ops/cycle` | Route confirmed in `routes/api.php`; timing-safe auth confirmed |
| Database session / cache / queue (no Redis) | Confirmed in `config/queue.php`, `config/filesystems.php` |
| OPcache production configuration | `validate_timestamps=0`, `revalidate_freq=0` |
| 59-table staging schema | Fully applied — 29 migrations, 0 pending, 106 foreign keys |
| `.env.render.example` template | Present, complete, no secrets, committed to repo |

### BLOCKED 🔴

| Blocker | Impact | Resolution Path |
|---|---|---|
| **Ephemeral media storage** — `FILESYSTEM_DISK=local` on Render free tier | Media uploads and AI-generated files lost on restart/redeploy | Install `league/flysystem-aws-s3-v3`, configure S3-compatible free storage (e.g. Cloudflare R2, Supabase Storage); **NOT in scope for initial staging deployment** |
| **Queue continuity during sleep windows** — ops cycle not running 22:00–08:00 UTC | Jobs queued in sleep window not processed until next duty cycle | Accepted staging limitation; no data loss (jobs persist in TiDB) |

### NEEDS VERIFICATION ⚠️

| Item | Action Required |
|---|---|
| **Flutter app staging build** | Rebuild `mobile/` with `--dart-define=API_BASE_URL=https://<app>.onrender.com/api/v1` before mobile testing against staging |
| **Social OAuth redirect URIs** | Update all five platform OAuth app configurations to use Render staging URLs before publishing features can be tested |
| **External cron service setup** | Configure UptimeRobot (or equivalent free-tier cron) with correct URL, method, and Bearer token header |
| **`APP_KEY` generation** | Must be generated fresh: `php artisan key:generate --show` — do not reuse local development key |
| **Internal tokens generation** | `AI_ENGINE_INTERNAL_TOKEN`, `TREND_ENGINE_INTERNAL_TOKEN`, `PUBLISHING_INTERNAL_TOKEN`, `INTERNAL_CRON_SECRET` must each be freshly generated with `openssl rand -hex 32` |
| **TiDB staging app credentials** | Verify `tidb_staging_app` account credentials are available and that the account has SELECT/INSERT/UPDATE/DELETE grants (but no DDL) on `socialpilot_staging` |

---

## 8. FINAL STATUS

**Result: B — Blockers found.**

The application architecture is **deployment-ready for a first staging verification**. All runtime components are correctly configured for Render free tier. The two blockers (ephemeral storage, queue sleep continuity) are **accepted and documented** as known staging limitations — they do not prevent deployment or core functionality verification.

The following blockers must be **acknowledged, not resolved**, before deployment can be authorized:

1. **Ephemeral local storage** — media files will be lost on container restart. This is acceptable for staging.
2. **Queue continuity during sleep** — jobs queued outside duty cycle hours will be delayed. This is acceptable for staging.

Deployment authorization is **not granted by this report**. Explicit authorization must be provided by the user in the next stage.

---

*This report was produced by read-only inspection. No files were modified, no migrations executed, no deployments performed, and no secrets were accessed or recorded.*
