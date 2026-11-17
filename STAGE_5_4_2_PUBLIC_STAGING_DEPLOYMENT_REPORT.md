# SOCIALPILOT AI — PHASE 11, STAGE 5.4.2
## Controlled Public Staging Deployment & Post-Deployment Verification Report

**Repository:** `Cla-renc/socialpilot-ai`  
**Branch:** `main`  
**Baseline Commit:** `98b3587`  
**Stage:** 5.4.2 (Controlled Public Staging Deployment & Post-Deployment Verification)  
**Date:** 2026-11-17  
**Final Decision:** **DEPLOYMENT BLOCKED** (Halted at Pre-Deployment Database Safety Gate)  

---

## 1. EXECUTIVE SUMMARY & OBJECTIVE

The primary objective of Stage 5.4.2 was to execute the first controlled public staging deployment of SocialPilot AI on Render using the unified-container architecture (`Dockerfile.unified`), while strictly enforcing:
1. Complete database isolation between environments.
2. Protection of existing production data.
3. Zero-cost ($0.00 / no credit card) operation.
4. Absolute integrity and truthfulness in verification results (no fabricated metrics or false claims of success).

### Deployment Gate Outcome
During the mandatory **Pre-Deployment Database Safety Gate (Section 1)**, an exhaustive audit and live TLS query of the TiDB Serverless cluster revealed that:
- **No isolated staging database exists** on the TiDB cluster.
- The only user databases present are `socialpilot` (designated production, 59 tables) and `socialpilot_migration_test` (migration test fixture, 59 tables).
- Deploying staging against `socialpilot` would violate environment isolation and endanger production schema and data.
- Reusing `socialpilot_migration_test` without explicit authorization would violate the strict rule prohibiting reuse of test databases.
- The strict safety gate dictates: *"If an isolated staging database is not available, STOP deployment and report the blocker. Do not create, modify or migrate any TiDB database during this initial safety inspection."*

In adherence to this rule, public deployment was **immediately halted**, no migrations were executed, no cloud instances were activated, and the blocker is documented herein.

---

## 2. PRE-DEPLOYMENT DATABASE SAFETY GATE AUDIT

### 2.1 Laravel Database Configuration Audit

Inspection of `config/database.php`:
- Default connection is driven by `env('DB_CONNECTION', 'sqlite')`.
- MySQL / TiDB connection (`mysql`) is configured to consume:
  - `host`: `env('DB_HOST', '127.0.0.1')`
  - `port`: `env('DB_PORT', '3306')`
  - `database`: `env('DB_DATABASE', 'laravel')`
  - `username`: `env('DB_USERNAME', 'root')`
  - `password`: `env('DB_PASSWORD', '')`
  - `options`: PDO SSL CA certificate dynamically attached via `env('MYSQL_ATTR_SSL_CA')`.
- Migration test connection (`tidb_migration_test`) is hard-coded to consume `TIDB_TEST_DB_DATABASE` (defaulting to `socialpilot_migration_test`).

Inspection of local `.env`:
- `DB_CONNECTION=mysql`
- `DB_HOST=gateway01.eu-central-1.prod.aws.tidbcloud.com`
- `DB_PORT=4000`
- `DB_DATABASE=socialpilot` (designated production)
- `TIDB_TEST_DB_DATABASE=socialpilot_migration_test` (test fixture)

Inspection of `.env.render.example`:
- Line 36 specifies: `DB_DATABASE=<TIDB_DATABASE_NAME>`
- Comment at Line 44 explicitly notes: `# SESSION, CACHE & QUEUE — All use the production database (TiDB Serverless)`
- The template does not predefine or assume a staging database name.

### 2.2 Live TiDB Cluster Audit (Read-Only TLS Handshake)

A non-destructive, read-only query was executed against the live TiDB Serverless cluster gateway (`gateway01.eu-central-1.prod.aws.tidbcloud.com:4000`) over TLS.

**Query Executed:** `SHOW DATABASES;`  
**Result:**
```text
EXISTING_DATABASES:
 - INFORMATION_SCHEMA
 - PERFORMANCE_SCHEMA
 - mysql
 - socialpilot
 - socialpilot_migration_test
 - sys
 - test
```

**Table Schema & Volume Inspection (`information_schema.tables`):**
- Database `socialpilot`: **59 tables** present (full production schema, including users, brands, posts, analytics, autopilot, publishing queues, and migrations).
- Database `socialpilot_migration_test`: **59 tables** present (migration test schema from Phase 10 validation).
- Isolated staging database (e.g., `socialpilot_staging`): **DOES NOT EXIST**.

### 2.3 Safety Evaluation & Gate Conclusion

| Candidate Database | Designated Role | Safe for Staging? | Decision | Reason |
|:-------------------|:----------------|:------------------|:---------|:-------|
| `socialpilot` | **Production** | ❌ **UNSAFE** | **REJECTED** | Staging traffic and automated queue testing would directly mutate production records. |
| `socialpilot_migration_test` | **Test Fixture** | ❌ **UNSAFE** | **REJECTED** | Contains historical test artifacts; violates isolation requirements; strict rule prohibits unapproved reuse. |
| `socialpilot_staging` | **Staging** | ⚠️ **DOES NOT EXIST** | **BLOCKED** | Must be provisioned before staging deployment can proceed. Creation during initial inspection is explicitly prohibited. |

**Gate Status: BLOCKED.** Deployment stopped prior to container spin-up or schema migration.

---

## 3. RENDER ACCOUNT & FREE-TIER VERIFICATION

### 3.1 Zero-Cost ($0.00 / No Credit Card) Assessment

- **Plan:** Render Free Web Service.
- **Credit Card Requirement:** None. Free tier services can be launched without adding payment details.
- **Compute Resources:** 0.1 CPU, 512 MB RAM shared cgroup.
- **Monthly Usage Allowance:** 750 free instance hours per calendar month per workspace.
- **Inactivity Spin-Down:** Render suspends free web services after 15 minutes of HTTP inactivity. Cold starts take 50–90 seconds.

### 3.2 Duty-Cycle & Instance Hour Calculations

As finalized in Stage 5.4.1, **Strategy C (Every 25 min, 08:00–22:00 UTC)** is the approved operations-cycle scheduler pattern:
- Daily active duration: 14 hours (08:00–22:00 UTC).
- Nightly tail: 15 minutes (sleep-down delay after 22:00 UTC).
- Morning cold-start: ~90 seconds.
- Daily consumption: ~14.3 instance hours.
- 30-day monthly consumption: ~432 hours.
- 31-day monthly consumption: ~446 hours.
- Safety buffer (against 750 h): **304 hours (40% buffer)**.
- Suspension risk: **Zero**.

---

## 4. UNIFIED CONTAINER CONFIGURATION & RUNTIME READINESS

The unified container artifacts are verified and ready for deployment once database isolation is established:

### 4.1 Build & Runtime Components (`Dockerfile.unified`)
- **Stage 1 (`frontend-builder`):** `node:20-alpine` compiles production Vite/Tailwind assets.
- **Stage 2 (`production`):** `php:8.2-fpm-bookworm` provides PHP 8.2 runtime, Python 3 virtual environment (`/opt/venv`), Nginx reverse proxy, and Supervisor daemon.
- **Extensions:** `pdo_mysql`, `pdo_sqlite`, `bcmath`, `gd`, `intl`, `opcache`, `zip`, `pcntl`.
- **Composer:** Optimised with `--no-dev --optimize-autoloader`.
- **Exclusions:** `.dockerignore` excludes all `.env*` files, local caches, git histories, and tests.

### 4.2 Supervisor Process Architecture
```
                   Render Edge Proxy (HTTPS)
                               │
                       [Port $PORT] (HTTP)
                               │
                               ▼
                        Nginx (1.22+)
                               │
            ┌──────────────────┴──────────────────┐
     (Web requests)                        (Loopback)
            │                                     │
            ▼                                     ▼
        PHP-FPM                             FastAPI Engine
     (127.0.0.1:9000)                      (127.0.0.1:8080)
      pm.max_children = 2                   Uvicorn (1 worker)
      memory_limit = 128M                   Internal Token Auth
            │                                     ▲
            └──────────(HTTP Internal)────────────┘
```

- **FastAPI Loopback Isolation:** Uvicorn strictly binds to `127.0.0.1:8080`. It is physically unreachable from the public internet; only PHP-FPM communicates with it.
- **Nginx Dynamic Port Binding:** `docker/docker-entrypoint.sh` inspects the Render-injected `$PORT` variable and dynamically writes the Nginx configuration on container boot.
- **Proxy Trusting:** `bootstrap/app.php` includes `$middleware->trustProxies(at: '*')`, ensuring HTTPS detection, secure cookies, and correct IP forwarding behind Render's edge proxy.

---

## 5. DATABASE MIGRATION AUDIT (GATE 5)

In accordance with strict safety mandates:
- **Zero migrations were executed against TiDB.**
- Neither `socialpilot` nor `socialpilot_migration_test` was touched, altered, or migrated.
- Destructive commands (`migrate:fresh`, `migrate:rollback`, `migrate:reset`) remain strictly banned.
- All 29 migrations remain validated and passing on the local SQLite development baseline.

---

## 6. POST-DEPLOYMENT ACCEPTANCE TEST MATRIX

Because deployment was halted at the pre-deployment database safety gate to prevent corruption of production data, live HTTP acceptance tests on the public URL could not be performed.

In strict compliance with the rule *"Distinguish successful tests from tests that could not be performed. Do not invent successful results"*, all post-deployment tests are documented accurately below:

| # | Acceptance Test Case | Status | Verified Method / Failure Reason |
|:--|:---------------------|:-------|:---------------------------------|
| 1 | Public homepage returns HTTP 200 | ⏸️ **NOT PERFORMED** | Halted: Public staging service not provisioned due to database safety gate. |
| 2 | `/up` health endpoint returns HTTP 200 | ⏸️ **NOT PERFORMED** | Halted: Public staging service not provisioned. |
| 3 | `/api/v1/health` reports healthy DB connectivity | ⏸️ **NOT PERFORMED** | Halted: Public staging service not provisioned. |
| 4 | `/api/v1/health/ready` returns ready | ⏸️ **NOT PERFORMED** | Halted: Public staging service not provisioned. |
| 5 | HTTPS works correctly (TLS termination) | ⏸️ **NOT PERFORMED** | Halted: Public staging service not provisioned. |
| 6 | User registration and login work | ⏸️ **NOT PERFORMED** | Halted: Requires live staging database. |
| 7 | Sanctum authentication token generation works | ⏸️ **NOT PERFORMED** | Halted: Requires live staging database. |
| 8 | Unauthorized requests are rejected (HTTP 401) | ⏸️ **NOT PERFORMED** | Halted: Public staging service not provisioned. |
| 9 | Laravel communicates with Python internally | ⏸️ **NOT PERFORMED** | Halted: Public staging service not provisioned. |
| 10 | Authenticated content generation works | ⏸️ **NOT PERFORMED** | Halted: Requires live staging database and Python engine. |
| 11 | Generated content is persisted to DB | ⏸️ **NOT PERFORMED** | Halted: Requires live staging database. |
| 12 | Queue processing drains jobs | ⏸️ **NOT PERFORMED** | Halted: Requires live staging database. |
| 13 | Operations-cycle authentication works | ⏸️ **NOT PERFORMED** | Halted: Public staging service not provisioned. *(Verified locally in L-02)* |
| 14 | Duplicate ops-cycle requests rejected (HTTP 429) | ⏸️ **NOT PERFORMED** | Halted: Public staging service not provisioned. *(Verified locally in L-03)* |
| 15 | Restart recovery works after sleep | ⏸️ **NOT PERFORMED** | Halted: Public staging service not provisioned. |

### Pre-Deployment Local Verification Reference (Passing Baseline)
For technical reference, the underlying backend logic for tests 8, 12, 13, and 14 was previously proven functional in local unit and integration tests:
- `L-01` (Ops cycle rejects unauthenticated requests with HTTP 401): ✅ **PASS** (`InternalOpsCycleApiTest`)
- `L-02` (Ops cycle accepts valid bearer token with HTTP 200): ✅ **PASS** (`InternalOpsCycleApiTest`)
- `L-03` (Ops cycle atomic cache lock prevents concurrent execution with HTTP 429): ✅ **PASS** (`InternalOpsCycleApiTest`)
- `L-04` (Ops cycle executes bounded queue ≤10 jobs, ≤25s execution): ✅ **PASS** (`InternalOpsCycleApiTest`)

---

## 7. STORAGE & RUNTIME LIMITATIONS DISCLOSURE

For any subsequent staging deployment utilizing the initial configuration:

1. **Ephemeral Filesystem Limitation:**
   - On Render Free Tier, container storage is completely ephemeral.
   - Any files written to `storage/app/` (user media uploads, AI generated images, storyboard scene clips, analytics exports) will **disappear upon container restart, redeploy, or idle sleep**.
   - Database metadata for media assets will persist in TiDB Serverless, but direct file downloads will fail after a restart until cloud object storage (`league/flysystem-aws-s3-v3` with Supabase S3) is installed and configured.
2. **Duty-Cycle Scheduler Jitter:**
   - Under Strategy C, scheduled posts and autopilot execution will have a timing variance of up to **25 minutes** during active hours (08:00–22:00 UTC) and will not publish during off-hours (22:00–08:00 UTC).
3. **Cold Start Latency:**
   - Inbound HTTP visits after 15 minutes of inactivity will experience a **50–90 second delay** while Render pulls the image and initializes Supervisor, PHP-FPM, Uvicorn, and Nginx.

---

## 8. REMEDIATION PLAN TO UNBLOCK DEPLOYMENT

To safely unblock Stage 5.4.2 and achieve full public deployment:

### Step 1: Provision Isolated Staging Database
Execute the following non-destructive administration statement on the TiDB Serverless cluster (via TiDB Cloud Console or authorized DBA tool):
```sql
CREATE DATABASE socialpilot_staging CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

### Step 2: Configure Staging Environment Variables on Render
Create the Render Web Service pointing to `Cla-renc/socialpilot-ai` (`Dockerfile.unified`) with the following environment variables:
```ini
APP_NAME="SocialPilot AI (Staging)"
APP_ENV=production
APP_DEBUG=false
APP_KEY=<generated-32-byte-base64-key>
APP_URL=https://<app-name>.onrender.com

DB_CONNECTION=mysql
DB_HOST=gateway01.eu-central-1.prod.aws.tidbcloud.com
DB_PORT=4000
DB_DATABASE=socialpilot_staging
DB_USERNAME=<tidb-username>
DB_PASSWORD=<tidb-password>
MYSQL_ATTR_SSL_CA=/etc/ssl/certs/ca-certificates.crt

SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database
FILESYSTEM_DISK=local
MEDIA_STORAGE_DISK=local

AI_ENGINE_URL=http://127.0.0.1:8080
AI_ENGINE_INTERNAL_TOKEN=<secure-random-token>
TREND_ENGINE_URL=http://127.0.0.1:8080
TREND_ENGINE_INTERNAL_TOKEN=<secure-random-token>
INTERNAL_CRON_SECRET=<secure-random-token>

FORCE_HTTPS=true
CORS_ALLOWED_ORIGINS=https://<app-name>.onrender.com
SANCTUM_STATEFUL_DOMAINS=<app-name>.onrender.com
```

### Step 3: Execute Controlled Staging Migrations
Once the container boots, open the Render One-Off Shell or terminal and run:
```bash
php artisan migrate --force
```
Verify that all 29 migrations create tables strictly within `socialpilot_staging`, leaving `socialpilot` (production) completely untouched.

### Step 4: Execute Live Acceptance Tests
Run the 15 acceptance tests against the live `https://<app-name>.onrender.com` URL and record production metrics.

---

## 9. AUDIT SUMMARY & FINAL DECISION

- **Public Staging URL:** `None` (Deployment paused at safety gate).
- **Deployment Commit:** `98b3587` (Baseline).
- **Database Isolation Evidence:** `socialpilot` (59 tables) and `socialpilot_migration_test` (59 tables) exist; `socialpilot_staging` does not exist.
- **Migration Results:** 0 migrations executed. Production and test databases preserved untouched.
- **Free-Tier Viability:** Strategy C confirmed safe (446 h max / 750 h allowance).
- **Final Decision:** **DEPLOYMENT BLOCKED**

*Audit completed in accordance with strict Phase 11 safety, security, and verification guidelines.*
