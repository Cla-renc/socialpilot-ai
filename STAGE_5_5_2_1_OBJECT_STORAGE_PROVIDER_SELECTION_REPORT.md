# Stage 5.5.2.1 — Durable Object Storage Provider Selection & Implementation Plan

**Project:** SocialPilot AI  
**Repository:** `C:\PROJECTS\SOCIALPILOT AI`  
**Target Environment:** Render Staging (`https://socialpilot-staging.onrender.com`) & Future Production  
**Policy Constraint:** Strict $0 / Zero-Cost Policy — **No Credit Card / No Payment Details Required**  
**Assessment Date:** November 2026  
**Document Status:** Final Audit & Architecture Report  

---

## 1. Executive Summary & Recommendation

In Stage 5.4.8, the storage audit confirmed that SocialPilot AI currently operates on Render's **ephemeral local filesystem** (`MEDIA_STORAGE_DISK=local`, `storage/app/private` and `storage/app/public`). Because Render free-tier instances restart on code deploys, configuration updates, and spin-down events, all locally stored user uploads and AI-generated media are erased upon restart.

To achieve production readiness without violating the project's **strict zero-cost policy** ($0 spend, no payment method or credit card required), this evaluation analyzed the industry's leading object storage providers against verified 2026 service terms.

### Key Evaluation Finding:
- **Cloudflare R2** provides generous free tiers (10 GB storage, $0 egress), but **requires a credit card** on file during signup to activate metered billing. Disqualified.
- **Backblaze B2** allows card-free registration and 10 GB storage, but **strictly requires a credit card on file to create or toggle a bucket to Public**. Because social media platforms (Instagram, TikTok, YouTube, X/Twitter, LinkedIn, Facebook) require direct public HTTP/HTTPS media URLs to fetch video/image payloads, private-only storage is insufficient. Disqualified.
- **Supabase Storage** provides **1 GB free storage, 5 GB monthly egress bandwidth, full native S3-compatible API protocol access, and completely card-free public buckets with default hard caps (no overage billing)**.

### Recommendation:
Adopt **Supabase Storage** via its native **S3 Gateway Protocol**. It is the only verified zero-cost provider that enables public media URLs without requiring credit card registration.

**Final Decision:**  
`A — PROVIDER VERIFIED; IMPLEMENTATION PLAN READY.`

---

## 2. Existing Application Storage Architecture Audit

An inspection of the codebase in `c:\PROJECTS\SOCIALPILOT AI` revealed that SocialPilot AI was deliberately designed with clean cloud storage abstraction:

### 2.1 Filesystem Configuration (`config/filesystems.php`)
The application defines a standard `s3` disk driver out-of-the-box:
```php
's3' => [
    'driver' => 's3',
    'key' => env('AWS_ACCESS_KEY_ID'),
    'secret' => env('AWS_SECRET_ACCESS_KEY'),
    'region' => env('AWS_DEFAULT_REGION'),
    'bucket' => env('AWS_BUCKET'),
    'url' => env('AWS_URL'),
    'endpoint' => env('AWS_ENDPOINT'),
    'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
    'throw' => false,
    'report' => false,
],
```
Crucially, `'endpoint'` and `'use_path_style_endpoint'` are already mapped to environment variables, allowing seamless redirection to any S3-compatible gateway without code alterations.

### 2.2 Media Configuration (`config/services.php`)
Media storage settings are decoupled:
```php
'media' => [
    'storage_disk' => env('MEDIA_STORAGE_DISK', 'local'),
    'max_upload_size_mb' => env('MEDIA_MAX_UPLOAD_MB', 20),
    'image_provider' => env('IMAGE_GENERATION_PROVIDER', null),
    'video_provider' => env('VIDEO_GENERATION_PROVIDER', null),
    'generation_timeout' => env('MEDIA_GENERATION_TIMEOUT', 120),
],
```

### 2.3 Media Library Upload & Lifecycle (`app/Services/MediaLibraryService.php`)
- **Upload:** Media files are stored via Laravel's abstraction:
  ```php
  $disk = config('services.media.storage_disk', 'local');
  $path = $file->storeAs("media/{$brand->id}", uniqid('upload_', true) . '.' . $extension, $disk);
  ```
- **URL Resolution:** URLs are derived dynamically:
  ```php
  'public_url' => Storage::disk($disk)->url($path);
  ```
- **Deletion:** Deletion operates cleanly against the configured disk:
  ```php
  $storagePath = $asset->storage_path;
  $disk = $asset->storage_disk ?? config('services.media.storage_disk', 'local');
  Storage::disk($disk)->delete($storagePath);
  ```

### 2.4 Database Schema Readiness (`MediaAsset` Model)
The TiDB Cloud database schema (`media_assets` table) already includes all required columns:
- `storage_disk` (`VARCHAR(50)`)
- `storage_path` (`VARCHAR(255)`)
- `public_url` (`VARCHAR(2048)`)
- `file_size` (`BIGINT UNSIGNED`)
- `mime_type` (`VARCHAR(100)`)
- `file_format` (`VARCHAR(20)`)

**Conclusion:** **Zero database migrations or schema modifications are required** to transition from local to S3-compatible cloud storage.

---

## 3. Storage Provider Comparative Evaluation

| Feature / Criteria | Backblaze B2 | Cloudflare R2 | Supabase Storage (Selected) |
| :--- | :--- | :--- | :--- |
| **Credit Card Required for Signup?** | No | **Yes** (required for account) | **No** (100% card-free) |
| **Public Buckets Without Card?** | **No** (Card required for public) | Yes (once card on file) | **Yes** (Card-free public buckets) |
| **Free Storage Allowance** | 10 GB | 10 GB | 1 GB |
| **Free Monthly Bandwidth** | 3x storage (~30 GB) | Unlimited / Free egress | 5 GB egress |
| **S3 Protocol Compatibility** | Full S3 API | Full S3 API | Full S3 API (native S3 Gateway) |
| **Path-Style Endpoint Support** | Yes | Yes | Yes (`use_path_style_endpoint: true`) |
| **Over-Limit Behavior** | Metered billing (card) | Metered billing (card) | **Hard Cap** (Requests blocked, $0 cost) |
| **Inactivity Policy** | Account active | Account active | Projects pause after 7d inactivity* |
| **Zero-Cost Policy Compliance** | **FAIL** (Card required for public) | **FAIL** (Card required at setup) | **PASS** (100% compliant) |

*\*Note on Supabase Inactivity: Supabase pauses free projects if there are zero incoming API or storage requests for 7 consecutive days. Because SocialPilot AI staging/production issues periodic requests and media fetches, active use keeps the project awake. Furthermore, if paused, projects can be unpaused from the Supabase web dashboard with zero data loss.*

---

## 4. Backblaze B2 & Cloudflare R2 Disqualification Details

### 4.1 Cloudflare R2 Disqualification
Cloudflare R2 provides an attractive free tier (10 GB storage and zero egress charges). However, Cloudflare's billing engine mandates adding a verified payment method (credit card or PayPal) before creating an R2 bucket, because R2 is architected as a metered utility. Under the strict $0/no-payment-card constraint, Cloudflare R2 is disqualified.

### 4.2 Backblaze B2 Disqualification
Backblaze B2 allows users to create an account and access 10 GB of private storage without entering a credit card. However:
1. Social media platforms (such as Meta Graph API for Instagram/Facebook, TikTok Open API, and YouTube Data API) require direct, publicly fetchable HTTPS URLs to ingest video and image posts.
2. In Backblaze B2, **enabling public bucket access or creating a public bucket requires credit card verification**. Private buckets can generate pre-signed authorization tokens, but pre-signed URLs are brittle for asynchronous third-party social crawler retrieval and expire.
3. Because public bucket hosting cannot be enabled without a payment card, Backblaze B2 fails the strict zero-card constraint.

---

## 5. Supabase Storage: Architectural Suitability

### 5.1 Free Allowance Capacity Analysis
- **1 GB Storage:** At an average optimized image size of 300 KB, 1 GB accommodates **~3,400 image assets**. For video assets (~5 MB short-form clips), it accommodates **~200 videos**. This is more than sufficient for staging and early-phase production.
- **5 GB Monthly Bandwidth:** Easily accommodates hundreds of social posts and editorial previews per month.
- **Default Hard Spend Cap:** Supabase Free Tier applies a hard limit. When the 1 GB or 5 GB threshold is reached, subsequent upload requests return an HTTP 402/quota error rather than triggering overdraft charges. This guarantees $0 spend.

### 5.2 S3 Gateway Endpoint Architecture
Supabase provides an S3-compatible gateway natively integrated with its Storage engine:
- **Endpoint:** `https://<project-ref>.supabase.co/storage/v1/s3`
- **Region:** The AWS region corresponding to the chosen Supabase data center (e.g., `eu-central-1`, `us-east-1`).
- **Authentication:** Standard HMAC AWS S3 credentials (Access Key ID and Secret Access Key) generated directly from the Supabase Dashboard (`Project Settings -> Storage -> S3 Access Keys`).
- **Public URL Resolution:** Assets stored in a public bucket named `media` are directly reachable at:  
  `https://<project-ref>.supabase.co/storage/v1/object/public/media/{path}`

---

## 6. Dependency Assessment & Docker Environment

### 6.1 Required Composer Package
Laravel 12's S3 driver requires the official Flysystem S3 adapter:
- **Package:** `league/flysystem-aws-s3-v3: ^3.0`
- **Compatibility:** Fully compatible with PHP 8.2 and Laravel 12.71.0.
- **License:** MIT (permissive open-source, zero cost).

### 6.2 Render Container Build Impact (`Dockerfile.unified`)
The application container build process in `Dockerfile.unified` runs:
```dockerfile
# 7. Install PHP Production Dependencies
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-progress
```
Adding `league/flysystem-aws-s3-v3` to `composer.json` and committing the updated `composer.lock` will cause Render to automatically build and include the S3 adapter during standard deployment without requiring any Dockerfile modifications.

---

## 7. Step-by-Step Implementation Plan (Stage 5.5.2.2)

When authorized, implementation will proceed through the following non-disruptive steps:

```
[Phase 1: Package Integration]
  Install league/flysystem-aws-s3-v3 via Composer locally
  Verify composer.lock & run php artisan test

[Phase 2: Configuration Mapping]
  Confirm config/filesystems.php S3 disk configuration
  Set MEDIA_STORAGE_DISK=s3 in .env / Render environment

[Phase 3: Supabase Project & Bucket Setup (User / Manual)]
  1. Create free project on Supabase (No card needed)
  2. Create a bucket named "media" and toggle to "Public"
  3. Generate S3 Access Keys (Project Settings -> Storage)

[Phase 4: Render Staging Environment Configuration]
  Configure Render environment variables:
    - MEDIA_STORAGE_DISK = s3
    - AWS_ACCESS_KEY_ID = <supabase-s3-access-key-id>
    - AWS_SECRET_ACCESS_KEY = <supabase-s3-secret-key>
    - AWS_DEFAULT_REGION = <project-region>
    - AWS_BUCKET = media
    - AWS_ENDPOINT = https://<project-ref>.supabase.co/storage/v1/s3
    - AWS_USE_PATH_STYLE_ENDPOINT = true
    - AWS_URL = https://<project-ref>.supabase.co/storage/v1/object/public/media

[Phase 5: Deploy & Verification Testing]
  Deploy main branch to Render staging
  Execute non-destructive upload & download smoke test
  Verify media persists across container restart / redeploy
```

---

## 8. Final Audit Decision

Based on strict evaluation against official provider terms, API protocols, card-free requirements, and zero-cost constraints:

```
================================================================================
FINAL DECISION:
A — PROVIDER VERIFIED; IMPLEMENTATION PLAN READY.
================================================================================
```

### Next Action:
In accordance with Stage 5.5.2.1 safety rules, **all operations remain stopped**. No packages have been installed, no code has been modified, and no cloud accounts or credentials have been created.

Awaiting user review and explicit authorization to proceed with **Stage 5.5.2.2 (Durable Object Storage Implementation)**.
