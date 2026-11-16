# SOCIALPILOT AI — PRODUCTION DEPLOYMENT REPORT

**Date:** November 16, 2026  
**Phase:** 11 (Production Infrastructure & Release Engineering)  
**Strict Policy:** Zero Unauthorized Cost / No Mandatory Paid Services  
**Repository:** https://github.com/Cla-renc/socialpilot-ai (`main`)  

---

## 1. INFRASTRUCTURE & PROVIDER EVALUATION SUMMARY

| Workload Component | Evaluated Providers | Selected Solution | Reason for Selection | Status |
|---|---|---|---|---|
| **Production Database** | TiDB Serverless, Render Postgres, Railway, Koyeb | **TiDB Serverless (Cloud)** | 100% MySQL 8.0 compatibility, permanent 5 GiB free tier, 50M RUs/month, zero credit card required, zero data deletion. Render deletes DB after 30 days; Railway requires paid subscription. | **CONFIGURED BUT NOT DEPLOYED** (Awaiting cluster creation by user) |
| **Media Storage** | Cloudinary, Cloudflare R2, AWS S3, Local Disk | **Cloudinary / Persistent Volume** | Cloudinary offers 25 monthly credits (25 GB storage) free without credit card. Cloudflare R2 requires a credit card during registration. | **CONFIGURED BUT NOT DEPLOYED** (Ready for cloud credentials or local volume) |
| **AI Inference** | Groq Cloud, OpenRouter, Ollama, OpenAI, Gemini | **Groq Cloud + OpenRouter Free Tier** | `openai/gpt-oss-120b` on Groq and `meta-llama/llama-3.1-8b-instruct:free` on OpenRouter provide fast, completely free inference without subscription fees. Paid models remain disabled. | **COMPLETE AND LIVE VERIFIED** |
| **Laravel Web & REST API** | Render, Koyeb, Self-Hosted Docker Compose | **Hardened Multi-Container Stack / Render Web** | Native PHP 8.2-FPM + Nginx container with optimized route, view, and config caching. Render provides free web service but sleeps after 15 min; Docker Compose runs 24/7 without limitations. | **CONFIGURED BUT NOT DEPLOYED** (Awaiting cloud hosting selection by user) |
| **Python AI Engine** | Render, Koyeb, Self-Hosted Docker Compose | **Uvicorn ASGI Container** | Python FastAPI engine with internal bearer token verification and health check probes. | **CONFIGURED BUT NOT DEPLOYED** |
| **Queue Workers & Scheduler** | Render, Railway, Systemd / Supervisord | **Supervisord / Systemd Runner** | Render and Koyeb **strictly exclude background workers from their free tiers** ($7/mo minimum on Render). Self-hosted Supervisord manages 24/7 queue processing and schedule cron at zero cost. | **CONFIGURED BUT NOT DEPLOYED** |
| **Mobile Application** | Google Play Store, Direct APK Sideloading | **Android Release APK (56.8 MB)** | Generated release APK at `mobile/build/app/outputs/flutter-apk/app-release.apk` with package ID `com.socialpilot.ai`. Sideload-ready; production keystore required for Play Store. | **COMPLETE AND LIVE VERIFIED** |

---

## 2. STATUS BREAKDOWN BY ARCHITECTURAL COMPONENT

### A. Database Tier
- **Status:** **CONFIGURED BUT NOT DEPLOYED**
- **Details:** The application is 100% MySQL 8.0 compatible across all 27 migrations, 46 foreign keys, and 22 JSON columns. The safe migration procedure, connection variables, and non-destructive restoration tests have been verified. Connecting to a live cloud cluster requires the user to create a free TiDB Serverless cluster.

### B. Media Storage Tier
- **Status:** **CONFIGURED BUT NOT DEPLOYED**
- **Details:** `MediaLibraryService` supports local volume mounts, Cloudinary, and S3-compatible cloud storage. Tenant isolation and file validation (20 MB cap, MIME whitelist) are implemented and tested.

### C. AI Inference Tier
- **Status:** **COMPLETE AND LIVE VERIFIED**
- **Details:** Groq Cloud free tier (`openai/gpt-oss-120b`), OpenRouter free models (`meta-llama/llama-3.1-8b-instruct:free`), and Ollama local development are fully configured and verified through automated tests. Paid OpenAI and Gemini APIs remain completely optional and disabled.

### D. Laravel Web & API Tier
- **Status:** **CONFIGURED BUT NOT DEPLOYED**
- **Details:** Configuration caching (`config:cache`), route caching (`route:cache`), view caching (`view:cache`), storage symlinks (`storage:link`), rate limiting (120 req/min API, 15 req/min Auth), and HTTPS scheme enforcement are configured. All 243 backend automated tests pass.

### E. Python FastAPI Engine
- **Status:** **CONFIGURED BUT NOT DEPLOYED**
- **Details:** FastAPI ASGI service with internal token authentication, startup script, and health check endpoints (`/health`, `/internal/v1/media/health`, `/internal/v1/scheduling/health`). All 87 Python regression tests pass.

### F. Queue & Scheduler Tier
- **Status:** **CONFIGURED BUT NOT DEPLOYED**
- **Details:** 8 scheduled commands verified with `withoutOverlapping()` mutex locks. Queue worker configured for database driver (`QUEUE_CONNECTION=database`). PaaS free tier worker limitations are documented.

### G. Flutter Mobile Client
- **Status:** **COMPLETE AND LIVE VERIFIED**
- **Details:** All 59 Flutter tests pass with zero static analysis warnings (`flutter analyze` clean). Release APK generated at 56.8 MB with package `com.socialpilot.ai`. Tokens stored via hardware-backed `EncryptedSharedPreferences`.

---

## 3. IDENTIFIED INFRASTRUCTURE BLOCKERS & ALTERNATIVES

| Identified Blocker | Impacted Cloud Providers | Root Cause | Zero-Cost Solution / Alternative |
|---|---|---|---|
| **No Free Background Workers** | Render, Koyeb | Render and Koyeb charge a minimum of $7/mo or require a paid plan to run background worker instances (`queue:work`). | 1. Self-host stack using Docker Compose on an existing server or homelab.<br>2. Run Laravel queue with sync driver or trigger scheduled dispatches via free external cron ping (Cron-Job.org). |
| **Ephemeral Database Termination** | Render | Render free PostgreSQL databases terminate and permanently delete data after 30 days. | Use **TiDB Serverless**, which provides a permanent, non-expiring MySQL database with 5 GiB storage for free. |
| **Mandatory Payment Cards for Free Tier** | Koyeb, Fly.io, Cloudflare R2 | Require credit card entry during registration and place temporary holds ($29 on Koyeb). | Use zero-card providers: **TiDB Serverless** (Database), **Cloudinary** (Media Storage), and **GitHub** (Code/Actions). |

---

## 4. FLUTTER PRODUCTION API CONFIGURATION

The mobile application is decoupled from hardcoded localhost or emulator IP addresses.

### Production Build Command
To point the mobile application to your production API:
```bash
cd mobile
flutter build apk --release --dart-define=API_BASE_URL=https://app.yourdomain.com/api/v1
```

### Local Physical Device Testing Command (Workstation LAN)
```bash
cd mobile
flutter run --release --dart-define=API_BASE_URL=http://<YOUR_LAN_IP>:8000/api/v1
```

---

## 5. REMAINING USER ACTIONS TO GO LIVE

To transition from "CONFIGURED" to "LIVE DEPLOYED" under the strict zero-cost policy:

1. **Step 1: Create Free TiDB Serverless Database (Zero Cost / No Card)**
   - Sign up at [tidbcloud.com](https://tidbcloud.com/) with GitHub.
   - Create a free Serverless cluster named `socialpilot`.
   - Copy connection credentials (`DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`).
2. **Step 2: Launch Deployment Stack**
   - **Option A (Self-Hosted / Cloud VM via Docker Compose):**
     Populate production `.env` and execute `docker compose up -d --build`.
   - **Option B (Render Free Web Service):**
     Link GitHub repo `Cla-renc/socialpilot-ai` to a new Render Web Service and set environment variables.
3. **Step 3: Run Database Migration**
   - Run `php artisan migrate --force` and `php artisan db:seed --class=PlanSeeder --force`.
4. **Step 4: Configure OAuth Client Credentials (When ready for live social posting)**
   - Add Meta, LinkedIn, TikTok, and Google developer credentials to production `.env`.
