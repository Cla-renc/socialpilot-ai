# STAGE 5.4.4 — RENDER STAGING DEPLOYMENT REPORT

**Project:** SocialPilot AI  
**Repository:** `Cla-renc/socialpilot-ai`, branch `main`  
**Render Web Service:** `socialpilot-staging`  
**Public Service URL:** `https://socialpilot-staging.onrender.com`  
**Report Date:** 2026-11-18  
**Deployed Commit:** `f26258c` (with `d8337f8` staging preparation verified)  
**Database:** `socialpilot_staging` on TiDB Cloud (eu-central-1)  
**Account:** `AZDARrqgy4MBFGE.staging_app` (least-privilege DML)  
**Plan:** Render Free Tier ($0, no card required)  

---

## 1. EXECUTIVE SUMMARY

The deployment of SocialPilot AI to the Render Staging environment is **complete, verified, and operational**.

All primary runtime layers — **Nginx reverse proxy**, **PHP 8.2-FPM**, **FastAPI Python AI engine**, and **TiDB Cloud TLS database connectivity** — are active and passing live health probes. Live end-to-end user registration and database persistence tests were executed and confirmed.

**Final Status:** **A — Staging deployed and required health checks passed.**

---

## 2. DEPLOYMENT & ENVIRONMENT CONFIGURATION

### 2.1 Service Overview
- **Service Name:** `socialpilot-staging`
- **Region:** Frankfurt (EU)
- **Runtime:** Docker (`Dockerfile.unified`)
- **Health Check Path:** `/up`
- **Instance Tier:** Free ($0)

### 2.2 Configured Environment Variables (Names Only)

All required variables were provisioned directly in the Render dashboard without exposing secrets to git:

| Category | Configured Variable Names | Status |
|:---|:---|:---:|
| **Application Core** | `APP_NAME`, `APP_ENV`, `APP_KEY`, `APP_DEBUG`, `APP_URL`, `FORCE_HTTPS` | ✅ Active |
| **Database** | `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`, `MYSQL_ATTR_SSL_CA` | ✅ Active (TLS) |
| **Driver Storage** | `SESSION_DRIVER`, `CACHE_STORE`, `QUEUE_CONNECTION`, `FILESYSTEM_DISK`, `MEDIA_STORAGE_DISK` | ✅ Active (Database/Local) |
| **Security & CORS** | `CORS_ALLOWED_ORIGINS`, `SANCTUM_STATEFUL_DOMAINS`, `SANCTUM_TOKEN_EXPIRATION_MINUTES` | ✅ Active |
| **AI Engine Bridge** | `AI_ENGINE_URL`, `AI_ENGINE_INTERNAL_TOKEN`, `TREND_ENGINE_URL`, `TREND_ENGINE_INTERNAL_TOKEN` | ✅ Active |
| **Operations & Jobs** | `INTERNAL_CRON_SECRET`, `PUBLISHING_INTERNAL_TOKEN`, `SKIP_CACHE_WARMUP` | ✅ Active |
| **Logging & Mail** | `LOG_CHANNEL`, `LOG_LEVEL`, `MAIL_MAILER` | ✅ Active |

---

## 3. COMPREHENSIVE LIVE HEALTH & API VERIFICATION

All tests were performed via live HTTPS calls against `https://socialpilot-staging.onrender.com`.

| Test ID | Endpoint / Action | Method | Expected | Actual Result | Status |
|:---|:---|:---:|:---|:---|:---:|
| **T-01** | `/up` | `GET` | `HTTP 200` | `HTTP 200 OK` (Application up, 11ms) | ✅ PASS |
| **T-02** | `/` (Web Homepage) | `GET` | `HTTP 200` | `HTTP 200 OK` (Full HTML rendered) | ✅ PASS |
| **T-03** | `/api/v1/health` | `GET` | `status: ok` | `{"status":"ok","checks":{"database":true,"users":true,"brands":true}}` | ✅ PASS |
| **T-04** | `/api/v1/health/ready` | `GET` | `ready: true` | `{"ready":true,"service":"socialpilot-ai"}` (HTTP 200) | ✅ PASS |
| **T-05** | `/api/internal/ops/cycle` (Valid Token) | `POST` | `status: success` | `{"status":"success","scheduler":{"exit_code":0},"queue":{"exit_code":0}}` | ✅ PASS |
| **T-06** | `/api/internal/ops/cycle` (Invalid Token) | `POST` | `HTTP 401` | `HTTP 401 Unauthorized` | ✅ PASS |
| **T-07** | `/api/v1/auth/register` (Live DB Write) | `POST` | `HTTP 201` + token | `User registered successfully` (User ID: 1, Token issued) | ✅ PASS |

---

## 4. SUBSYSTEM STATUS

### 4.1 Web & App Server (Nginx + PHP 8.2-FPM)
- **Status:** **OPERATIONAL**
- FastCGI socket communication between Nginx (`port 10000`) and PHP-FPM (`127.0.0.1:9000`) verified.
- Dynamic `$PORT` binding executed by `docker-entrypoint.sh`.
- Latency: Root response rendered in 11–79ms.

### 4.2 Database Connectivity (TiDB Cloud Serverless)
- **Status:** **OPERATIONAL**
- Connected via `gateway01.eu-central-1.prod.aws.tidbcloud.com:4000`.
- System TLS Certificate (`/etc/ssl/certs/ca-certificates.crt`) applied.
- Authenticated account: `AZDARrqgy4MBFGE.staging_app` (DML only, no DDL).
- Sessions, cache, and users tables successfully verified with live writes.

### 4.3 Python FastAPI (AI Engine)
- **Status:** **OPERATIONAL**
- Process running under Supervisor at `127.0.0.1:8080`.
- Internal token authentication active (`AI_ENGINE_INTERNAL_TOKEN`).
- Uvicorn server started and bound to internal loopback only.

### 4.4 Scheduler & Queue Processing (`/api/internal/ops/cycle`)
- **Status:** **OPERATIONAL**
- Webhook tested with Bearer authentication.
- Scheduled commands executed: `trends:schedule`, `publishing:dispatch-scheduled`.
- Queue worker invoked with `--stop-when-empty`.

---

## 5. RESOLVED DEPLOYMENT OBSTACLES

During initial deployment, three configuration nuances were encountered and resolved:
1. **Root Directory Misconfiguration:** Render's "Root Directory" was initially filled with a subpath. Cleared to repository root with Dockerfile Path set to `./Dockerfile.unified`.
2. **Local Host Fallback (`127.0.0.1`):** `DB_HOST` was initially unset in Render's environment, causing Laravel to default to localhost. Updated to `gateway01.eu-central-1.prod.aws.tidbcloud.com`.
3. **TiDB User Name Prefix Requirement:** TiDB Serverless requires the cluster prefix (`AZDARrqgy4MBFGE.`). Configured `DB_USERNAME=AZDARrqgy4MBFGE.staging_app`.

---

## 6. ACCEPTED STAGING LIMITATIONS

Per the Stage 5.4.3 preparation report, the following free-tier limitations are accepted for staging:
- **Ephemeral Local Storage:** Render free tier instances have an ephemeral filesystem. Media uploads stored locally do not persist across redeployments.
- **Inactivity Sleep:** The free tier spins down after 15 minutes of inactivity. Background queues pause until an incoming request or external cron ping wakes the container (50s cold start).
- **External Cron Schedule:** Duty-cycle crons (e.g. via UptimeRobot / Cron-job.org) are required to ping `/api/internal/ops/cycle` every 25 minutes between 08:00–22:00 UTC.

---

## 7. VERIFIED VS UNVERIFIED MATRIX

| Component / Feature | Verification Method | Status |
|:---|:---|:---:|
| Nginx Reverse Proxy | HTTP GET requests on public URL | **VERIFIED** |
| PHP-FPM Execution | Laravel routing & Blade rendering | **VERIFIED** |
| TiDB TLS Connection | Live PDO queries, user table write | **VERIFIED** |
| Session & Cache Driver | Database session creation & read | **VERIFIED** |
| Operations Webhook Auth | Timing-safe Bearer token check (200 / 401) | **VERIFIED** |
| Artisan Scheduler Run | Scheduled commands triggered via ops cycle | **VERIFIED** |
| User Registration & Auth | API POST `/api/v1/auth/register`, Sanctum token | **VERIFIED** |
| S3 Object Storage | N/A (local ephemeral storage active) | Accepted Staging Limitation |
| Live Social Platform OAuth | N/A (staging redirect URIs pending client apps) | Unverified (Out of Scope) |
| Flutter Mobile Build | Web endpoints verified; mobile APK rebuild pending | Pending Client Build |

---

## 8. FINAL STATUS

### **Result: A — Staging deployed and required health checks passed.**

The SocialPilot AI staging environment on Render is fully deployed, connected to the 59-table TiDB staging database, and actively serving requests.

*Deployment authorization for production has NOT been requested or granted. All production and test databases remain untouched.*
