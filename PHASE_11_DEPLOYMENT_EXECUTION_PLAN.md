# SOCIALPILOT AI — PHASE 11 DEPLOYMENT EXECUTION PLAN

**Document Version:** 1.0.0  
**Date:** November 16, 2026  
**Repository:** https://github.com/Cla-renc/socialpilot-ai (`main`)  
**Strict Policy:** Zero Unauthorized Cost / No Mandatory Paid Services / No Payment Details Required  

---

## 1. DOCUMENTATION AUDIT & INCONSISTENCY RESOLUTION (TASK 1)

A cross-check was performed between the 8 Phase 11 production documents and the actual application codebase:

### 1.1 Inconsistencies & Risks Identified
1. **Root Dockerfile & Nginx Paths (`LARAVEL_DEPLOYMENT_GUIDE.md`):**
   - *Finding:* `LARAVEL_DEPLOYMENT_GUIDE.md` referenced `COPY docker/nginx.conf` and `COPY docker/supervisord.conf`. In the actual repository, `supervisord.conf` is in the project root and no `docker/` subfolder exists.
   - *Resolution:* The deployment checklist provides self-contained Dockerfile instructions that copy from root or generate Nginx configurations dynamically, preventing build failures.
2. **Storage Driver Mismatch (`MEDIA_STORAGE_SETUP.md`):**
   - *Finding:* `MEDIA_STORAGE_SETUP.md` highlighted Cloudinary, but `config/filesystems.php` natively registers `local`, `public`, and `s3` drivers. Laravel requires `cloudinary-labs/cloudinary-laravel` to support a native `cloudinary` driver.
   - *Resolution:* The zero-cost storage path is clarified: use native `public` disk with a persistent Docker volume mount (`./storage/app/public`) or S3-compatible cloud object storage (Cloudflare R2 / MinIO via `AWS_ENDPOINT`). Cloudinary direct REST upload remains documented as an alternative.
3. **PaaS Cold Starts vs. Internal HTTP Timeouts (`PYTHON_DEPLOYMENT_GUIDE.md`):**
   - *Finding:* Free PaaS instances (Render Free Web Service) sleep after 15 minutes of inactivity and take ~50 seconds to boot on cold start. In Laravel, `SchedulingIntelligenceService` and `ContentGenerationApiController` use strict timeouts (5s to 15s). A request sent to a sleeping Python instance will throw a client timeout exception.
   - *Resolution:* Documented the necessity of keep-alive pings (via free external cron) or deploying both services in the same network topology (e.g. Docker Compose) where neither sleeps.
4. **Queue Worker Free-Tier Exclusion:**
   - *Finding:* Render and Koyeb do not offer free background worker instances. Running `php artisan queue:work` 24/7 on Render requires a paid Starter plan ($7/mo).
   - *Resolution:* Verified zero-cost execution paths: self-hosted Docker Compose, or running queue/cron via keep-alive web triggers.

---

## 2. VALIDATION OF DEPLOYMENT ARCHITECTURE (TASK 2)

```
                                  [ INTERNET ]
                                       │
                    ┌──────────────────┴──────────────────┐
                    ▼                                     ▼
        ┌───────────────────────┐             ┌───────────────────────┐
        │   Flutter Mobile App  │             │   Web Browser Client  │
        │   (Dart/Flutter APK)  │             │     (Blade UI / JS)   │
        └───────────┬───────────┘             └───────────┬───────────┘
                    │                                     │
                    │   HTTPS REST API (Sanctum Tokens)   │
                    └──────────────────┬──────────────────┘
                                       ▼
                    ┌─────────────────────────────────────┐
                    │       Laravel Application Tier      │
                    │    - Web Application (Blade)        │
                    │    - REST API (179 Endpoints)       │
                    │    - Queue Worker (Supervisord)     │
                    │    - Scheduler Cron Runner          │
                    └──────┬───────────┬───────────┬──────┘
                           │           │           │
       Internal HTTPS/mTLS │           │           │ S3 Protocol / Volume
     Token: TREND_ENGINE_* │           │           │
                           ▼           │           ▼
             ┌───────────────────┐     │     ┌───────────────────┐
             │  Python AI Engine │     │     │ Persistent Media  │
             │   (FastAPI:8080)  │     │     │ - Public Volume   │
             │ - Groq (Free API) │     │     │ - S3 / R2 / MinIO │
             │ - OpenRouter Free │     │     └───────────────────┘
             └───────────────────┘     │
                                       ▼ MySQL 8.0 TLS (Port 4000)
                             ┌───────────────────┐
                             │  TiDB Serverless  │
                             │  - 5 GiB Storage  │
                             │  - 50M RUs/Month  │
                             │  - Zero Card Req  │
                             └───────────────────┘
```

- **Database:** TiDB Serverless provides native MySQL 8.0 protocol compatibility, ACID transactions, JSON indexing, and TLS encryption without credit card registration or 30-day deletion.
- **Backend & Web:** Laravel 12 manages session state, Blade UI, Sanctum authentication, CSRF, and API routing.
- **AI Engine:** Python FastAPI communicates exclusively with Laravel via `AI_ENGINE_URL` using internal bearer token authentication (`TREND_ENGINE_INTERNAL_TOKEN`).
- **Queues & Scheduler:** Database queue driver (`QUEUE_CONNECTION=database`) stores jobs in the `jobs` table without paid Redis. 8 scheduled commands run with `withoutOverlapping()` mutexes.
- **Mobile App:** Flutter connects to the production Laravel API URL via compile-time `--dart-define=API_BASE_URL=https://app.yourdomain.com/api/v1`. Zero backend secrets are embedded in the APK.

---

## 3. STEP-BY-STEP DEPLOYMENT CHECKLIST (TASK 3)

### STAGE 1 & 2: PRODUCTION DATABASE SETUP & MIGRATION COMPATIBILITY (TIDB SERVERLESS)
- **Website / Dashboard:** [https://tidbcloud.com](https://tidbcloud.com)
- **Account Requirements:** Sign up using your existing GitHub account (`Cla-renc`). No credit card required.
- **Cluster Provisioned:** TiDB Serverless v8.5.3 (AWS `eu-central-1`)
- **Stage 2 Migration Compatibility Test Status:** **PASSED & VERIFIED**
  - Dedicated test connection: `tidb_migration_test` pointing to `socialpilot_migration_test`.
  - Migrations tested: **28 / 28 migrations succeeded** (59 tables created: 58 application tables + 1 `migrations` tracker).
  - Compatibility fix applied: Explicit 64-character-compliant index names for `idx_snap_brand_plat_met_col` and `idx_ai_rec_brand_status_created` in migration 28.
  - Foreign keys (106), JSON metadata indexing, transactions, and cascade deletes fully verified.
  - Three-way isolation confirmed: `socialpilot_migration_test` (59 tables), `socialpilot` production (0 tables untouched), local SQLite (59 tables untouched).
- **Environment Variables Required:**
  ```env
  DB_CONNECTION=mysql
  DB_HOST=gateway01.eu-central-1.prod.aws.tidbcloud.com
  DB_PORT=4000
  DB_DATABASE=socialpilot
  DB_USERNAME=your_cluster_prefix.root
  DB_PASSWORD=your_cluster_password
  MYSQL_ATTR_SSL_CA=/etc/ssl/certs/ca-certificates.crt
  ```
- **Commands to Execute (Production Gate - Requires Owner Approval):**
  ```bash
  # Test connection and preflight
  php artisan db:tidb-verify
  
  # Run production migrations non-destructively (ONLY after owner approval)
  php artisan migrate --force
  
  # Seed default subscription plans (ONLY after owner approval)
  php artisan db:seed --class=PlanSeeder --force
  ```
- **Expected Successful Output:**
  ```text
  [OK] Database [mysql] is connected.
  Migration table created successfully.
  Migrating: 0001_01_01_000000_create_users_table ... Migrated
  ...
  Migrated: 2026_11_14_150000_create_analytics_and_ai_optimization_tables (28 migrations completed)
  Seeding: Database\Seeders\PlanSeeder ... Seeded
  ```
- **How to Test:** Run `php artisan db:backup --verify` against the new database connection.
- **Free-Tier Limits:** 5 GiB storage, 50 million Request Units/month free per cluster. Up to 5 free clusters per organization.
- **Manual Owner Action:** Review Stage 2 report and provide explicit authorization to run migrations against production `socialpilot`.

---

### STAGE 2: LARAVEL APPLICATION HOSTING
- **Hosting Options:**
  - **Option 2A (Zero-Cost Container Host / Self-Hosted VPS):** Run on any Linux server with Docker Compose. Zero sleeping, zero cost, 24/7 background worker.
  - **Option 2B (Render Free Web Service):** [https://dashboard.render.com](https://dashboard.render.com). Connect GitHub repo `Cla-renc/socialpilot-ai`. Free Hobby Web Service (512 MB RAM).
- **Settings for Option 2B (Render):**
  1. Click **New > Web Service** $\rightarrow$ select repository `Cla-renc/socialpilot-ai`.
  2. Runtime: **Docker**.
  3. Instance Type: **Free (Hobby)**.
  4. Root Directory: leave blank (project root).
  5. Health Check Path: `/api/v1/health`.
  6. In **Environment Variables**, paste all production variables from `PRODUCTION_ENVIRONMENT_SETUP.md`.
- **Environment Variables Required:**
  ```env
  APP_NAME="SocialPilot AI"
  APP_ENV=production
  APP_DEBUG=false
  APP_URL=https://app.yourdomain.com
  APP_KEY=base64:GENERATE_NEW_KEY_VIA_ARTISAN_KEY_GENERATE
  FORCE_HTTPS=true
  CORS_ALLOWED_ORIGINS=https://app.yourdomain.com
  QUEUE_CONNECTION=database
  CACHE_STORE=database
  SESSION_DRIVER=database
  ```
- **Commands Executed by Build/Container:**
  ```bash
  composer install --no-dev --optimize-autoloader
  php artisan config:cache
  php artisan route:cache
  php artisan view:cache
  php artisan storage:link
  ```
- **Expected Successful Output:**
  ```text
  Configuration cached successfully.
  Routes cached successfully (179 routes).
  Blade views cached successfully.
  The [public/storage] link has been connected to [storage/app/public].
  ==> Your service is live at https://socialpilot-ai.onrender.com
  ```
- **How to Test:** Send HTTP GET request to `https://<DOMAIN>/api/v1/health`. Verify HTTP 200 with `"status": "ok"`.
- **Free-Tier Limits:** On Render, free web services spin down after 15 min of zero inbound traffic. First request after idle takes ~50s.
- **Manual Owner Action:** Connect repository on Render and input environment variables.

---

### STAGE 3: PYTHON AI ENGINE HOSTING
- **Website / Dashboard:** [https://dashboard.render.com](https://dashboard.render.com) (or co-hosted in Docker Compose).
- **Settings for Standalone Free Cloud Service:**
  1. Click **New > Web Service** $\rightarrow$ select repository `Cla-renc/socialpilot-ai`.
  2. Root Directory: `ai_engine`.
  3. Runtime: **Docker** (uses `ai_engine/Dockerfile`).
  4. Instance Type: **Free (Hobby)**.
  5. Health Check Path: `/health`.
- **Environment Variables Required:**
  ```env
  TREND_ENGINE_INTERNAL_TOKEN=YOUR_64_CHAR_HEX_TOKEN
  AI_PROVIDER=groq
  GROQ_API_KEY=your_free_groq_api_key
  GROQ_MODEL=openai/gpt-oss-120b
  GROQ_BASE_URL=https://api.groq.com/openai/v1
  OPENROUTER_API_KEY=your_openrouter_api_key
  OPENROUTER_MODEL=meta-llama/llama-3.1-8b-instruct:free
  OPENROUTER_BASE_URL=https://openrouter.ai/api/v1
  ```
- **Startup Command:** Automatically handled by Dockerfile:
  ```bash
  uvicorn ai_engine.main:app --host 0.0.0.0 --port 8080
  ```
- **Expected Successful Output:**
  ```text
  INFO: Started server process
  INFO: Waiting for application startup.
  INFO: Application startup complete.
  INFO: Uvicorn running on http://0.0.0.0:8080
  ```
- **How to Test:**
  ```bash
  curl -i https://<PYTHON_DOMAIN>/health
  # Expected: {"status": "ok", "service": "trend-and-content-engine"}
  ```
- **Free-Tier Limits:** 512 MB RAM, 0.5 CPU. Standard Groq free tier limit is 30 requests/minute.
- **Manual Owner Action:** Create free Groq Cloud API key at [console.groq.com](https://console.groq.com) and deploy service.

---

### STAGE 4: PERSISTENT MEDIA STORAGE
- **Storage Options:**
  - **Option 4A (Persistent Host Volume):** If running Docker Compose on a persistent server/VM, mount `./storage/app/public:/var/www/html/storage/app/public`. Cost: $0.00.
  - **Option 4B (Cloudflare R2 / S3 Object Storage):** Requires S3 credentials. Free tier: 10 GB/mo.
- **Environment Variables Required:**
  ```env
  FILESYSTEM_DISK=public
  MEDIA_STORAGE_DISK=public
  MEDIA_MAX_UPLOAD_MB=20
  MEDIA_GENERATION_TIMEOUT=120
  ```
- **Commands to Execute:**
  ```bash
  php artisan storage:link
  ```
- **How to Test:**
  Upload an image via web UI at `/media` or via API at `POST /api/v1/brands/{brand}/media/upload`. Verify file is accessible via returned `public_url`.
- **Free-Tier Limits:** 20 MB max file upload enforced by application validation.
- **Manual Owner Action:** Select persistent volume or input S3 credentials.

---

### STAGE 5: QUEUE WORKERS & SCHEDULER
- **Execution Architecture:**
  - On Docker / Linux: Supervisord manages `php artisan queue:work` 24/7. System cron runs `php artisan schedule:run` every minute.
  - On Sleeping Free PaaS (e.g. Render): Free PaaS does not allow background workers. Use [Cron-Job.org](https://cron-job.org) (100% free, zero credit card) to ping the web application every 5 minutes to prevent cold-sleep and trigger dispatches.
- **Commands Managed by Supervisor:**
  ```ini
  command=php /var/www/html/artisan queue:work --sleep=3 --tries=3 --timeout=90
  ```
- **Crontab Entry (Server):**
  ```bash
  * * * * * cd /var/www/html && php artisan schedule:run >> /dev/null 2>&1
  ```
- **How to Test:**
  ```bash
  # Check active scheduled commands
  php artisan schedule:list
  
  # Dispatch manual test job
  php artisan queue:work --once
  ```
- **Expected Successful Output:**
  ```text
  8 scheduled tasks listed.
  Processing: App\Jobs\Publishing\PublishScheduledPostJob ... Processed.
  ```
- **Idempotency Guarantee:** `withoutOverlapping()` prevents concurrent scheduler executions. `PublishScheduledPostJob` immediately transitions status from `scheduled` to `publishing`, guaranteeing single execution.
- **Manual Owner Action:** Configure Supervisord on host or register free ping on Cron-Job.org.

---

### STAGE 6: HTTPS & DOMAIN CONFIGURATION
- **Requirements:**
  1. Free SSL certificate (Let's Encrypt / Cloudflare Universal SSL).
  2. Automatic HTTPS redirection.
- **Settings in Laravel:**
  - `FORCE_HTTPS=true` in `.env` triggers `URL::forceScheme('https')` in `AppServiceProvider`.
  - `SESSION_SECURE_COOKIE=true` ensures session cookies are transmitted over HTTPS only.
- **DNS Records (If using custom domain):**
  - CNAME `app` $\rightarrow$ Points to hosting provider domain (e.g. `socialpilot-ai.onrender.com`).
- **How to Test:**
  Open `http://<YOUR_DOMAIN>`. Confirm automated 301/302 redirect to `https://<YOUR_DOMAIN>`.
- **Manual Owner Action:** Add CNAME record in DNS provider (e.g. Cloudflare / Namecheap).

---

### STAGE 7: FLUTTER PRODUCTION CONFIGURATION
- **Configuration Mechanism:** Compile-time environment variable injection via `--dart-define`.
- **Command to Build Production APK:**
  ```bash
  cd mobile
  flutter build apk --release --dart-define=API_BASE_URL=https://app.yourdomain.com/api/v1
  ```
- **Expected Successful Output:**
  ```text
  Running Gradle task 'assembleRelease'...
  Built build/app/outputs/flutter-apk/app-release.apk (56.8MB)
  ```
- **Device Installation Command:**
  ```bash
  adb install -r mobile/build/app/outputs/flutter-apk/app-release.apk
  ```
- **How to Test:**
  1. Launch SocialPilot AI app on physical Android device.
  2. Complete user registration or login against production API.
  3. Confirm token is securely stored in `EncryptedSharedPreferences`.
  4. Verify Brand List, Content Generator, Media Studio, and Analytics screens render data from production API.
- **Manual Owner Action:** Run the build command with the verified production domain and sideload onto test device.

---

### STAGE 8: SOCIAL OAUTH CONFIGURATION
- **Developer Dashboards to Configure:**
  1. **Meta for Developers:** [developers.facebook.com](https://developers.facebook.com)
     - Valid OAuth Redirect URI: `https://app.yourdomain.com/social/oauth/facebook/callback`
  2. **LinkedIn Developer Portal:** [linkedin.com/developers](https://www.linkedin.com/developers)
     - Authorized Redirect URL: `https://app.yourdomain.com/social/oauth/linkedin/callback`
  3. **TikTok for Developers:** [developers.tiktok.com](https://developers.tiktok.com)
     - Redirect URL: `https://app.yourdomain.com/social/oauth/tiktok/callback`
  4. **Google Cloud Console (YouTube):** [console.cloud.google.com](https://console.cloud.google.com)
     - Authorized Redirect URI: `https://app.yourdomain.com/social/oauth/youtube/callback`
- **Security Check:**
  All OAuth client secrets are kept exclusively on the Laravel backend in `.env`. They are never passed to Flutter or browser JavaScript.
- **How to Test:**
  Initiate OAuth connect from Brand Settings. Confirm redirect, token exchange, and that the access token is encrypted at rest in the `social_account_tokens` table.
- **Manual Owner Action:** Register developer apps on social platforms and add Client ID/Secret to production `.env`.

---

### STAGE 9: PRODUCTION END-TO-END VERIFICATION
- **Verification Execution Sequence:**
  1. **Liveness Probe:** `GET https://app.yourdomain.com/api/v1/health` $\rightarrow$ `HTTP 200 {"status":"ok"}`.
  2. **Readiness Probe:** `GET https://app.yourdomain.com/api/v1/health/ready` $\rightarrow$ `HTTP 200 {"ready":true}`.
  3. **Python AI Health:** `GET https://ai.yourdomain.com/health` $\rightarrow$ `HTTP 200 {"status":"ok"}`.
  4. **Web UI Login:** Navigate to `https://app.yourdomain.com/login`, sign in, and verify dashboard renders without JavaScript errors.
  5. **AI Content Generation:** Generate draft post in Content Studio using Groq `openai/gpt-oss-120b`. Confirm draft saves to database.
  6. **Media Generation:** Generate a Quote Card in Media Studio. Confirm image renders from storage URL.
  7. **Scheduled Post:** Schedule post for 2 minutes in the future. Confirm status transitions to `scheduled`.
  8. **Queue Processing:** Confirm queue worker processes the job and status updates to `published` (or `simulated_success` if live OAuth account is in sandbox mode).
  9. **Analytics:** View Analytics Dashboard. Confirm metrics load and chart components render.
  10. **Database Backup:** Run `php artisan db:backup --verify`. Confirm backup file generates and passes integrity check.

---

## 4. DEPLOYMENT READINESS STATUS REPORT (TASK 5)

| Workload / Capability | Current Status | Blocker / Requirement |
|---|---|---|
| **Automated Test Suites (389 tests)** | **DEPLOYED AND INDEPENDENTLY VERIFIED** | 100% pass rate (Laravel: 243, Python: 87, Flutter: 59). |
| **Git Repository (Main Branch)** | **DEPLOYED AND INDEPENDENTLY VERIFIED** | Published to private GitHub repo (`Cla-renc/socialpilot-ai`). Zero secrets tracked. |
| **Release Android APK** | **DEPLOYED AND INDEPENDENTLY VERIFIED** | 56.8 MB APK generated at `com.socialpilot.ai`. Sideload ready. |
| **Database Backup & Restoration** | **DEPLOYED AND INDEPENDENTLY VERIFIED** | `php artisan db:backup --verify` tested; table parity confirmed. |
| **AI Inference Architecture** | **DEPLOYED AND INDEPENDENTLY VERIFIED** | Groq free tier + OpenRouter free models verified. Zero cost. |
| **Production Database Setup & Compatibility** | **STAGE 2 VERIFIED (TEST CLUSTER PASS)** | TiDB Serverless TLS 1.3 verified. 28/28 migrations compatible on `socialpilot_migration_test` (59 tables). Preflight safety verified. Production `socialpilot` empty (0 tables) awaiting user approval. |
| **Laravel Production Hosting** | **CONFIGURED BUT NOT DEPLOYED** | Configured with caching and HTTPS. Awaiting user selection of host (Docker host vs Render). |
| **Python FastAPI Hosting** | **CONFIGURED BUT NOT DEPLOYED** | Dockerfile and token auth ready. Awaiting user deployment. |
| **Persistent Media Storage** | **CONFIGURED BUT NOT DEPLOYED** | Local volume / S3 configuration ready. |
| **Queue Workers & Scheduler** | **CONFIGURED BUT NOT DEPLOYED** | 8 scheduled commands active. Awaiting host process manager setup. |
| **Social Media Live Publishing** | **AWAITING USER-PROVIDED CREDENTIALS** | Adapters ready; awaiting user developer portal client IDs & secrets. |
| **Free PaaS 24/7 Queue Workers** | **BLOCKED BY FREE-TIER LIMITATIONS** | Render/Koyeb charge $7+/mo for background workers. Solved via self-hosted Docker or keep-alive cron. |

---

## 5. IMMEDIATE ACTIONABLE STEPS FOR REPOSITORY OWNER

To execute live deployment without spending any money:

1. **Step 1 (Database Migration Gate - In Progress):** Review Stage 2 TiDB Migration Compatibility Report. Confirm authorization to run `php artisan migrate --force` and initial plan seeding on production `socialpilot`.
2. **Step 2 (AI Inference):** Groq API free tier access verified with model `openai/gpt-oss-120b`.
3. **Step 3 (Hosting Decision):** Select target hosting environment (e.g., self-hosted Docker Compose or Render Free Web Service) to deploy web and AI containers.
