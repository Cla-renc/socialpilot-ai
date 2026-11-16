# SOCIALPILOT AI — PRODUCTION HOSTING & INFRASTRUCTURE COMPARISON

**Date:** November 16, 2026  
**Objective:** Independent evaluation of cloud infrastructure providers to host SocialPilot AI (Laravel Web + API, Python FastAPI AI Engine, MySQL Database, Object Storage, and Background Queue/Scheduler Workers) under a **strict zero-unauthorized-cost policy**.

---

## 1. COMPONENT ARCHITECTURAL REQUIREMENTS

SocialPilot AI consists of 5 core infrastructure workloads:
1. **Laravel Web Application & REST API (PHP 8.2 + Nginx):** Long-running web server handling user sessions, Blade UI, authentication (Sanctum/Breeze), and API routes.
2. **Python AI Engine (FastAPI + Uvicorn):** Internal API service for trend scoring, content generation, media formatting, and scheduling heuristics.
3. **Persistent Database:** Relational database strictly compatible with MySQL 8.0+ migrations, foreign keys, and JSON columns.
4. **Persistent Media Storage:** S3-compatible or asset CDN storage for generated quote cards, user images, and storyboard videos.
5. **Continuous Background Processing:** A persistent queue worker (`php artisan queue:work`) and cron runner (`php artisan schedule:run`) to execute scheduled publishing, autopilot queues, token refreshes, and analytics collection without falling asleep.

---

## 2. HOSTING PROVIDER EVALUATION MATRIX

| Provider & Service | Workloads Supported | Free Tier Available? | Credit Card Required? | Sleep / Spin-Down Inactivity | Background Workers Free? | Database Free? | Storage Free? | Zero-Cost Verdict |
|---|---|---|---|---|---|---|---|---|
| **Render** | Web Services, PostgreSQL | Yes (Hobby Plan) | **No** (for Web) | **Yes** (sleeps after 15 min; ~50s cold start) | **NO** (Starter $7/mo minimum required) | **NO** (Postgres only; deletes after 30 days) | Ephemeral only (0 GB persistent) | **UNSUITABLE** for Workers & Database |
| **Railway** | Containers, Databases | Trial only ($5 one-time, 30 days) | **No** (for trial) | None during trial | Trial only; reverts to limited $1/mo credit | Trial only; no permanent free MySQL | Ephemeral | **UNSUITABLE** (Trial expires; requires paid plan) |
| **Koyeb** | Docker Web Services | Yes (1 Free Eco Instance) | **YES** ($29 temporary pre-auth hold) | **Yes** (sleeps after 1 hr inactivity) | **NO** (Worker service requires paid plan) | None | 2 GB SSD (ephemeral) | **UNSUITABLE** (Requires payment card & pre-auth hold) |
| **Fly.io** | Apps, LiteFS | Free allowance exists | **YES** (Card mandatory during signup) | Can auto-stop/start | Possible within allowance | Postgres only (no native MySQL) | 3 GB persistent volume | **UNSUITABLE** (Requires payment card) |
| **TiDB Serverless (PingCAP)** | MySQL-compatible Database | **YES (Permanent)** | **NO** (Free up to 5 clusters) | **No sleep** (instant serverless auto-scale) | N/A (Database only) | **YES** (5 GiB row storage, 50M RUs/mo free) | 5 GiB | **RECOMMENDED & FULLY COMPATIENT** |
| **Cloudinary** | Media Storage & CDN | **YES (Permanent)** | **NO** | N/A (Asset storage & CDN) | N/A | N/A | **YES** (25 monthly credits / 25 GB storage) | **RECOMMENDED & FULLY COMPLIANT** |
| **Cloudflare R2** | S3-compatible Storage | 10 GB/mo free allowance | **YES** (Payment method required) | N/A | N/A | D1/KV (SQL/KV, not MySQL wire) | 10 GB free | **UNSUITABLE** (Card required on signup) |
| **Self-Hosted VPS / Docker Compose** | All 5 workloads in one container stack | Depends on existing host | N/A | **No sleep** (continuous 24/7 execution) | **YES** (Full queue worker + cron) | **YES** (Full MySQL 8.0) | Local disk / volume mount | **RECOMMENDED FOR FULL STACK** |

---

## 3. IN-DEPTH PROVIDER ANALYSIS

### A. Render (render.com)
- **Free Allowance:** 1 Free Web Service (512 MB RAM, 0.5 CPU, 750 free instance hours/month).
- **Critical Limitations:**
  1. *Sleep Behavior:* Web services automatically spin down after 15 minutes of zero inbound traffic. Subsequent cold starts take 45–60 seconds.
  2. *Worker Blocker:* Render **does not support Background Workers on the free tier**. Any background worker process requires a paid Starter plan ($7/month minimum).
  3. *Database Blocker:* Render Postgres free databases are hard-deleted 30 days after creation. No free MySQL is offered.
- **Verdict:** Render is capable of hosting a stateless Web API or Python API for demo purposes, but **cannot support SocialPilot AI's continuous queue workers, scheduler, or persistent database without paid upgrades**.

### B. Railway (railway.app)
- **Pricing Model:** Provides a 30-day initial trial with a one-time grant of $5 in usage credit.
- **Critical Limitations:** After the 30-day trial expires, the account reverts to a very limited $1/month credit plan that cannot sustain continuous multi-service operation, custom domains, or production background queues. Moving to the Hobby plan requires $5/month plus usage fees and a credit card.
- **Verdict:** Unsuitable for permanent production hosting under a zero-cost policy.

### C. Koyeb (koyeb.com)
- **Pricing Model:** Offers 1 free Eco instance with 512 MB RAM and 0.1 vCPU.
- **Critical Limitations:** Koyeb strictly requires a valid credit card at registration and places an immediate **$29 temporary pre-authorization hold**. Furthermore, Koyeb free instances are limited to Web Services that scale to zero after 1 hour of inactivity; background worker instances are strictly excluded from the free tier.
- **Verdict:** Violates both the no-credit-card and continuous-worker requirements.

### D. TiDB Serverless (Cloud MySQL Database)
- **Pricing Model:** Permanent free tier without requiring a credit card or payment information.
- **Free Allowance:** Up to 5 free database clusters per organization, 5 GiB of row-based storage per cluster, 250 million Request Units (RUs) pooled per month (50M RUs per cluster).
- **Compatibility:** 100% MySQL 8.0 wire protocol and syntax compatible. Supports all 27 SocialPilot AI Laravel migrations, JSON columns, foreign key constraints, transactions, and indexing without modification.
- **Verdict:** **Selected as the primary production database provider.**

### E. Cloudinary (Persistent Media Storage)
- **Pricing Model:** Permanent free tier without requiring a credit card.
- **Free Allowance:** 25 monthly credits (up to 25 GB managed storage, 25,000 image/video transformations, or 25 GB high-performance CDN bandwidth).
- **Features:** Soft limits that notify rather than automatically charging. Direct HTTP API and official PHP SDK.
- **Verdict:** **Selected as the primary zero-cost persistent media storage provider.**

---

## 4. RECOMMENDED PRODUCTION DEPLOYMENT STRATEGY

Because public PaaS providers (Render, Railway, Koyeb, Fly.io) either charge for continuous background workers, require credit cards, or spin down web services after 15 minutes of inactivity, the optimal production architecture follows a **Decoupled Zero-Cost Topology**:

```
+-----------------------------------------------------------------------------------+
|                         SOCIALPILOT AI ARCHITECTURE                               |
+-----------------------------------------------------------------------------------+
|                                                                                   |
|  1. DATABASE TIER (Cloud Serverless)                                              |
|     - Provider: TiDB Serverless (PingCAP)                                         |
|     - Cost: $0.00 / month (No credit card required)                               |
|     - Capacity: 5 GiB storage, 50,000,000 Request Units/month                     |
|     - Protocol: MySQL 8.0 TLS encrypted                                           |
|                                                                                   |
|  2. MEDIA STORAGE TIER (Cloud CDN)                                                |
|     - Provider: Cloudinary / S3-compatible storage                                |
|     - Cost: $0.00 / month (No credit card required)                               |
|     - Capacity: 25 GB media storage + global CDN delivery                         |
|                                                                                   |
|  3. AI INFERENCE TIER (Cloud Multi-Provider)                                      |
|     - Providers: Groq Cloud (llama-3.1-8b-instant free tier)                       |
|                  OpenRouter (meta-llama/llama-3.1-8b-instruct:free)               |
|                  Ollama (local inference fallback)                                |
|     - Cost: $0.00 / month (Strict zero-cost policy)                               |
|                                                                                   |
|  4. APPLICATION & COMPUTE TIER (Production Container / Dedicated Host)            |
|     - Configuration: Docker Compose with Supervisord                              |
|     - Services: Laravel Web + API (PHP 8.2 FPM / Nginx)                           |
|                 Python AI Engine (FastAPI Uvicorn)                                |
|                 Continuous Queue Worker (php artisan queue:work)                  |
|                 Automated Scheduler Cron (php artisan schedule:run)               |
|     - Host Options:                                                               |
|       Option A (Zero-Cost Dedicated/VPS): Existing server, homelab, or cloud VM  |
|       Option B (Free Web PaaS + External Webhook Cron): Render Free Web Service    |
|                paired with Cron-Job.org free HTTP ping for scheduled jobs         |
+-----------------------------------------------------------------------------------+
```
