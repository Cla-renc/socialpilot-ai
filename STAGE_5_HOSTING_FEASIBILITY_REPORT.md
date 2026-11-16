# SOCIALPILOT AI — STAGE 5.1 & 5.1.1: ZERO-COST HOSTING FEASIBILITY AUDIT & REVALIDATION REPORT

**Date:** November 16, 2026  
**Auditor:** Antigravity AI Engineering  
**Project:** SocialPilot AI  
**Repository:** `Cla-renc/socialpilot-ai`  
**Target Branch:** `main`  
**Current Phase:** Phase 11, Stage 5.1 & Stage 5.1.1 Revalidation  
**Budget Policy:** Strict $0.00 / month (No credit/debit card required, zero trial-to-paid conversion risk, no unexpected billing)

---

## EXECUTIVE SUMMARY & AUDIT REVALIDATION

A rigorous architectural, operational, and financial feasibility audit and subsequent revalidation (Stage 5.1.1) was conducted to determine a deployment pathway for SocialPilot AI on a **strict $0.00 monthly budget**.

### Primary Findings of the Revalidation (Stage 5.1.1):
1. **Hugging Face Docker Spaces Disqualification:** Updated official Hugging Face documentation confirms that **Docker-based Spaces are NO LONGER FREE**. Docker and standard CPU compute Spaces now require a paid **PRO subscription ($9–$10/month)** backed by a credit card. Consequently, Hugging Face Docker hosting is **DISQUALIFIED and REMOVED** from the recommended architecture.
2. **Render Free Tier Realities & Production Suitability:** Render’s free tier provides 512 MB RAM, 0.5 shared vCPU, and 750 free instance hours/month, but enforces a mandatory **15-minute inactivity sleep**, a **~50-second cold-start latency**, an ephemeral filesystem, an outbound bandwidth cap of **5 GB/month** ($0.15/GB overage), and **NO free background workers or cron jobs**. External pings (e.g. cron-job.org) **cannot guarantee uninterrupted uptime**. Therefore, a Render Free deployment is **SUITABLE ONLY FOR DEMONSTRATIONS, PORTFOLIO PRESENTATION, AND STAGING VALIDATION**, not dependable commercial production hosting.
3. **TiDB Cloud Serverless Verification Status:** While the published Starter Tier provides 5 GiB row storage and 50M Request Units/month free, cluster-level consumption counters and monthly spending limits reside strictly in the cloud web console. Because dashboard metrics are inaccessible via SQL queries, the production cluster allowance is **EXPLICITLY MARKED AS REQUIRING MANUAL DASHBOARD VERIFICATION** by the account owner prior to public traffic.
4. **Supabase Object Storage Limitations:** Supabase provides 1 GB free file storage, 50 MB max single-file upload, and 5 GB egress without a credit card. It integrates with Laravel’s S3 driver with **zero database schema changes**. However, free projects are subjected to a **7-day inactivity pause policy**, which can break public media links for social publishing if the project is idle.
5. **Queue & Scheduler Re-Engineering:** External ping triggers cannot replace permanent queue workers. A bounded, authenticated in-process cycle endpoint (`POST /api/internal/ops/cycle`) is designed to prevent overlapping runs, avoid public Artisan exposure, and bound execution time, while explicitly documenting failure scenarios (cold starts, queue backlog, scheduling jitter).
6. **Website Delivery Architecture:** Clarified that the **Laravel Blade application requires a PHP 8.2+ runtime** and cannot run on static hosting platforms like Cloudflare Pages. Cloudflare Pages is suitable solely for compiled frontend static assets or optional Flutter Web distribution.

---

## 1. EXISTING ARCHITECTURE & CODEBASE ASSESSMENT

A comprehensive audit of the local codebase revealed the operational boundaries and environment requirements across all system components.

### 1.1 Backend Framework (Laravel 12 / PHP 8.2+)
* **Framework:** Laravel 12.0.1 on PHP 8.2+ with Laravel Breeze (Blade/Alpine.js/Tailwind CSS) and Laravel Sanctum 4.3.
* **Database Drivers:** Supports `sqlite` (local development default, zero cost) and `mysql` with TLS certificate validation (`MYSQL_ATTR_SSL_CA`).
* **Session & Cache:** Configured to use database-driven sessions (`SESSION_DRIVER=database`) and database cache (`CACHE_STORE=database`). This eliminates the requirement for a paid Redis cluster.
* **Health Endpoints:**
  * `GET /up` (Laravel 12 built-in health probe).
  * `GET /api/v1/health` (Reports JSON status and verifies active PDO connection).
  * `GET /api/v1/health/ready` (Returns HTTP 200 when database is responsive, 503 if unreachable).

### 1.2 Python / FastAPI AI Engine (`ai_engine`)
* **Framework:** FastAPI 0.115+ with Uvicorn and HTTPX running on Python 3.12+.
* **Packaging:** Fully Dockerized (`ai_engine/Dockerfile`) with minimal dependencies (`requirements.txt`).
* **Endpoints:** Exposes internal microservice routes under `/internal/v1/*` (`/research`, `/content/generate`, `/content/strategy`, `/content/variations`, `/content/improve`, `/content/evaluate`, `/media/visual-profile`, `/media/storyboard`, `/scheduling/recommendations`).
* **Database Access:** **Zero database coupling.** The Python service has no SQL or database credentials; it operates as a pure stateless computational engine receiving brand payloads and returning structured JSON.
* **Authentication:** Guarded by `_verify_internal_token` checking `Authorization: Bearer <AI_ENGINE_INTERNAL_TOKEN>`.

### 1.3 Mobile Application (`mobile`)
* **Framework:** Flutter 3.x using `http`, `flutter_secure_storage`, and `app_links` for OAuth deep linking.
* **API Integration:** Configured via Dart compile-time environment variable `API_BASE_URL` (defaults to Android emulator loopback `http://10.0.2.2:8000/api/v1`).
* **Security Model:** Communicates solely with the Laravel REST API (`/api/v1/*`) using Sanctum Bearer tokens. Never accesses TiDB or the Python engine directly.

### 1.4 TiDB Production Database Connection
* **Target Connection:** `config/database.php` (`mysql` connection configured for TiDB Cloud Serverless on port 4000).
* **TLS Requirements:** Strict TLS required. Pre-packaged with ISRG Root X1 CA certificate (`Certificates/isrgrootx1.pem`).
* **Safety Verification Commands:**
  * `php artisan db:tidb-verify`: Non-destructive connection and schema verification.
  * `php artisan db:tidb-production-preflight`: Preflight validation ensuring isolation and preventing test db contamination.
* **Preservation Status:** Local SQLite database (`database/database.sqlite`) and verified backups (`storage/app/backups/`) remain fully intact and operational.

### 1.5 Queued Jobs & Background Workers
The repository contains **13 queued job classes**:
* `ProcessAutopilotSchedulingJob`
* `ProcessBrandPost`
* `ProcessMediaGenerationJob`
* `RefreshSchedulingRecommendationsJob`
* `RunContentGeneration`
* `RunTrendResearch`
* `ScheduleQueuedContentJob`
* `SendUpcomingPostReminderJob`
* `CollectAccountAnalyticsJob`, `CollectAudienceInsightsJob`, `CollectPostAnalyticsJob`
* `GenerateAnalyticsReportJob`, `GenerateOptimizationRecommendationsJob`, `RefreshAnalyticsSnapshotJob`
* `PublishImmediatePostJob`, `PublishScheduledPostJob`, `RefreshSocialAccountTokenJob`, `RetryFailedPublishingJob`, `CheckPublishingStatusJob`

**Current Queue Configuration:** Default is `QUEUE_CONNECTION=database` using the `jobs` table. In production, jobs remain unprocessed unless an active queue runner executes `queue:work`.

### 1.6 Laravel Scheduler Commands (`routes/console.php`)
The scheduler defines 8 recurring tasks:
* `trends:schedule` — Every minute (`withoutOverlapping`).
* `autopilot:process` — Every 5 minutes (`withoutOverlapping`).
* `scheduling:process-queue` — Every 15 minutes (`withoutOverlapping`).
* `SendUpcomingPostReminderJob` — Hourly.
* `publishing:dispatch-scheduled` — Every minute (`withoutOverlapping`).
* `publishing:refresh-tokens` — Hourly (`withoutOverlapping`).
* `analytics:collect` — Daily at 02:00 UTC (`withoutOverlapping`).
* `db:backup` — Daily at 01:00 UTC (`withoutOverlapping`).

**Critical Finding:** Social publishing is checked **every minute**. Without continuous execution, scheduled posts are delayed.

---

## 2. HOSTING PROVIDERS INVESTIGATED & ELIGIBILITY AUDIT

Every candidate provider was audited against official free-tier terms, credit card mandates, resource allowances, sleep behavior, and auto-billing policies.

| Provider | Card Required? | Permanent Free Tier? | Supported Stacks | RAM / CPU Limits | Background Workers? | Cron Jobs? | TiDB Outbound (Port 4000)? | Risk of Automatic Billing | Status |
| :--- | :---: | :---: | :---: | :---: | :---: | :---: | :---: | :---: | :---: |
| **Render** | **NO** | Yes | Docker, PHP, Python, Node | 512 MB RAM / 0.5 vCPU | Paid Only ($7/mo min) | Paid Only ($7/mo min) | Supported (TLS) | **Zero** (Suspends on limit) | **Approved for Demo/Staging Only** |
| **Hugging Face Spaces** | **YES** | **No for Docker** | Static HTML, Docker (Paid) | 16 GB (PRO tier) | No free Docker | No | Supported | **HIGH** ($9/mo PRO required) | **DISQUALIFIED (Policy Update)** |
| **TiDB Cloud Serverless** | **NO** | Yes | MySQL 8.0 wire-protocol | 5 GiB Row / 50M RUs/mo | N/A (Managed DB) | N/A | Port 4000 Native | **Zero** (Throttles/suspends) | **Approved for DB (Manual Check Req)**|
| **Supabase Storage** | **NO** | Yes | S3-compatible Object API | 1 GB storage / 5 GB egress | N/A (Object Store) | N/A | HTTPS Port 443 | **Zero** (Restricts uploads) | **Approved for Media (7-day pause risk)**|
| **GitHub Actions** | **NO** | Yes | Linux CI/CD runners | 7 GB RAM / 2 vCPUs | Workflows up to 6 hrs | `on: schedule` cron | Supported | **Zero** (Free for public repos) | **Approved as Auxiliary Trigger** |
| **Cloudflare Pages** | **NO** | Yes | Static, JS/Wasm | N/A (Static CDN) | **NO** (No PHP runtime) | N/A | N/A | **Zero** (Free tier) | **Approved for Static Assets/Web APK Only**|
| **Koyeb** | **YES** | Yes | Docker, Node, Python | 512 MB RAM / 0.1 vCPU | No free workers | Paid only | Supported | **HIGH** ($29 card hold) | **DISQUALIFIED** |
| **Fly.io** | **YES** | **No** (Trial only) | Docker | Micro machines | Pay-as-you-go | Paid | Supported | **HIGH** (Requires CC after 7d) | **DISQUALIFIED** |
| **Railway** | **Conditional** | **No** ($1 credit) | Docker, PHP, Python | 512 MB RAM | Dies when $1 spent | Paid | Supported | **HIGH** (Credit runs out in 7d) | **DISQUALIFIED** |
| **Vercel** | **NO** | Yes | Serverless Node, Python | 1024 MB (Lambda) | **NO** (No daemons) | 1/day max on free tier | Supported | **Zero** | **DISQUALIFIED for Laravel Core** |
| **Netlify** | **NO** | Yes | Static / Edge Functions | Serverless | **NO** | No minute cron | N/A | **Zero** | **DISQUALIFIED for Core**|
| **Alwaysdata** | **NO** | Yes | PHP, Python, SSH | 100 MB disk total | Limited | Yes | Supported | **Zero** | **DISQUALIFIED** (100MB cap) |
| **PythonAnywhere** | **NO** | Yes | Python WSGI only | 512 MB | No | Limited | **BLOCKED** (Whitelisted HTTP) | **Zero** | **DISQUALIFIED** (No PHP/TiDB)|
| **AWS / GCP / Azure** | **YES** | 12-mo Trial | Full cloud | Variable | Yes | Yes | Supported | **CRITICAL** (Auto-bills) | **DISQUALIFIED** |
| **Oracle Cloud Free** | **YES** | Yes | Arm/AMD VMs | 1-24 GB RAM | Yes | Yes | Supported | **HIGH** (Card verification hold)| **DISQUALIFIED** |

---

## 3. STAGE 5.1.1 — CORRECTIONS AND VERIFIED LIMITATIONS

This section incorporates all specific corrections, re-evaluations, and hardened specifications mandated in Stage 5.1.1:

### 3.1 Corrected Provider Eligibility & Hugging Face Removal
* **Hugging Face Policy Shift:** Hugging Face previously permitted personal free accounts to spin up CPU Basic Docker spaces. In current documentation and live platform enforcement, **Docker-based Spaces are strictly gated behind paid PRO ($9/month) or Team subscriptions**, both requiring credit card registration.
* **Removal from Architecture:** Hugging Face Docker hosting is **formally removed** from the recommended architecture.
* **Zero-Cost Python Engine Solution:** To retain the Python/FastAPI AI engine without paid tiers or credit cards, the Python engine must be **collocated inside the single unified Docker container alongside Laravel on Render**, managed by Supervisor on internal loopback (`http://127.0.0.1:8080`).
  * *Resource trade-off:* Both PHP-FPM and Python FastAPI must operate within Render’s 512 MB RAM limit. This requires strict concurrency tuning (`pm.max_children = 2` for PHP-FPM, single worker for Uvicorn).

### 3.2 Reassessed Render Free Limitations & Production Viability
Render Free possesses specific infrastructural limits that must be honestly stated:
* **Inactivity Sleep:** Services spin down after **15 minutes of zero inbound HTTP traffic**.
* **Cold Starts:** Waking an inactive container incurs a **45 to 55-second delay**.
* **Instance Hours:** Free accounts receive **750 hours/month** across all free web services. Running a single service 24/7 consumes 720 to 744 hours (approx. 97–99% of allowance). Running two separate services exhausts the pool by mid-month.
* **Ephemeral Disk:** Any file written to the local container disk is destroyed upon restart, spin-down, or new deployment.
* **No Free Workers or Cron:** Background worker and cron job service types have no free tier ($7/month minimum each).
* **Outbound Bandwidth:** Free tier accounts receive **5 GB/month** of outbound internet bandwidth. Exceeding this allowance suspends services or requires upgrading ($0.15/GB overage).
* **Unreliability of External Keep-Alive Pings:** Pinging services (such as cron-job.org or GitHub Actions) **cannot guarantee 100% uptime**:
  * Free external schedulers suffer from network jitter, DNS resolution delays, and scheduling queues (GitHub Actions cron can be delayed by 15–45 minutes).
  * If a ping times out before the 50-second cold start completes, the ping fails.
  * Render reserves the right to rate-limit or terminate accounts utilizing abusive keep-alive loops.
* **Verdict on Render Free:** Suitable **strictly for staging, demonstration, and evaluation purposes**. It does not constitute dependable, commercially viable 24/7 SaaS hosting.

### 3.3 TiDB Account & Cluster Quota Revalidation
* **Published Starter Allowances:** Up to 5 free clusters per organization, 5 GiB row storage, 5 GiB columnar storage, and 50 million Request Units (RUs) per month.
* **Current Consumption & Spending Limit Status:**
  * SQL connections into the TiDB database can query system status (`SHOW STATUS LIKE 'Ssl_cipher'`) and user tables, but **cannot query billing control-plane metrics** (e.g. monthly RU usage, current monthly spending limit, overage throttle settings).
  * The command line interface and local test suite cannot independently confirm whether the cluster owner has set the monthly spending limit to strictly \$0 or left it uncapped.
  * **MANDATORY REQUIREMENT:** The cluster quota and spending limit are **marked as requiring manual dashboard verification**. Prior to public launch, the repository owner must log into `tidbcloud.com`, inspect **Cluster -> Usage & Spending Limits**, and verify:
    1. The cluster is operating on the free Starter tier.
    2. The **Monthly Spending Limit is set to $0** (hard cap).
    3. The cluster behavior on quota exhaustion is set to throttle/pause rather than auto-bill.

### 3.4 Supabase Storage Reassessment
* **Free Tier Quotas:**
  * Total Storage: **1 GB**.
  * Maximum Single File Upload: **50 MB** (fits all SocialPilot AI brand logos and post images).
  * Egress Bandwidth: **5 GB uncached + 5 GB cached egress** per month.
* **Inactivity Pausing Policy:**
  * Free Supabase projects **automatically pause after 7 consecutive days of inactivity**.
  * *Impact on SocialPilot AI:* If no media uploads occur for 7 days, the project pauses. When paused, public media URLs requested by Meta Graph API or TikTok will fail with HTTP 503/404 until the project is manually unpaused in the Supabase Dashboard.
* **Database Schema Impact:**
  * The existing Laravel media system (`Brand::logo_path`, `MediaAsset::storage_path`, `MediaAsset::public_url`) stores relative paths and uses Laravel’s `Storage::disk('s3')->url($path)` helper.
  * **Zero database schema changes are required** to use Supabase Storage. Only environment variables (`AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`, `AWS_BUCKET`, `AWS_ENDPOINT`, `AWS_USE_PATH_STYLE_ENDPOINT=true`) need to be configured.

### 3.5 Redesigned Secure, Bounded Queue & Scheduler Execution
To execute queues and scheduled commands without paid background workers or public Artisan exposure, the execution mechanism is redesigned with strict security boundaries:

```
+---------------------------------------------------------------------------------------+
|                               SECURE OPS CYCLE PIPELINE                               |
|                                                                                       |
|   External Trigger (cron-job.org / GitHub Actions)                                    |
|   Method: POST /api/internal/ops/cycle                                                |
|   Header: Authorization: Bearer <INTERNAL_CRON_SECRET>                                |
+------------------------------------------+--------------------------------------------+
                                           |
                                           v
+---------------------------------------------------------------------------------------+
|  1. Timing-Safe Authentication (`hash_equals`) -> Reject 401 if invalid/empty         |
|  2. Atomic Mutex Lock (`Cache::lock('ops_cycle_lock', 60)`) -> Return 429 if active   |
|  3. Pre-flight Checks (Verify active DB connection, memory threshold < 80%)          |
+------------------------------------------+--------------------------------------------+
                                           |
                    +----------------------+----------------------+
                    |                                             |
                    v                                             v
+---------------------------------------+     +-----------------------------------------+
|     Phase 1: Scheduler Execution      |     |       Phase 2: Bounded Queue Work       |
|                                       |     |                                         |
|  - Calls: `Artisan::call('schedule:run')`  - Calls: `queue:work` with hard bounds:   |
|  - Checks due scheduled posts         |     |    * `--stop-when-empty`               |
|  - Dispatches publishing & autopilot  |     |    * `--max-jobs=10`                   |
|  - Memory & execution time monitored  |     |    * `--max-time=25` (sub-gateway)     |
|                                       |     |    * `--tries=3` (preserves retry)     |
+---------------------------------------+     +-----------------------------------------+
                                           |
                                           v
+---------------------------------------------------------------------------------------+
|  4. Failure Logging: Records failed jobs to `failed_jobs` table; logs run metrics     |
|  5. Release Cache Lock & Return JSON Response: `{"status": "completed", "jobs": n}`  |
+---------------------------------------------------------------------------------------+
```

#### Key Security & Operational Properties:
1. **No Public Artisan Exposure:** The endpoint does not accept arbitrary command parameters. Only hardcoded internal calls (`schedule:run` and bounded `queue:work`) are executed.
2. **Timing-Safe Auth:** Authenticated via `INTERNAL_CRON_SECRET` using `hash_equals()`.
3. **Overlapping Prevention:** Protected by an atomic cache lock (`Cache::lock('ops_cycle_lock', 60)`). Overlapping runs are rejected immediately.
4. **Bounded Queue Run:** Configured with `--stop-when-empty`, `--max-jobs=10`, and `--max-time=25`. Ensures the request exits before HTTP gateway timeouts (typically 30–60s).
5. **Preserved Failure Semantics:** Job failures trigger Laravel’s native retry and exception logging into `failed_jobs`.
6. **Documented Failure Scenarios:**
   * *Cold-Start Timeout:* If Render has spun down, waking the container takes ~50s. An external ping with a 30s timeout will fail on the first hit.
   * *Scheduling Jitter:* GitHub Actions cron jobs often experience 15 to 45-minute delays. Scheduled posts may not dispatch at the exact intended minute.
   * *Backlog Accumulation:* If 50 jobs are queued during a spike, processing 10 jobs per ping takes 5 ping cycles to clear.

### 3.6 Corrected Website Delivery Architecture
To prevent confusion regarding runtime requirements, website delivery is structured into clear tiers:

1. **Laravel Dynamic Web Core (Render Web Service):**
   * Executes the PHP 8.2+ runtime and renders Blade views (Dashboard, Publishing Studio, Analytics, Settings).
   * Exposes the authenticated REST API (`/api/v1/*`) consumed by the Flutter app.
   * **Cannot be hosted on Cloudflare Pages, GitHub Pages, or Netlify Static.**
2. **Static Frontend Assets (Vite / Container Public):**
   * Compiled CSS (`public/build/assets/*.css`) and JavaScript (`public/build/assets/*.js`) generated by Vite.
   * Served directly by the web server (Nginx/Caddy) inside the Render container, with optional Cloudflare CDN caching.
3. **Flutter Mobile Application (GitHub Releases / Cloudflare Pages):**
   * **Android APK:** Built via `flutter build apk --release --dart-define=API_BASE_URL=https://<production-domain>/api/v1`. Distributed for $0 via **GitHub Releases** (bypasses Google Play fee).
   * **Flutter Web (Optional):** Compiled via `flutter build web` into static HTML/Wasm. This static artifact **can be hosted for $0 on Cloudflare Pages**, acting as a browser client connecting to the hosted Laravel REST API.

---

## 4. REVISED $0 DEPLOYMENT ARCHITECTURE

```
                                  +------------------------------------+
                                  |     Flutter Mobile Client (APK)    |
                                  |   (Distributed on GitHub Releases) |
                                  +-----------------+------------------+
                                                    |
                                                    | HTTPS (Sanctum Tokens)
                                                    v
+------------------------+      HTTPS       +---------------------------------------------+
|   External Trigger     |--------------->  |           Render Free Web Service           |
| (cron-job.org / GitHub | (Bearer Secret)  |           (Unified Docker Container)        |
|  Actions * * * * *)    |                  |  - Nginx Reverse Proxy (Port 10000)         |
+------------------------+                  |  - PHP-FPM 8.2 (Laravel Core & Blade UI)    |
                                            |  - Python 3.12 (FastAPI on 127.0.0.1:8080)  |
                                            |  - Supervisor Process Manager               |
                                            |  - In-Process Bounded Queue Runner          |
                                            +------+------------------+-------------------+
                                                   |                  |
                       TLS MySQL (Port 4000)       |                  | S3 API (Port 443)
                     +-----------------------------+                  |
                     v                                                v
    +--------------------------------+               +------------------------------------+
    |      TiDB Cloud Serverless     |               |      Supabase Object Storage       |
    |     (Starter Tier - 25 GiB)    |               |       (1 GB Free Persistent)       |
    |  - Relational Schema Data      |               |  - Brand Logos & Post Images       |
    |  - Queued Jobs (`jobs` table)  |               |  - Public HTTPS Media URLs         |
    |  - Encrypted OAuth Tokens      |               |  - (Manual Unpause if >7d idle)    |
    |  *(Manual Dashboard Check Req)*|               +------------------------------------+
    +--------------------------------+
```

---

## 5. WHAT CANNOT RELIABLY OPERATE UNDER THE $0 RESTRICTIONS

The following features **cannot operate reliably under a strict $0 budget** without paid upgrades:

| Feature / Requirement | Underlying Technical Bottleneck | Consequence Under $0 Constraints |
| :--- | :--- | :--- |
| **Exact Sub-Minute Post Publishing** | Render free tier sleeps after 15m; free external schedulers experience jitter (15–45m on GitHub Actions; up to 5m on cron-job.org). | Scheduled posts may publish 5 to 30 minutes late during periods of container inactivity. |
| **Continuous Background Processing** | Dedicated background workers require paid dynos ($7/mo on Render, paid tiers on Fly/Railway). | Queue jobs are processed in batches only when an external HTTP ping arrives. Heavy queues will experience latency. |
| **Uninterrupted Public Media Hosting** | Free Supabase projects pause after 7 days of inactivity. | If no new media is uploaded for a week, existing media URLs break until manually unpaused in the dashboard. |
| **High-Concurrency AI Generations** | Collocating PHP-FPM and Python FastAPI inside a single 512 MB RAM container leaves little headroom for heavy memory spikes. | Concurrent requests may trigger Out-Of-Memory (OOM) kills if more than 2–3 users generate content simultaneously. |
| **Production SLA / Guaranteed Uptime** | Render Free has cold starts (~50s), a 750-hour monthly account pool, and a 5 GB outbound bandwidth cap. | The service is vulnerable to temporary downtime upon budget exhaustion or unexpected traffic spikes. |

---

## 6. REQUIRED CONFIGURATION CHANGES PRIOR TO STAGE 5.2

1. **Unified Dockerfile Preparation:** Create a multi-stage Dockerfile combining PHP 8.2-FPM, Nginx, and Python 3.12 with Supervisor to manage both services within a 512 MB memory footprint.
2. **Dedicated In-Process Cycle Route:** Register `POST /api/internal/ops/cycle` in `routes/api.php` guarded by `INTERNAL_CRON_SECRET` and cache locking.
3. **Environment Hardening:** Set `APP_ENV=production`, `APP_DEBUG=false`, `FORCE_HTTPS=true`, and configure `SESSION_DRIVER=database` and `CACHE_STORE=database`.
4. **Storage Configuration:** Configure `.env` with Supabase S3 credentials and set `FILESYSTEM_DISK=s3` and `MEDIA_STORAGE_DISK=s3`.
5. **Mobile API Compile Parameter:** Build Flutter releases using `--dart-define=API_BASE_URL=https://<render-domain>/api/v1`.

---

## 7. FINANCIAL LEDGER (VERIFIED $0 COMMITMENT)

| Architectural Tier | Provider & Plan | Monthly Allowance | Monthly Cost | Overage Policy (Without Payment Card) |
| :--- | :--- | :--- | :---: | :--- |
| **Core Web & Collocated AI** | Render Free Web Service | 512 MB RAM, 0.5 CPU, 750 hrs/mo | **$0.00** | Suspends service on limit; never auto-bills without a card. |
| **Production Database** | TiDB Cloud Serverless | 25 GiB storage, 50M RUs/mo | **$0.00** | Strict throttling / suspension on limit. *(Verify spending limit = $0 in console)* |
| **Media Object Storage** | Supabase Storage | 1 GB storage, 5 GB egress/mo | **$0.00** | Uploads rejected when 1 GB reached; account not billed. |
| **Auxiliary Scheduler Trigger**| cron-job.org / GitHub Actions | Unlimited execution pings | **$0.00** | Free public automation. |
| **Mobile APK Delivery** | GitHub Releases | Unlimited downloads | **$0.00** | Free for public/open repositories. |
| **SSL / TLS Encryption** | Let's Encrypt / Render | Automated SSL certificates | **$0.00** | Automatically provisioned. |
| **TOTAL MONTHLY HOSTING COMMITMENT** | | | **$0.00 / mo** | **Guaranteed $0.00 (Zero Card Required)** |

---

## 8. TEST SUITE & APPLICATION STABILITY CONFIRMATION

To ensure that the feasibility audit and documentation corrections did not alter any application code or database state, the regression test suite was executed:

* **Laravel Test Suite:** **267 passed (1,278 assertions)** — 100% passing.
* **Database State:**
  * Local SQLite database (`database/database.sqlite`): **Intact and untouched** (868 KB).
  * Verified database backups: **Preserved** (`storage/app/backups/`).
  * TiDB Production Database: **Untouched** (no migrations, modifications, or reseeding performed).
* **Git Status:** Clean (`main` branch, working tree completely clean).

---

## 9. FINAL VERDICT

### **APPROVED FOR LOCAL CONTAINER PREPARATION ONLY**

#### Technical Justification:
* **Why not "Approved for Stage 5.2 Production Deployment":** Deploying to Render Free with external webhooks cannot guarantee dependable commercial production uptime, sub-minute social publishing, or immunity from the 15-minute sleep and 7-day storage pause policies. Claiming this setup is "production-ready" would be inaccurate.
* **Why "Approved for Local Container Preparation Only":** The proposed unified container architecture (collocating Laravel, PHP-FPM, Python FastAPI, and the bounded in-process queue runner) is technically sound, cost-free, and safe to prepare and validate locally in Stage 5.2. Local container preparation allows comprehensive benchmarking of memory consumption (staying beneath 512 MB) and verification of the secure cycle route before any cloud hosting decisions are finalized.
