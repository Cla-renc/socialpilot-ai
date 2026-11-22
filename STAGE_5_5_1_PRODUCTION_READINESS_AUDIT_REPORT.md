# STAGE 5.5.1 — PRODUCTION READINESS & TRANSITION AUDIT REPORT

**Project:** SocialPilot AI  
**Repository:** `https://github.com/Cla-renc/socialpilot-ai`, branch `main`  
**Staging Host:** `https://socialpilot-staging.onrender.com`  
**Target Environment:** SocialPilot AI Production Environment  
**Database Systems:** TiDB Cloud Serverless (`socialpilot` production, `socialpilot_staging` staging)  
**Evaluation Mode:** Strict Read-Only Audit  
**Audit Date:** 2026-11-19  

---

## 1. EXECUTIVE SUMMARY

This audit delivers an exhaustive production transition readiness assessment for SocialPilot AI. 

The evaluation encompasses:
- Reusability of the audited unified container architecture (Nginx, PHP 8.2-FPM, Python FastAPI, Supervisor).
- Durable object storage alternatives evaluated against official terms and the user's strict $0 / card-free policy.
- Queue processing and scheduler hosting strategies on free-tier infrastructure.
- Social media platform API registration requirements and publishing readiness.
- Flutter Android client release packaging, signing, and API targeting.
- Production security controls, least-privilege database user models, and operational runbooks.

### Final Acceptance Decision
### **B — PRODUCTION PREPARATION CONDITIONALLY READY; DOCUMENTED BLOCKERS REMAIN.**

The application codebase, database schema (59 base tables, 29 migrations), and internal AI services are production-capable. However, transition to live production is conditional upon resolving five documented infrastructure and integration dependencies:
1. **Object Storage Activation:** Selecting a durable storage provider compatible with the card-free constraint (e.g. Supabase Storage vs Cloudflare R2).
2. **Social Developer App Approvals:** Registering and approving production OAuth applications for Meta, LinkedIn, TikTok, and YouTube.
3. **Production Database Credential Provisioning:** Establishing a dedicated DML-only production account (`AZDARrqgy4MBFGE.prod_app`) on the isolated `socialpilot` database.
4. **Render Free Tier Constraints Acceptance:** Operating within the 512MB RAM cap and 15-minute inactivity sleep window, or selecting an always-on hosting alternative.
5. **Mobile Release Build:** Generating an official Android signing keystore and compiling the Flutter APK targeting the production domain.

---

## 2. EXISTING ARCHITECTURE & REUSABILITY ASSESSMENT

### 2.1 Reusable Components (Zero Changes Required)
- **`Dockerfile.unified` Multi-Stage Container:** 
  - Stage 1 compiles frontend assets via Node 20 (`npm run build`).
  - Stage 2 builds the PHP 8.2-FPM Bookworm runtime with Python 3, Nginx, and Supervisor.
  - 100% deterministic, stateless, and reusable directly for production.
- **Process Supervision (`docker/supervisor/supervisord.conf`):**
  - Manages `php-fpm` (PID priority 10), `fastapi` (PID priority 20), and `nginx` (PID priority 30).
  - All processes configure `autorestart=true` and group signal handling.
- **Dynamic Port Injection (`docker/docker-entrypoint.sh`):**
  - Adapts Nginx `default.conf` dynamically to the hosting provider's assigned `$PORT` (Render / cloud container standards).
- **FastAPI AI Engine (`ai_engine/ai_engine/main.py`):**
  - Loopback-only service on `127.0.0.1:8080` protected by internal timing-safe bearer token.
  - Zero external cloud AI dependencies required when using `AI_PROVIDER=internal`.
- **Database Schema & Migrations:**
  - 59 base tables and 29 migrations already exist on the target `socialpilot` production database.
  - No new DDL or migrations are required.

### 2.2 Components Requiring Separate Production Configuration
- **Application Core:** `APP_ENV=production`, `APP_DEBUG=false`, unique production `APP_KEY`, production `APP_URL`.
- **Database Connection:** `DB_DATABASE=socialpilot`, separate production username and password.
- **Security & CORS:** `CORS_ALLOWED_ORIGINS` and `SANCTUM_STATEFUL_DOMAINS` pointing strictly to the production domain.
- **Secrets:** Fresh random 64-character hex tokens for `AI_ENGINE_INTERNAL_TOKEN`, `TREND_ENGINE_INTERNAL_TOKEN`, `PUBLISHING_INTERNAL_TOKEN`, and `INTERNAL_CRON_SECRET`.

---

## 3. DURABLE MEDIA STORAGE ASSESSMENT

### 3.1 Current Local Implementation
- **Filesystem Disks:** `FILESYSTEM_DISK=local`, `MEDIA_STORAGE_DISK=local`.
- **Physical Location:** Files are written to `storage/app/public/media/{brand_id}/`.
- **Metadata Isolation:** Metadata (`file_name`, `mime_type`, `file_size`, `storage_disk`, `storage_path`) is stored in `media_assets` in TiDB Cloud.
- **Ephemeral Risk:** On Render Free Tier, the container filesystem is wiped upon every restart, deployment, or sleep cycle. This leaves orphaned rows in `media_assets` whose `public_url` returns `HTTP 404`.

### 3.2 Object Storage Provider Evaluation ($0 Constraint)

| Provider | Free Monthly Allowance | Credit Card Required? | Egress Pricing | Overages & Limits | Classification |
|:---|:---|:---:|:---:|:---|:---:|
| **Cloudflare R2** | 10 GB storage<br>1M Class A ops<br>10M Class B ops | **YES**<br>(Required to activate R2 metered billing) | $0.00 (Zero egress fees) | Billed automatically if free tier exceeded | **PAYMENT DETAILS REQUIRED** |
| **Supabase Storage** | 1 GB storage<br>5 GB bandwidth | **NO**<br>(100% card-free) | Included up to 5 GB | Hard cap; project pauses after 7 days inactivity if zero traffic | **FREE WITH LIMITATIONS** |
| **Backblaze B2** | 10 GB storage<br>3x free egress via Bandwidth Alliance | **NO**<br>(Card-free signup) | Free via Cloudflare CDN integration | 10 GB hard limit on free tier | **FREE WITH LIMITATIONS** |
| **AWS S3** | 5 GB storage (12 months only) | **YES**<br>(Card required) | Metered ($0.09/GB) | Reverts to paid billing after 12 months | **PAYMENT DETAILS REQUIRED** |

### 3.3 Zero-Cost Policy Recommendation
Because the user mandates a strict **$0 / no payment card** policy:
1. **Cloudflare R2 is disqualified** under strict card-free rules because Cloudflare requires adding a payment method to provision R2 buckets.
2. **Supabase Storage or Backblaze B2 is recommended** for true card-free $0 production operations.
3. **Integration Requirement:** Adding `league/flysystem-aws-s3-v3` to `composer.json` is mandatory to enable S3-compatible cloud storage in Laravel.

---

## 4. BACKGROUND JOBS AND HOSTING STRATEGY

### 4.1 Queue & Scheduler Analysis
- **Queue Connection:** `QUEUE_CONNECTION=database` backed by TiDB Serverless (`socialpilot.jobs`).
- **Persistence During Sleep:** While the container sleeps, jobs in `jobs` remain **100% durable in TiDB Cloud**.
- **Bounded Operations Cycle:** Triggered via `POST /api/internal/ops/cycle`:
  - `schedule:run` evaluates scheduled tasks.
  - `queue:work` executes with bounded constraints: `--stop-when-empty`, `--max-jobs=10`, `--max-time=25`, `--tries=3`.
  - Atomic cache lock (`internal_ops_cycle_lock`, 60s expiration) prevents overlapping runs.

### 4.2 Workload Classification & Timing Sensitivity

| Task | Command | Frequency | Timing Sensitivity | Can Wait For Wakeup? |
|:---|:---|:---:|:---:|:---:|
| **Publishing Dispatch** | `publishing:dispatch-scheduled` | Every 1 min | **HIGH** (Social posts must publish on time) | No (Requires active wake) |
| **Trend Research** | `trends:schedule` | Every 1 min | **MEDIUM** (Ingests trending topics) | Yes |
| **Autopilot Rules** | `autopilot:process` | Every 5 min | **MEDIUM** (Generates queue items) | Yes |
| **Queue Processing** | `scheduling:process-queue` | Every 15 min | **MEDIUM** | Yes |
| **Token Refresh** | `publishing:refresh-tokens` | Hourly | **HIGH** (Must refresh before expiry) | Yes (within 24h) |
| **Post Reminders** | `SendUpcomingPostReminderJob` | Hourly | **MEDIUM** | Yes |
| **Analytics Collection**| `analytics:collect` | Daily @ 02:00 | **LOW** | Yes |
| **Database Backup** | `db:backup` | Daily @ 01:00 | **HIGH** (Disaster recovery) | Requires night wake |

### 4.3 Hosting Feasibility on Render Free Tier
- **Constraints:**
  - 512MB RAM cap.
  - 15-minute inactivity timeout.
  - 750 free instance hours/month per account.
- **Production Assessment:**
  - Separating health monitoring (`/up`) from job execution (`/api/internal/ops/cycle`) is enforced.
  - A duty-cycle external cron (e.g. Cron-job.org / UptimeRobot) pinging `/api/internal/ops/cycle` every 20–25 minutes between 08:00 and 22:00 UTC consumes ~420–440 hours/month (well within the 750h pool).
  - **Limitation:** Off-duty posts scheduled between 22:00 and 08:00 UTC will be delayed until the morning duty cycle wakes the instance. This must be accepted for a $0 production service or supplemented by nighttime cron triggers.

---

## 5. SOCIAL MEDIA INTEGRATIONS AUDIT

| Platform | Code Implementation | Registered Provider | Required Scopes / Permissions | Developer App Status | Live Publishing Tested? | Cost / Approval Blockers |
|:---|:---:|:---:|:---|:---:|:---:|:---|
| **LinkedIn** | `LinkedInPublishingProvider` | Yes | `openid`, `profile`, `email`, `w_member_social` | Unconfigured on Prod | Staging Mock Verified | **$0** · Standard developer access; requires App creation |
| **Meta (Facebook)** | `FacebookPublishingProvider` | Yes | `pages_show_list`, `pages_read_engagement`, `pages_manage_posts`, `publish_video` | Unconfigured on Prod | Staging Mock Verified | **$0** · Requires Meta Business App Verification for public use |
| **Meta (Instagram)**| `InstagramPublishingProvider`| Yes | `instagram_basic`, `instagram_content_publish` | Unconfigured on Prod | Staging Mock Verified | **$0** · Requires Instagram Graph API review |
| **TikTok** | `TikTokPublishingProvider` | Yes | `user.info.basic`, `video.upload`, `video.publish` | Unconfigured on Prod | Staging Mock Verified | **$0** · Content Posting API requires TikTok Developer Review |
| **YouTube** | `YouTubePublishingProvider` | Yes | `youtube.upload`, `youtube.readonly` | Unconfigured on Prod | Staging Mock Verified | **$0** · Google Cloud Console OAuth consent screen required |
| **X (Twitter)** | `SocialConnectionController` | No (OAuth only) | `tweet.read`, `tweet.write`, `users.read` | Unconfigured on Prod | Not Verified | **Free tier limited** to 1,500 writes/mo; v2 Basic is $100/mo |

**Integration Finding:** No live publishing to real social media accounts has been executed. The codebase features complete HTTP client adapters and idempotency controls, but live production broadcasting requires registered developer keys.

---

## 6. FLUTTER PRODUCTION RELEASE AUDIT

Audited from `mobile/` and `mobile/android/app/build.gradle.kts`:

| Parameter | Current Configuration | Production Requirement | Status |
|:---|:---|:---|:---:|
| **Package Identifier** | `com.socialpilot.ai` | `com.socialpilot.ai` | ✅ Production Ready |
| **Min / Target SDK** | Flutter default (Min: 21, Target: 34) | Min: 21, Target: 34+ (Play Store standard) | ✅ Production Ready |
| **Cleartext Traffic** | `android:usesCleartextTraffic="true"` | Must set to `false` for HTTPS-only security | ⚠️ Needs Hardening |
| **Signing Config** | Falls back to debug signature | Requires official keystore (`key.properties`) | 🔴 Blocked for Play Store |
| **API Base URL** | `http://10.0.2.2:8000/api/v1` default | `--dart-define=API_BASE_URL=https://<prod>/api/v1` | ⚠️ Build Flag Required |
| **Asset Linking** | Icons and logos in `assets/images/` | High-res production launcher assets | ✅ Implemented |

**Release Assessment:**
- The Flutter codebase is **ready for internal release testing** (signed with debug keystore).
- For **Google Play Store distribution**, a dedicated release signing key must be generated.

---

## 7. PRODUCTION SECURITY & OPERATIONS CHECKLIST

| Control Area | Security Requirement | Existing Staging Control | Planned Production Control |
|:---|:---|:---:|:---:|
| **App Key** | 32-byte cryptographically secure key | Verified in Staging (`APP_KEY`) | Freshly generated via `artisan key:generate` |
| **Debug Mode** | `APP_DEBUG=false` | Verified `false` on Staging | Statically enforced `false` |
| **HTTPS Enforcement** | Strict HTTPS redirect behind proxy | `FORCE_HTTPS=true`, `trustProxies('*')` | Enforced at Render Cloudflare Edge |
| **CORS / Sanctum** | Whitelisted origin domains only | Staging subdomain whitelisted | Dedicated production subdomain only |
| **Database User** | Least-privilege DML user (No DDL) | `AZDARrqgy4MBFGE.staging_app` | `AZDARrqgy4MBFGE.prod_app` (DML only) |
| **Database Encryption**| Mandatory TLS wire encryption | `MYSQL_ATTR_SSL_CA` active | `MYSQL_ATTR_SSL_CA` active |
| **Backups** | Scheduled daily backup & SHA-256 | Implemented in `db:backup` command | Scheduled daily via ops cycle |
| **Secret Storage** | Zero credentials in Git repository | Gitignored `.env.*` enforced | Render Dashboard encrypted env vars |
| **Error Logging** | Standardized JSON stderr streams | Directed to Supervisor stderr | Streamed to Render log aggregator |

---

## 8. ZERO-COST COMPLIANCE MATRIX

| Component | Target Provider | Policy Classification | Condition / Limitation |
|:---|:---|:---:|:---|
| **Web Service Hosting** | Render Free Tier | **FREE WITH LIMITATIONS** | 512MB RAM, 15-min sleep, 750h/month limit |
| **Relational Database** | TiDB Cloud Serverless | **VERIFIED FREE** | 5GB storage, 50M RUs/month, zero payment details |
| **AI Text Engine** | Internal FastAPI Bridge | **VERIFIED FREE** | Runs on loopback within unified container ($0 cost) |
| **Scheduler Automation** | Cron-job.org / UptimeRobot | **VERIFIED FREE** | External HTTP webhook, zero payment details |
| **Object Media Storage** | Supabase Storage | **FREE WITH LIMITATIONS** | 1GB storage, 5GB bandwidth, card-free |
| **Object Media Storage** | Cloudflare R2 | **PAYMENT DETAILS REQUIRED** | 10GB free, but credit card required to provision |
| **Social Publishing APIs** | Meta, LinkedIn, Google | **VERIFIED FREE** | Standard developer tiers are $0 |
| **X (Twitter) API** | X Developer Platform | **FREE WITH LIMITATIONS** | Free tier capped at 1,500 post writes/month |

---

## 9. PRODUCTION BLOCKERS & DEPENDENCIES

The following items are explicit blockers that prevent immediate production launch:

1. **Durable Object Storage Package:** `league/flysystem-aws-s3-v3` must be installed in `composer.json` and deployed to avoid local ephemeral data loss.
2. **Card-Free Storage Selection:** The user must approve using a card-free storage provider (such as Supabase Storage or Backblaze B2) instead of Cloudflare R2.
3. **Production Database User Provisioning:** The dedicated least-privilege DML account for `socialpilot` must be provisioned.
4. **Third-Party Developer Apps:** Social platform OAuth credentials for LinkedIn, Meta, and Google must be created with production callback URIs.
5. **Mobile Signing Keystore:** An Android upload keystore must be generated before compiling public store builds.

---

## 10. RECOMMENDED IMPLEMENTATION SEQUENCE

```
[Stage 5.5.1: Readiness Audit Complete]
       │
       ▼
[Stage 5.5.2: Durable Storage & Dependency Integration]
       ├─ Add league/flysystem-aws-s3-v3 to composer.json
       └─ Configure card-free S3-compatible bucket (Supabase / B2)
       │
       ▼
[Stage 5.5.3: Production Database Account Provisioning]
       ├─ Connect as TiDB root with TLS
       ├─ Provision AZDARrqgy4MBFGE.prod_app on `socialpilot` (DML only)
       └─ Verify cross-database isolation
       │
       ▼
[Stage 5.5.4: Production Web Service Deployment on Render]
       ├─ Create `socialpilot-prod` Web Service (Dockerfile.unified)
       ├─ Configure production environment variables & fresh secrets
       └─ Verify live health checks (/up, /api/v1/health/ready)
       │
       ▼
[Stage 5.5.5: External Cron & Production Operations Verification]
       ├─ Configure external duty-cycle cron
       └─ Verify ops cycle execution and backup automation
       │
       ▼
[Stage 5.5.6: Flutter Production APK Packaging]
       └─ Build release APK with --dart-define=API_BASE_URL
```

---

## 11. ACTIONS REQUIRING SEPARATE HUMAN AUTHORIZATION

Before any transition step is executed, explicit human authorization is required for:
1. Installing `league/flysystem-aws-s3-v3` into `composer.json` and committing the update.
2. Creating a card-free object storage bucket on Supabase or Backblaze B2.
3. Executing the SQL statement on TiDB Cloud to create the `AZDARrqgy4MBFGE.prod_app` user.
4. Creating the new Web Service on Render for production.
5. Entering production credentials into Render's dashboard.

---

*This report concludes the Stage 5.5.1 Production Readiness Audit under strict read-only constraints.*
