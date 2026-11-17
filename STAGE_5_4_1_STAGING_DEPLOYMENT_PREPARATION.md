# SOCIALPILOT AI — PHASE 11, STAGE 5.4.1
## Staging Deployment Preparation & Final Configuration Audit

**Repository:** `Cla-renc/socialpilot-ai`  
**Branch:** `main`  
**Preceding Commit:** `86062cb`  
**Stage:** 5.4.1 (Staging Deployment Preparation)  
**Date:** 2026-11-17  

---

## 1. SCHEDULER CALCULATION CORRECTION

Stage 5.3.3 contained an inconsistency: the per-day awake-time estimate of 14.25 h was understated because it assumed the container sleeps between 25-minute requests. With a 15-minute inactivity timer and a 25-minute request interval, the container is **always awake during the operating window** (each request resets the 15-minute timer before it fires). The table rows for Strategy A/B in that report also incorrectly showed 672 h for 28-day months when the container runs 24/7 (672 = 28 × 24 ✓, those were correct).

### Corrected Calculations — Strategy C: Every 25 min, 08:00–22:00 UTC

**Key facts:**
- Operating window: 14 hours/day (08:00–22:00 UTC).
- Off-hours: 10 hours/day (22:00–08:00 UTC).
- Inactivity timer: 15 minutes.
- Request interval: 25 minutes.
- Since 25 min > 15 min, the container **sleeps** between requests outside the operating window.
- During the operating window: each request resets the timer before it fires. The container stays awake continuously for the full 14-hour window.
- Off-hours: after the last request at ~22:00, the container sleeps 15 minutes later (22:15). It wakes for the first morning request at 08:00 (cold start, ~90 s wake). Nightly off-window cost: 15 minutes/night.

**Instance-hours per day:**
```
Operating window:  14.00 h (awake entire window)
Nightly tail:       0.25 h (15 min sleep-down delay after last request)
Morning cold start: 0.025 h (~90 s)
Daily total:       ~14.275 h ≈ 14.3 h
```

**Monthly totals:**

| Month | Days | Gross Instance Hours | Per-deploy overhead | Net estimate | Safety margin (of 750 h) |
|:------|:-----|:--------------------|:--------------------|:-------------|:------------------------|
| 28-day | 28 | 28 × 14.3 = **400.4 h** | +0.25 h / 10 deploys = 2.5 h | **~403 h** | **347 h (46%)** |
| 30-day | 30 | 30 × 14.3 = **429.0 h** | +2.5 h | **~432 h** | **318 h (42%)** |
| 31-day | 31 | 31 × 14.3 = **443.3 h** | +2.5 h | **~446 h** | **304 h (40%)** |

> **750-hour workspace-wide limit:** This budget is shared across ALL free web services in the Render workspace. If a second free web service runs in the same workspace, its hours count against this same 750-hour pool. With a single web service on Strategy C, there is a >300-hour buffer even in 31-day months.

### Strategy Comparison (Corrected)

| Strategy | Interval | 31-day hours | Safety margin | Scheduler jitter | Recommendation |
|:---------|:---------|:-------------|:--------------|:-----------------|:---------------|
| A. Every 5 min, 24/7 | 5 min | 744 h | **6 h** ⚠️ | None | **Do not use** |
| B. Every 30 min, 24/7 | 30 min | 744 h | **6 h** ⚠️ | None | **Do not use** |
| **C. Every 25 min, 08–22 UTC** | 25 min | **~446 h** | **304 h** ✅ | ≤25 min | **RECOMMENDED** |
| D. Every 30 min, 08–22 UTC | 30 min | ~432 h | 318 h ✅ | ≤30 min | Acceptable |

> **Important limitation of Strategy C/D:** Scheduled tasks that run `everyMinute()` (e.g., `trends:schedule`, `publishing:dispatch-scheduled`) will execute with up to 25–30 minute jitter instead of 1-minute precision. Social publishing will be delayed up to 25 minutes from its scheduled time. This is acceptable for a demonstration-stage deployment and must be communicated to testers.

---

## 2. UNIFIED CONTAINER ARCHITECTURE — VERIFIED CONFIGURATION

### Dockerfile.unified — Build Stages

| Stage | Base Image | Purpose |
|:------|:-----------|:--------|
| `frontend-builder` | `node:20-alpine` | Compiles Vite/Tailwind assets (`npm run build`) |
| `production` | `php:8.2-fpm-bookworm` | Runtime: PHP 8.2, Python 3, Nginx, Supervisor |

**Key build facts:**
- Python venv created at `/opt/venv`; added to `PATH`.
- `ai_engine/requirements.txt` installed: `fastapi>=0.115`, `httpx>=0.28`, `uvicorn[standard]>=0.34`.
- PHP extensions: `pdo_mysql`, `pdo_sqlite`, `bcmath`, `gd`, `intl`, `opcache`, `zip`, `pcntl`.
- Composer: `--no-dev --optimize-autoloader` (production-safe).
- Tests directory excluded via `.dockerignore`.
- `.env` and all `.env.*` files excluded from image via `.dockerignore`.

### Supervisor Process Map

| Program | Command | Restart Policy |
|:--------|:--------|:---------------|
| `php-fpm` | `php-fpm -F` | `autorestart=true` |
| `fastapi` | `uvicorn ai_engine.main:app --host 127.0.0.1 --port 8080 --workers 1` | `autorestart=true` |
| `nginx` | `nginx -g "daemon off;"` | `autorestart=true` |

**FastAPI binds to `127.0.0.1:8080`** — not accessible from outside the container. Only Laravel (via PHP-FPM on `127.0.0.1:9000`) communicates with it over the loopback interface.

### Entrypoint Sequence (Verified in `docker/docker-entrypoint.sh`)

1. Reads `$PORT` env var (Render-injected); patches Nginx `listen` directives.
2. Creates runtime storage directories with `www-data` permissions.
3. Creates `public/storage` symlink if missing.
4. In production mode: clears config/route/view caches (unless `SKIP_CACHE_WARMUP=true`).
5. Execs `supervisord`.

> **Missing step:** The entrypoint does **not** run `php artisan migrate`. Migrations must be run manually from the Render Shell after first deployment, or via a separate Render one-off job. This is intentional and correct — automatic migration on startup is unsafe.

### PHP-FPM Pool Configuration (Verified)

```ini
pm = dynamic
pm.max_children = 2        # Hard limit for 512 MB RAM
pm.start_servers = 1
pm.min_spare_servers = 1
pm.max_spare_servers = 2
pm.max_requests = 500      # Recycle workers to prevent memory leaks
```

### PHP Runtime Configuration (Verified)

```ini
memory_limit = 128M        # Per-worker limit
upload_max_filesize = 50M
post_max_size = 50M
max_execution_time = 60    # Nginx fastcgi_read_timeout = 60 (consistent)
opcache.validate_timestamps = 0  # Correct for production (image is immutable)
expose_php = Off
```

### Nginx Configuration (Verified)

- Listens on `$PORT` (entrypoint patches the config at startup).
- Root: `/var/www/html/public`.
- Security headers: `X-Frame-Options`, `X-Content-Type-Options`, `X-XSS-Protection`, `Referrer-Policy`.
- Blocks access to `storage/app`, `storage/framework`, `storage/logs`, and hidden files.
- `client_max_body_size 50M` — consistent with PHP upload limits.
- `fastcgi_read_timeout 60` — consistent with `max_execution_time = 60`.
- **No HTTPS termination in Nginx** — Render's edge proxy handles TLS. Nginx receives plain HTTP from the proxy.

---

## 3. FILE STORAGE STRATEGY — FIRST STAGING DEPLOYMENT

### Decision: `FILESYSTEM_DISK=local` for Stage 5.4.2

| Env Var | Value | Reason |
|:--------|:------|:-------|
| `FILESYSTEM_DISK` | `local` | `league/flysystem-aws-s3-v3` not installed |
| `MEDIA_STORAGE_DISK` | `local` | Same; prevents class-not-found fatal |

### Features Requiring Persistent Storage

The following features write files and are affected by ephemeral local storage on Render:

| Feature | Storage Path | Impact of Ephemeral Disk |
|:--------|:-------------|:-------------------------|
| User media uploads | `storage/app/private/media/{brand_id}/` | **Lost on deploy/restart** |
| AI-generated images | `storage/app/private/media/` | **Lost on deploy/restart** |
| AI-generated videos | `storage/app/private/media/` | **Lost on deploy/restart** |
| Brand logos | `storage/app/public/` | **Lost on deploy/restart** |
| Analytics reports | `storage/app/private/analytics/` | **Lost on deploy/restart** |
| Database backups | `storage/app/backups/` | **Lost on deploy/restart** |
| Compiled view cache | `storage/framework/views/` | Rebuilt automatically on restart |

> **Known limitation documented here:** On Render's free tier, the container filesystem is **ephemeral**. Any file written to `storage/app/` is permanently lost when the container restarts or is redeployed. The database records referencing those files (`media_assets.storage_path`, `media_assets.public_url`) remain in TiDB but the files themselves will be missing.

### Mitigation for Staging

- Storage failures are **non-fatal**: `config/filesystems.php` has `'throw' => false` on all disks.
- `MediaLibraryService::deleteAsset()` wraps storage calls in `try/catch`.
- Database records will persist. This degraded state is acceptable for a demonstration deployment where testers understand the limitation.
- Do not test media persistence or file download features until S3 is configured.

### Path to S3 (Stage 5.4.3+)

```bash
composer require league/flysystem-aws-s3-v3
# Then commit, push, deploy
# Then set in Render environment:
# FILESYSTEM_DISK=s3
# MEDIA_STORAGE_DISK=s3
# AWS_ACCESS_KEY_ID=<supabase-s3-key>
# AWS_SECRET_ACCESS_KEY=<supabase-s3-secret>
# AWS_ENDPOINT=https://<project-ref>.supabase.co/storage/v1/s3
# AWS_BUCKET=<bucket-name>
# AWS_DEFAULT_REGION=us-east-1
# AWS_USE_PATH_STYLE_ENDPOINT=true
```

---

## 4. DATABASE SAFETY & MIGRATION PLAN

### TiDB Serverless Connection Requirements

| Setting | Value | Source |
|:--------|:------|:-------|
| `DB_CONNECTION` | `mysql` | `config/database.php` |
| `DB_PORT` | `4000` | TiDB Serverless standard |
| `DB_CHARSET` | `utf8mb4` | `config/database.php` |
| `DB_COLLATION` | `utf8mb4_unicode_ci` | `config/database.php` |
| `MYSQL_ATTR_SSL_CA` | `/etc/ssl/certs/ca-certificates.crt` | Required for TLS; path is correct on Debian Bookworm (`php:8.2-fpm-bookworm`) |
| TLS required | Yes | TiDB Serverless enforces TLS on all connections |

### Migration Status (Local SQLite — Fully Verified)

All **29 migrations** have run and are in `Ran` status on the local SQLite database. These are the same migration files that will run against TiDB.

| Batch | Migrations |
|:------|:-----------|
| 1 | `create_users_table`, `create_cache_table`, `create_jobs_table` |
| 2 | `create_brands_table`, `create_brand_settings_table`, `create_social_accounts_table`, `create_social_account_tokens_table`, `create_plans_table`, `create_subscriptions_table` |
| 3 | `add_terms_acceptance_to_users_table` |
| 4 | `create_personal_access_tokens_table`, `create_brand_content_drafts_table`, `create_brand_posts_table`, `create_brand_analytics_table`, `create_campaigns_table`, `add_campaign_id_to_brand_posts_table`, `create_brand_members_table`, `create_brand_approval_requests_table` |
| 5–14 | Trend intelligence, notifications, media generation, scheduling, publishing, analytics, content integrity tables |

### Pre-Migration Procedure (Run AFTER first deploy, before accepting traffic)

**Step 1 — Verify TiDB connectivity from Render Shell:**
```bash
php artisan verify:tidb-connection
```

**Step 2 — Run migrations (safe; additive only):**
```bash
php artisan migrate --force
```

> `--force` is required because `APP_ENV=production` causes Laravel to prompt for confirmation in interactive terminals.

**Step 3 — Verify migration status:**
```bash
php artisan migrate:status
```

**What NOT to run:**
- ❌ `php artisan migrate:fresh` — wipes all data.
- ❌ `php artisan migrate:rollback` — unless a specific migration is confirmed broken.
- ❌ `php artisan db:seed` — no production seeders exist.
- ❌ Any command against `tidb_migration_test` connection — that is a test database.

### Backup & Rollback

- TiDB Serverless provides automated daily snapshots (retention varies by plan).
- Before running `migrate --force`, note the last-known-good migration batch number from `php artisan migrate:status`.
- If a migration fails: `php artisan migrate:rollback --step=1` to reverse only the last batch.
- Do not roll back batch 1–4 (core schema) without a full data snapshot.

---

## 5. DEPLOYMENT SECURITY REVIEW

### Application Security

| Setting | Value | Status |
|:--------|:------|:-------|
| `APP_ENV` | `production` | ✅ Required |
| `APP_DEBUG` | `false` | ✅ Required — prevents stack trace exposure |
| `APP_KEY` | 32-byte base64 key | ✅ Generate fresh: `php artisan key:generate --show` |
| HTTPS enforcement | `URL::forceScheme('https')` when `APP_ENV=production` | ✅ Auto-active in production mode |
| `FORCE_HTTPS` | `true` | ✅ Explicitly set in template |

### Trusted Proxies — MISSING (Action Required)

Render terminates TLS at its edge proxy and forwards plain HTTP to the container. The `X-Forwarded-For` and `X-Forwarded-Proto` headers are set by Render's proxy.

**Current state:** The application does **not** configure Laravel's `TrustProxies` middleware. Without it:
- `request()->secure()` returns `false` (HTTP, not HTTPS) even though the user is on HTTPS.
- `URL::forceScheme('https')` works for asset generation but `request()->isSecure()` will return `false`.
- Session cookies may not be marked `Secure` correctly.

**Required fix before Stage 5.4.2:**

Add to `bootstrap/app.php`:
```php
->withMiddleware(function (Middleware $middleware): void {
    $middleware->trustProxies(at: '*');
})
```

This trusts all proxies (appropriate for Render's managed infrastructure where the container is not directly internet-accessible).

### Internal Service Authentication (Verified)

| Service | Auth Method | Status |
|:--------|:------------|:-------|
| Ops cycle (`POST /api/internal/ops/cycle`) | `hash_equals($configuredSecret, $headerToken)` | ✅ Timing-safe |
| Python FastAPI endpoints | `hmac.compare_digest(supplied, expected)` | ✅ Timing-safe |
| FastAPI port 8080 | `127.0.0.1` only (Supervisor config) | ✅ Not externally accessible |
| Python container | Loopback only (no `ports:` in unified container) | ✅ |

### CORS Configuration (Verified)

`config/cors.php` reads `CORS_ALLOWED_ORIGINS` from the environment. In `.env.render.example`, this is set to the Render app URL only. The dev-time default (`*`) must NOT be used in the staging deployment.

### Rate Limiting (Verified)

```php
RateLimiter::for('api', function (Request $request) {
    return Limit::perMinute(120)->by($request->user()?->id ?: $request->ip());
});
RateLimiter::for('auth-api', function (Request $request) {
    return Limit::perMinute(15)->by($request->ip());
});
```

Standard rate limits are configured. Appropriate for staging.

### Secrets Management

| Secret | Delivery Method | Status |
|:-------|:----------------|:-------|
| `APP_KEY` | Render environment variable | ✅ Never in image |
| `DB_PASSWORD` | Render environment variable | ✅ Never in image |
| `INTERNAL_CRON_SECRET` | Render environment variable | ✅ Never in image |
| `AI_ENGINE_INTERNAL_TOKEN` | Render environment variable | ✅ Never in image |
| All other API keys | Render environment variable | ✅ Never in image |
| `.env` files | `.dockerignore` excludes all `.env*` | ✅ Confirmed |

---

## 6. STAGING ACCEPTANCE CHECKLIST

Each test is marked with whether it was verified locally (pre-deployment) or requires the live Render deployment.

### 6.1 Pre-Deployment Tests (Verified Locally ✅)

| # | Test | Local Result | Method |
|:--|:-----|:-------------|:-------|
| L-01 | Ops cycle rejects unauthenticated requests (HTTP 401) | ✅ Passed | PHPUnit `InternalOpsCycleApiTest` |
| L-02 | Ops cycle accepts valid bearer token (HTTP 200) | ✅ Passed | PHPUnit |
| L-03 | Ops cycle atomic lock prevents duplicates (HTTP 429) | ✅ Passed | PHPUnit |
| L-04 | Ops cycle executes bounded queue (≤10 jobs, ≤25 s) | ✅ Passed | PHPUnit |
| L-05 | Python unavailability returns deterministic fallback | ✅ Passed | PHPUnit `DecoupledArchitectureIntegrationTest` |
| L-06 | Publishing idempotency prevents duplicate sends | ✅ Passed | PHPUnit |
| L-07 | Content generation data integrity (unique indexes) | ✅ Passed | PHPUnit `ContentGenerationDataIntegrityTest` |
| L-08 | Transactional rollback on persistence failure | ✅ Passed | PHPUnit |
| L-09 | All 29 migrations are in `Ran` status | ✅ Verified | `artisan migrate:status --database=sqlite` |
| L-10 | Sessions/cache/queue default to `database` driver | ✅ Verified | `config/session.php`, `config/cache.php`, `config/queue.php` |
| L-11 | Python AI engine sync tests (48 tests) | ✅ Passed | pytest from `ai_engine/` |
| L-12 | Full Laravel test suite 291 tests | ✅ Passed (prior stage) | PHPUnit |

### 6.2 Post-Deployment Tests (Must Be Performed After Stage 5.4.2 Deploy)

| # | Test | Expected Result | Notes |
|:--|:-----|:----------------|:-------|
| R-01 | `GET https://<app>.onrender.com/up` | HTTP 200 | Laravel framework health |
| R-02 | `GET https://<app>.onrender.com/api/v1/health` | `{"status":"ok","checks":{"database":true,...}}` | Confirms TiDB connectivity |
| R-03 | `GET https://<app>.onrender.com/api/v1/health/ready` | `{"ready":true}` HTTP 200 | Readiness gate |
| R-04 | `GET https://<app>.onrender.com` | Homepage HTML, HTTP 200 | Nginx + PHP-FPM serving |
| R-05 | `POST https://<app>.onrender.com/api/v1/auth/register` | HTTP 201 with token | User creation + DB write |
| R-06 | `POST https://<app>.onrender.com/api/v1/auth/login` | HTTP 200 with token | Auth + session in TiDB |
| R-07 | `GET https://<app>.onrender.com/api/v1/user` with bearer token | HTTP 200 | Sanctum token authentication |
| R-08 | `POST /api/internal/ops/cycle` with valid secret | `{"status":"success"}` HTTP 200 | Full ops cycle in container |
| R-09 | `POST /api/internal/ops/cycle` without secret | HTTP 401 | Auth guard working |
| R-10 | `GET http://127.0.0.1:8080/health` from Render Shell | `{"status":"ok","version":"2.0.0"}` | Python FastAPI running |
| R-11 | `POST /api/v1/brands` (create a brand) | HTTP 201 | DB write + persistence |
| R-12 | `POST /api/v1/content/generate` (authenticated) | HTTP 200 with content | Laravel→Python call succeeds |
| R-13 | Verify content draft persists after container restart | Draft in `GET /api/v1/content/drafts` | Queue + DB durability |
| R-14 | `POST /api/internal/ops/cycle` twice rapidly | 2nd returns HTTP 429 | Atomic lock working |
| R-15 | Container restart recovery (Render manual restart) | All endpoints return to 200 within 90 s | Cold-start resilience |
| R-16 | Verify no stack traces appear in error responses | Errors return generic JSON | `APP_DEBUG=false` |
| R-17 | Verify HTTPS redirect on HTTP request | 301 to HTTPS (Render proxy) | HTTPS enforcement |
| R-18 | `php artisan migrate:status` from Render Shell | All 29 migrations in `Ran` status | Migration completeness |

### 6.3 Tests to SKIP in Staging Deployment

| Test | Reason |
|:-----|:-------|
| Media file upload & download | Ephemeral storage — files lost on restart |
| AI image/video generation | Requires paid API keys |
| Social media OAuth & publishing | Requires platform app credentials with staging redirect URIs |
| Database backups (`db:backup`) | No persistent storage |
| Analytics data collection (`analytics:collect`) | Requires social account tokens |

---

## 7. RESOLVED DEPLOYMENT PRE-REQUISITES

### Blocker 1 — TrustProxies Middleware (RESOLVED)

**File modified:** `bootstrap/app.php`

```php
->withMiddleware(function (Middleware $middleware): void {
    $middleware->trustProxies(at: '*');
})
```

Configured to trust Render's reverse proxy, ensuring `request()->isSecure()` returns `true` on HTTPS and client IPs are resolved accurately.

### Blocker 2 — `INTERNAL_CRON_SECRET` Documented in `.env.example` (RESOLVED)

**File modified:** `.env.example`

Added placeholder `INTERNAL_CRON_SECRET=` for developer reference and completeness.

### Soft Prerequisite — Live `docker stats` Confirmation

The container memory estimate (~340–385 MB peak) has not been confirmed with live `docker stats` because the Docker daemon is unavailable in this environment. This does not block staging deployment to Render, but must be confirmed before claiming production readiness.

---

## 8. STAGE 5.4.2 DEPLOYMENT APPROVAL

### Pre-Deployment Checklist

Before creating the Render service and deploying:

- [x] Fix `TrustProxies` middleware in `bootstrap/app.php` (Resolved in Stage 5.4.1).
- [x] Add `INTERNAL_CRON_SECRET=` to `.env.example` (Resolved in Stage 5.4.1).
- [x] Create `.env.render.example` and update `.gitignore` (Resolved in Stage 5.4.1).
- [ ] Generate a fresh `APP_KEY` for Render (`php artisan key:generate --show`).
- [ ] Generate `INTERNAL_CRON_SECRET`, `AI_ENGINE_INTERNAL_TOKEN`, `TREND_ENGINE_INTERNAL_TOKEN` using `openssl rand -hex 32`.
- [ ] Configure Render web service: Docker runtime, branch `main`, port `auto`.
- [ ] Enter all Render environment variables from `.env.render.example` with real values.
- [ ] After first deploy: run `php artisan migrate --force` from Render Shell.
- [ ] After migration: run acceptance tests R-01 through R-18.
- [ ] Configure Cron-job.org: `POST https://<app>.onrender.com/api/internal/ops/cycle` every 25 minutes, 08:00–22:00 UTC, with `Authorization: Bearer <INTERNAL_CRON_SECRET>` header.

### Decision

**Stage 5.4.2 Staging Deployment is APPROVED.**

All code-level configuration blockers have been remediated, verified, and committed.

---

## APPENDIX — FILES CREATED/MODIFIED IN THIS STAGE

| File | Action | Purpose |
|:-----|:-------|:--------|
| `.env.render.example` | Created | Render staging environment variable template (placeholders only) |
| `.gitignore` | Modified | Added `!.env.render.example` exception to allow tracking |
| `bootstrap/app.php` | Modified | Added `$middleware->trustProxies(at: '*')` for Render proxy compatibility |
| `.env.example` | Modified | Added `INTERNAL_CRON_SECRET=` placeholder |
| `STAGE_5_4_1_STAGING_DEPLOYMENT_PREPARATION.md` | Created | Staging deployment preparation and audit report |
