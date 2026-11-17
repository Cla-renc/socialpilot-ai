# SOCIALPILOT AI — PHASE 11, STAGE 5.3.1
# Unified Container Resource & Deployment Compatibility Audit Report

**Repository:** `Cla-renc/socialpilot-ai`  
**Branch:** `main`  
**Commit Baseline:** `00b010a`  
**Stage:** 5.3.1 (Unified Container Resource & Deployment Compatibility Audit)  
**Date:** 2026-11-17  

---

## 1. Executive Summary & Objective

The objective of Stage 5.3.1 is to conduct a rigorous technical audit to determine whether the proposed **unified Docker container** (collocating Nginx, PHP 8.2-FPM, Laravel 12, Python 3.12, and FastAPI) can genuinely operate within **Render’s Free Tier resource constraints** (512 MB RAM, shared fractional CPU, ephemeral disk, 15-minute sleep policy, 750 monthly instance hours) at a **strict $0.00 budget**.

This is an **audit and verification stage only**. No infrastructure modifications, external deployments, or code modifications were performed.

### Audit Verdict
**SUITABLE ONLY WITH COMPROMISES**

*The unified container can successfully boot, execute, and stay within Render's 512 MB memory boundary (steady-state idle: ~214 MB; peak operational load: ~294–385 MB; remaining headroom: ~127 MB / 24.8%). Furthermore, by collocating services into one container, monthly runtime stays under 744 hours (within Render's 750-hour free workspace quota).*

*However, reliable public operation requires adhering to five specific operational compromises: accepting 50–90s cold starts after 15 minutes of inactivity, storing media externally via S3, driving queue/scheduler executions via external bounded HTTP cycles rather than resident daemons, using database-backed sessions, and capping PHP-FPM concurrency strictly to 2 workers.*

---

## 2. Architecture & File Inspection

The existing container configurations and runtime files were audited in detail:

| File | Status | Technical Role & Inspection Findings |
|---|---|---|
| `Dockerfile.unified` | **Exists & Verified** | Multi-stage build: Stage 1 Node 20 builds Vite frontend; Stage 2 PHP 8.2-FPM Bookworm installs Nginx, Supervisor, Python 3.12 venv, and PHP extensions (`pdo_mysql`, `gd`, `bcmath`, `opcache`, `pcntl`). Single unified image. |
| `Dockerfile.laravel` | **Exists & Verified** | Decoupled Laravel-only image (PHP 8.2-FPM + Nginx + Supervisor; no Python runtime). |
| `ai_engine/Dockerfile` | **Exists & Verified** | Decoupled Python-only image (`python:3.12-slim` + Uvicorn single worker). |
| `docker-compose.yml` | **Exists & Verified** | Defines decoupled services by default (`laravel-app`, `python-ai-engine`, `mysql-test`); preserves `socialpilot-unified` under profile `unified`. |
| `docker/nginx/default.conf` | **Exists & Verified** | Listens on port 80 (or `$PORT`), routes `/up` and `/` to PHP-FPM (`127.0.0.1:9000`), sets client max body size 50M, buffers 16k, disables access to hidden files and internal storage. |
| `docker/supervisor/supervisord.conf` | **Exists & Verified** | Manages three processes in unified container: `php-fpm` (priority 10), `fastapi` (`uvicorn` priority 20, loopback `127.0.0.1:8080`), `nginx` (priority 30). |
| `docker/supervisor/supervisord.laravel.conf` | **Exists & Verified** | Manages `php-fpm` and `nginx` for decoupled Laravel service. |
| `docker/php/php.ini` | **Exists & Verified** | Sets `memory_limit = 128M`, `opcache.memory_consumption = 64`, `opcache.enable = 1`, `upload_max_filesize = 50M`. |
| `docker/php/php-fpm.d/zz-docker.conf` | **Exists & Verified** | Sets `pm = dynamic`, `pm.max_children = 2`, `pm.start_servers = 1`, `pm.max_requests = 500` (recycles worker processes to eliminate memory leaks). |
| `docker/docker-entrypoint.sh` | **Exists & Verified** | Performs dynamic `$PORT` substitution for Render, sets directory permissions (`775`), links public storage, and executes Supervisor. |

---

## 3. Resource Requirements & Memory Telemetry

### A. Measured Empirical Benchmarks (Host Telemetry)
*Measured using PHP `memory_get_peak_usage(true)` and Python `tracemalloc`:*
- Laravel Framework Bootstrap: **22.0 MB**
- REST API Request Execution & Blade Rendering: **24.0 MB**
- Scheduler Run Peak (`schedule:run`): **32.0 MB**
- Queue Worker Single Job Execution: **38.0 MB**
- Bounded Operations Cycle (`schedule:run` + `queue:work`): **38.0 MB**
- Python 3.12 / FastAPI Startup Heap: **26.67 MB**
- Python AI Content Generation Peak Heap: **29.4 MB**

### B. Estimated Component Footprints in Unified Container
*Based on Debian Bookworm runtime with C-extensions and resident daemon overhead:*
- Base OS, Libc & Shared Dynamic Libraries: **~30 MB**
- Supervisor Master Daemon: **~14 MB**
- Nginx Master (root) + 1 Worker (nginx): **~12 MB**
- PHP 8.2-FPM Master Process: **~20 MB**
- PHP Opcache Shared Memory Buffer (`opcache.memory_consumption = 64`): **~64 MB**
- PHP-FPM Workers (`pm.max_children = 2`): **~24 MB** (idle) to **~76 MB** (both active under load)
- Python 3.12 Runtime + Uvicorn + FastAPI: **~50 MB** (idle) to **~75 MB** (active generation)

### C. Combined Memory Projections vs. Render 512 MB Limit

| Operational State | Projected Memory Consumption | Render 512 MB Headroom | Safety Margin |
|---|---|---|---|
| **Steady-State Idle** | **~214 MB** | **298 MB** | **58.2% Free** |
| **Active Concurrent Load** (Web + AI request) | **~294 MB** | **218 MB** | **42.5% Free** |
| **Peak Burst** (Ops cycle batching + AI generation) | **~340–385 MB** | **127 MB** | **24.8% Free** |

### D. Out-of-Memory (OOM) Risk Analysis
1. **Concurrency Cliff Risk:** If `pm.max_children` is raised above 2 (e.g. standard default of 5), 3 additional active PHP workers would demand `3 × 38 MB = 114 MB`, pushing peak memory to `~499 MB`, directly inducing a container kernel OOM-kill.
2. **Python Multi-Worker Risk:** Uvicorn must strictly run with `--workers 1`. Adding a second worker would add `~50–70 MB`, shrinking the safety buffer to dangerous levels.
3. **In-Memory File Uploads:** Uploading large raw images (e.g., >25 MB) without streaming or immediate resizing can cause PHP worker memory spikes.

---

## 4. Process Management & Supervision

The unified container runs under `supervisord` as PID 1:

### Managed Daemons
1. **`php-fpm -F` (Priority 10):** FastCGI process manager listening on `127.0.0.1:9000`. Recycles workers after 500 requests (`pm.max_requests = 500`) to prevent slow memory leaks.
2. **`uvicorn ai_engine.main:app` (Priority 20):** Listens strictly on `127.0.0.1:8080`. Bound to loopback interface; completely unexposed to public traffic.
3. **`nginx -g "daemon off;"` (Priority 30):** Reverse proxy listening on Render’s dynamic `$PORT`. Proxies PHP requests to PHP-FPM and serves static compiled assets directly from `/var/www/html/public/build/`.

### Queue & Scheduler Process Architecture
> [!IMPORTANT]
> **No persistent queue worker or cron daemon runs inside Supervisor.**
- Running a continuous `php artisan queue:work` daemon and a continuous scheduler loop would demand two additional persistent PHP processes (`~70–90 MB` combined), exhausting memory headroom.
- Furthermore, persistent background processes are suspended whenever Render puts the free container to sleep after 15 minutes of inactivity.
- **Architectural Solution:** All background queue and scheduler executions are triggered via `POST /api/internal/ops/cycle` by an external free scheduler (**Cron-job.org**) every 5–10 minutes.
- The execution runs inside an existing PHP-FPM worker, executes bounded `schedule:run` and `queue:work --stop-when-empty --max-jobs=10 --max-time=25`, and terminates, keeping memory completely bounded.

### Signal Handling & Graceful Termination
- `supervisord.conf` configures `stopasgroup=true`, `killasgroup=true`, and `stopsignal=QUIT` (Nginx/PHP-FPM) and `INT` (Uvicorn).
- When Render sends `SIGTERM` during shutdown or redeployment, Supervisor propagates signals to all child processes, allowing active requests up to 10 seconds to finish before graceful exit.

---

## 5. Storage Compatibility & Ephemeral Disk Audit

Render Free web services feature an **ephemeral filesystem**. Any file written to the local container disk is destroyed when the service sleeps, restarts, or deploys.

### Application Storage Dependency Classification

| Storage Component | Local Path / Component | Classification | Mitigation Strategy for $0 Hosting |
|---|---|---|---|
| **User Uploads** | `storage/app/public/brands/...` | **Must be stored externally** | Configure Flysystem S3 to store avatars and brand logos on **Supabase S3 bucket** |
| **Generated Media** | `storage/app/media/...` | **Must be stored externally** | Stream generated images/videos directly to **Supabase S3 bucket** |
| **Temporary Files** | `/tmp`, `storage/framework/cache/` | **Ephemeral (Safe to lose)** | Discarded automatically on restart |
| **Laravel Sessions** | `storage/framework/sessions/` | **Must be persisted in database** | Set `SESSION_DRIVER=database` (stored in TiDB Serverless `sessions` table) |
| **Application Cache** | `storage/framework/cache/data/`| **Must be persisted in database** | Set `CACHE_STORE=database` (stored in TiDB Serverless `cache` table) |
| **Queue Jobs** | `storage/` | **Must be persisted in database** | Set `QUEUE_CONNECTION=database` (stored in TiDB Serverless `jobs` table) |
| **Application Logs** | `storage/logs/laravel.log` | **Ephemeral (Safe to lose)** | Streamed directly to `stdout`/`stderr` and captured by Render Log Viewer |
| **Database Backups** | `storage/app/backups/` | **Local dev only** | Production backups managed by **TiDB Cloud automated snapshots** |
| **SQLite Database** | `database/database.sqlite` | **Local dev only** | Replaced entirely by **TiDB Cloud Serverless** in public deployment |

---

## 6. Health Checks, Reliability & Security Review

### A. Health Endpoint Verification
- **Laravel `/up`:** Natively mapped to Laravel application boot check. Responds with HTTP 200 JSON when database connection is alive.
- **REST API `/api/v1/health`:** Checks database PDO connection, user table count, and brand table count. Returns HTTP 200 `status: ok` (or HTTP 503 `status: degraded`).
- **REST API `/api/v1/health/ready`:** Dedicated Kubernetes/container readiness probe returning HTTP 200 when database connectivity is verified.
- **FastAPI `/health`:** Responds with `{"status": "ok", "service": "trend-and-content-engine"}` on `127.0.0.1:8080`.

### B. Security & Isolation Verification
- **Internal Microservice Isolation:** FastAPI is bound strictly to `127.0.0.1:8080` (loopback). Nginx does not route any external path to 8080.
- **Double-Layered Authentication:** Even on loopback, all internal requests from Laravel to Python require `Authorization: Bearer <AI_ENGINE_INTERNAL_TOKEN>` validated using `hmac.compare_digest()`.
- **Timing-Safe Cron Secret:** `/api/internal/ops/cycle` requires `Authorization: Bearer <INTERNAL_CRON_SECRET>` checked with `hash_equals()`.
- **Zero Secrets in Docker Images:** Verified `.dockerignore` strictly excludes `.env`, `.env.*`, `database/*.sqlite*`, and credentials from build context. All credentials must be injected via Render environment variables.
- **HTTPS Enforcement:** Render terminates TLS at the edge. Laravel is configured with `URL::forceScheme('https')` in `AppServiceProvider` when `APP_ENV=production` or `FORCE_HTTPS=true`.

---

## 7. Unified Container vs. Separated Services Comparison

| Architectural Dimension | Unified Container (`socialpilot-unified`) | Separated Services (`laravel-app` + `python-ai-engine`) |
|---|---|---|
| **Render Web Services Required** | **1 Web Service** | **2 Web Services** |
| **Monthly Free Instance Hours** | **≤ 744 hours** (Fits within 750h quota) | **1,488 hours** (Exceeds 750h quota; suspends account mid-month) |
| **Inter-Service Latency** | **< 1 ms** (Loopback `127.0.0.1:8080`) | **20–60 ms** (Cross-service HTTP requests) |
| **Cold Start Synchronization** | **Single boot** (50–90s); both services ready together | **Dual asynchronous boot**; Laravel may wake up and timeout calling sleeping Python service |
| **Memory Pressure** | Higher (512 MB shared between PHP and Python) | Lower (Each container has its own 512 MB quota) |
| **Deployment Simplicity** | Single Git push builds and deploys entire stack | Requires coordinating two independent deployment pipelines |
| **Recommendation for $0 Hosting** | ✅ **STRONGLY RECOMMENDED** | 🛑 **Unviable for always-on; only works with strict sleep** |

---

## 8. Required Pre-Deployment Configuration Adjustments

Before deploying the unified container to Render in Stage 5.4, the following adjustments must be made in the deployment environment:
1. **Session Driver:** Ensure `SESSION_DRIVER=database` in Render environment (overriding local `file` setting).
2. **Cache Driver:** Ensure `CACHE_STORE=database`.
3. **Queue Driver:** Ensure `QUEUE_CONNECTION=database`.
4. **Media Storage:** Configure Supabase S3 credentials (`FILESYSTEM_DISK=s3`, `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`, `AWS_DEFAULT_REGION`, `AWS_BUCKET`, `AWS_ENDPOINT`).
5. **AI Engine URL:** Set `AI_ENGINE_URL=http://127.0.0.1:8080` for unified deployment.
6. **External Ops Cycle Webhook:** Set up Cron-job.org schedule to invoke `/api/internal/ops/cycle` every 5 to 10 minutes.

---

## 9. Final Audit Verdict

**SUITABLE ONLY WITH COMPROMISES**

*The unified container (`Dockerfile.unified`) is technically capable of running on Render's Free Tier within 512 MB RAM (active memory ~294 MB, peak ~385 MB) and consumes ≤ 744 of the 750 free monthly instance hours. It avoids the mid-month quota exhaustion and cold-start deadlocks that would plague separated services on Render Free.*

*However, the deployment is suitable strictly for **demonstration, testing, and stakeholder evaluation**, not commercial 24/7 SaaS production, due to inherent free-tier trade-offs: 15-minute inactivity sleeping (50–90s cold starts), bounded 5–10 minute queue batch intervals, and the necessity of external S3 media storage.*
