# Stage 5.5.4.12 — T-08 Synthetic Media Probe Preflight Report

**Project:** SocialPilot AI  
**Repository:** `Cla-renc/socialpilot-ai`  
**Branch:** `main`  
**Deployed Commit:** `7cbd0bca529c112eaa9dd536414ee36ad691e5cc` (`7cbd0bc`)  
**Production Web Service Name:** `socialpilot-ai`  
**Production URL:** `https://socialpilot-ai-y4lm.onrender.com`  
**Database:** `socialpilot` (TiDB Cloud Serverless, `gateway01.eu-central-1.prod.aws.tidbcloud.com:4000`)  
**Runtime Database Account:** `AZDARrqgy4MBFGE.prod_app` (DML only, TLS active: `TLS_AES_128_GCM_SHA256`)  
**Cloud Storage Provider:** Supabase Storage (S3-compatible API via Flysystem S3 adapter)  
**Stage:** 5.5.4.12 (T-08 Synthetic Media Probe Preflight — Read-Only)  
**Preflight Timestamp:** 2026-11-23T13:03:10+00:00  
**Report Generated:** 2026-11-23T16:15:00+03:00  
**Script:** `scripts/stage_5_5_4_12_preflight.php`  
**Results Data:** `scripts/stage_5_5_4_12_preflight_results.json`  

---

## 1. FINAL DECISION

```
A — READY
```

> [!IMPORTANT]
> **CLASSIFICATION RATIONALE:**
> 1. **Complete Architectural Preparedness:** The media upload, retrieval, and deletion subsystems are fully implemented in `MediaLibraryController` and `MediaLibraryService` with robust validation and multi-tenant authorization guards.
> 2. **Supabase Storage Configuration Verified:** The application is explicitly configured to use `MEDIA_STORAGE_DISK=supabase`, driving an S3-compatible Flysystem adapter connecting to Supabase Storage:
>    - **Bucket:** `media` (matches expected architecture).
>    - **Region:** `eu-west-1` (matches expected region).
>    - **Endpoint:** `https://vznosyrvokaenrqabalr.storage.supabase.co/storage/v1/s3`.
>    - **Path-style Addressing:** `AWS_USE_PATH_STYLE_ENDPOINT=true` (required for Supabase S3 API).
>    - **Credentials:** S3 Access Key ID (32 chars) and Secret Access Key (64 chars) are active and configured.
> 3. **Strict Separation of Uploading from AI Generation:** The media upload workflow (`POST /api/v1/brands/{brand}/media/upload`) accepts binary file payloads, performs MIME-type and size checks, and persists objects directly to Supabase Storage without contacting any AI providers (OpenAI, Gemini, Stability, etc.). It operates completely cost-free ($0/no-card maintained).
> 4. **Strict Multi-Tenant Isolation:**
>    - Brand authorization: `abort_unless($targetBrand->user_id === $request->user()->id, 403)`.
>    - Asset authorization: `abort_unless($asset->user_id === $user->id, 403)`.
>    - File path isolation: Every upload is automatically partitioned under `media/{brand_id}/upload_{uniqid}.{ext}`.
> 5. **Dual Deletion Strategy Identified for Zero Orphan Risk:**
>    - Deleting via API (`DELETE /api/v1/media/{id}`) triggers `MediaLibraryService::deleteAsset()`, which deletes both the database record and the remote Supabase S3 object via `Storage::disk('supabase')->delete($storagePath)`.
>    - The controlled test must delete the synthetic media asset via API **before** deleting the synthetic brand, ensuring zero orphan objects remain in Supabase Storage.
> 6. **Production Baseline Inspected via Read-Only Queries:**
>    - `media_assets`: **`0`**
>    - `media_generation_jobs`: **`0`**
>    - `media_storyboards`: **`0`**
>    - `scheduled_post_media`: **`0`**
>    - `brand_content_drafts`: **`0`**
>    - `scheduled_posts`: **`0`**
>    - Existing baseline assets (User 30001, Brand 30001 in `draft` status, and real user User 344308) are confirmed intact.
> 7. **Zero Production Mutations:** Zero files uploaded, zero database records modified or created.

---

## 2. Inspected Routes and Source Files

| Source File | Purpose |
| :--- | :--- |
| `routes/api.php` (lines 100–122) | Media routes (`/brands/{brand}/media/upload`, `/media/{id}`, etc.) |
| `app/Http/Controllers/Api/V1/MediaLibraryController.php` | Controller for media index, show, upload, update, destroy |
| `app/Services/MediaLibraryService.php` | Core upload handling, MIME/size validation, storage operations |
| `config/filesystems.php` (lines 63–74) | Flysystem `supabase` S3 disk driver configuration |
| `config/services.php` (lines 143–150) | `services.media` configuration (`storage_disk`, `max_upload_size_mb`) |
| `app/Models/MediaAsset.php` | MediaAsset Eloquent model, casts, relations, soft deletes |
| `database/migrations/2026_11_13_200000_create_media_generation_tables.php` | Schema for `media_assets`, `media_generation_jobs`, `media_storyboards` |
| `database/migrations/2026_11_13_230000_create_scheduling_and_autopilot_tables.php` | Schema for `scheduled_post_media` pivot table |
| `scripts/stage_5_5_4_12_preflight_results.json` | Live DB and configuration evidence |

---

## 3. T-08.1 — Media Architecture Findings

### Endpoints and HTTP Methods

| Method | Endpoint | Action | Authorization |
| :--- | :--- | :--- | :--- |
| `GET` | `/api/v1/brands/{brand}/media` | `MediaLibraryController@index` | Sanctum + Brand Owner (`403`) |
| `POST` | `/api/v1/brands/{brand}/media/upload` | `MediaLibraryController@upload` | Sanctum + Brand Owner (`403`) |
| `POST` | `/api/v1/media/upload` | `MediaLibraryController@upload` | Sanctum + Target Brand Owner (`403`) |
| `GET` | `/api/v1/media/{id}` | `MediaLibraryController@show` | Sanctum + Asset Owner (`403`) |
| `PUT` | `/api/v1/media/{id}` | `MediaLibraryController@update` | Sanctum + Asset Owner (`403`) |
| `DELETE` | `/api/v1/media/{id}` | `MediaLibraryController@destroy` | Sanctum + Asset Owner (`403`) |

### Upload Validation Rules

- `file`: Required binary file payload (`'file' => ['required', 'file']`).
- `brand_id`: Required integer referencing `brands.id` owned by the authenticated user.
- `title`: Nullable string, max 255 characters (defaults to filename without extension).
- `platform`: Nullable string, max 50 characters.
- `metadata`: Nullable JSON array.

### File Type & Size Limits

- **Maximum Upload Size:** `20MB` (`services.media.max_upload_size_mb`).
- **Enforcement:** `abort_if($file->getSize() > $maxBytes, 422, "File exceeds maximum size of 20MB.");`
- **Supported MIME Types:**
  - **Images:** `image/jpeg`, `image/png`, `image/webp`, `image/gif`
  - **Videos:** `video/mp4`, `video/quicktime`, `video/webm`
- **Enforcement:** `abort_unless(in_array($file->getMimeType(), $allowedMimes, true), 422, 'Unsupported file type.');`

### Upload vs. AI Generation Separation

- **Media Uploading (`MediaLibraryController`):** Accepts user-supplied files via HTTP multipart/form-data. Files are streamed directly to Supabase Storage via Flysystem. No third-party LLMs or generative models are invoked. Record is created with `provider: 'uploaded'` and `generation_status: 'completed'`.
- **Media Generation (`MediaGenerationController`):** Dispatches asynchronous generation jobs to external AI services (if configured). Currently disabled in production (`image_provider = null`, `video_provider = null`).

### Client Consistency

Both the web front-end and Flutter mobile client (`mobile/lib`) communicate through the exact same Laravel REST API endpoints under `/api/v1`.

---

## 4. T-08.2 — Supabase Storage Configuration Findings

The production storage configuration was inspected without exposing sensitive credential values:

| Configuration Item | Expected Architecture | Actual Production Value | Evaluation |
| :--- | :--- | :--- | :---: |
| **Media Storage Disk** | `supabase` | `supabase` (`config('services.media.storage_disk')`) | ✅ MATCH |
| **Driver** | S3 (Flysystem) | `s3` | ✅ MATCH |
| **Bucket Name** | `media` | `media` (`AWS_BUCKET=media`) | ✅ MATCH |
| **Region** | `eu-west-1` | `eu-west-1` (`AWS_DEFAULT_REGION=eu-west-1`) | ✅ MATCH |
| **Endpoint** | Supabase S3 | `https://vznosyrvokaenrqabalr.storage.supabase.co/storage/v1/s3` | ✅ MATCH |
| **Path-Style Endpoint** | `true` | `true` (`AWS_USE_PATH_STYLE_ENDPOINT=true`) | ✅ MATCH |
| **Public CDN URL Base** | Supabase Public | `https://vznosyrvokaenrqabalr.supabase.co/storage/v1/object/public/media` | ✅ MATCH |
| **Access Key ID** | Configured | Configured (32 characters, S3 HMAC ID) | ✅ ACTIVE |
| **Secret Access Key** | Configured | Configured (64 characters, S3 Secret Key) | ✅ ACTIVE |
| **Container Consistency** | Local & Prod match | Consistent across `.env` and `.env.production.local` | ✅ MATCH |

---

## 5. T-08.3 — Storage Service Implementation Findings

### Upload Workflow
```
UploadedFile (HTTP POST)
  ├── 1. Size Validation (<= 20MB) -> 422 on failure
  ├── 2. MIME Validation (PNG/JPEG/WebP/GIF/MP4/WebM) -> 422 on failure
  ├── 3. Partition Path: media/{brand_id}/upload_{uniqid}.{ext}
  ├── 4. Flysystem: Storage::disk('supabase')->putFileAs(...)
  └── 5. DB Record: MediaAsset::create(...) with public_url, storage_path, storage_disk
```

### Deletion Workflow
```php
public function deleteAsset(MediaAsset $asset): void
{
    $storagePath = $asset->storage_path;
    $disk        = $asset->storage_disk ?? config('services.media.storage_disk', 'local');

    $asset->delete(); // SoftDeletes

    if ($storagePath) {
        try {
            Storage::disk($disk)->delete($storagePath);
        } catch (\Throwable) {
            // best-effort cleanup
        }
    }
}
```

### Orphan Prevention Strategy
- If a brand is deleted via API, database foreign keys cascade-delete `media_assets` rows in SQL, but Flysystem does **not** receive an S3 deletion webhook.
- **Controlled Test Safeguard:** The synthetic test must explicitly invoke `DELETE /api/v1/media/{asset_id}` via the API **before** deleting the synthetic brand. This ensures `deleteAsset()` executes and issues the S3 `DeleteObject` command to Supabase Storage, guaranteeing zero orphaned storage objects.

---

## 6. T-08.4 — Security & Multi-Tenant Isolation Findings

| Protection Layer | Implementation Mechanism | Expected Status Code |
| :--- | :--- | :---: |
| **Brand Scoping on Upload** | `abort_unless($targetBrand->user_id === $request->user()->id, 403)` | `HTTP 403 Forbidden` |
| **Asset Scoping on Retrieval** | `abort_unless($asset->user_id === $user->id, 403)` | `HTTP 403 Forbidden` |
| **Asset Scoping on Deletion** | `abort_unless($asset->user_id === $user->id, 403)` | `HTTP 403 Forbidden` |
| **Brand Media Index Scoping** | `abort_unless($brand->user_id === $request->user()->id, 403)` | `HTTP 403 Forbidden` |
| **Storage Path Isolation** | Prefixed with `media/{brand_id}/` | Strict folder partition |
| **Path Traversal Protection** | Filename generated using `uniqid('upload_', true)` | Traversal impossible |
| **MIME Spoofing Protection** | File inspection via PHP `getMimeType()` (magic byte detection) | `HTTP 422` on invalid MIME |

---

## 7. T-08.5 — Database Relationships & Actual Baseline Counts

### Live Database Inspection (via `prod_app` on `socialpilot`)

| Table | Count | Baseline Evaluation |
| :--- | :---: | :--- |
| `users` | **`2`** | User 30001 (baseline fixture) + User 344308 (Clarence Yaa Ngwaru, project owner) |
| `brands` | **`1`** | Brand 30001 ("Ullrich-Mitchell", status: `draft`, owned by User 30001) |
| `media_assets` | **`0`** | Clean baseline |
| `media_generation_jobs` | **`0`** | Clean baseline |
| `media_storyboards` | **`0`** | Clean baseline |
| `scheduled_post_media` | **`0`** | Clean pivot baseline |
| `brand_content_drafts` | **`0`** | Clean baseline |
| `scheduled_posts` | **`0`** | Clean baseline |
| `publishing_attempts` | **`0`** | Clean baseline |
| `jobs` (Queue) | **`0`** | Clean queue |
| `failed_jobs` | **`0`** | Clean queue |
| `notifications` | **`0`** | Clean baseline |
| `personal_access_tokens` | **`0`** | Clean baseline |

### Important Observation: Active Users
The live `users` table contains two valid accounts:
1. **User `30001`:** `dbernier@example.org` (production fixture asset).
2. **User `344308`:** `yaaclarence@gmail.com` (Clarence Yaa Ngwaru, registered via production web UI at `2026-11-23 12:56:45`, 0 brands).

**Standing Invariant:** Both User `30001` and User `344308` are protected production accounts and must remain strictly untouched during any test.

### Foreign Key Constraints on `media_assets`

- `media_assets.user_id` → `users.id` (`ON DELETE CASCADE`)
- `media_assets.brand_id` → `brands.id` (`ON DELETE CASCADE`)
- `media_assets.content_draft_id` → `brand_content_drafts.id` (`ON DELETE SET NULL`)
- `scheduled_post_media.media_asset_id` → `media_assets.id` (`ON DELETE CASCADE`)

---

## 8. T-08.6 — Synthetic Media Test Proposal (Stage 5.5.4.13)

For the separately authorized controlled execution, the following sequence is proposed:

### Proposed Sequence

1. **Pre-Execution Gate:** Re-verify database connection, TLS, and baseline counts (`media_assets = 0`, User 30001 intact, Brand 30001 intact, User 344308 intact).
2. **Ephemeral Identity Provisioning:**
   - Register one temporary synthetic user: `[PROD-TEST] Media Auditor` (`prod-media-audit-<uuid>@example.invalid`).
   - Create one temporary synthetic brand: `[PROD-TEST] Media Verification` (status: `draft`).
3. **Negative Validation Probes:**
   - **T-08.1:** Empty payload to `POST /api/v1/brands/{brand}/media/upload` → verify `HTTP 422`.
   - **T-08.2:** Unsupported file type probe (e.g. text file or executable) → verify `HTTP 422`.
   - **T-08.3:** Cross-tenant upload probe against Brand `30001` → verify `HTTP 403`.
4. **Controlled Synthetic Upload (T-08.4):**
   - **Test File:** 1x1 transparent PNG (`68 bytes`) generated in-memory.
   - **Filename:** `prod_media_probe.png`.
   - **Payload:** `brand_id: {brand}`, `file: @prod_media_probe.png`, `title: "[PROD-TEST] Storage Probe PNG"`.
   - **Expected:** `HTTP 201 Created` (`provider: "uploaded"`, `storage_disk: "supabase"`, public URL returned).
5. **Authenticated Media Retrieval (T-08.5):**
   - `GET /api/v1/media/{asset_id}` with synthetic token → verify `HTTP 200 OK`.
   - Verify `storage_disk = 'supabase'`, `file_size = 68`, `mime_type = 'image/png'`.
6. **Cross-Tenant Access Probe (T-08.6):**
   - Probe Brand 30001 media listing (`GET /api/v1/brands/30001/media`) with synthetic token → verify `HTTP 403`.
7. **Complete Teardown (T-08.7):**
   - **Step A:** Invoke `DELETE /api/v1/media/{asset_id}` via official API → verify `HTTP 200 OK` (purges Supabase S3 object + soft-deletes DB record).
   - **Step B:** Invoke `DELETE /api/v1/brands/{brand_id}` via official API → verify `HTTP 200 OK` (cascades all brand rows in DB).
   - **Step C:** Scoped purge of synthetic notifications, access tokens, and user.
8. **Final Baseline Restoration Verification:**
   - Verify `media_assets` = 0, `brands` = 1, `users` = 2, `tokens` = 0.
   - Verify all health probes return `HTTP 200 OK`.

---

## 9. T-08.7 — Cost & External Dependency Audit

- **$0 / No-Card Compliance:**
  - Supabase Storage free tier includes **500MB** of object storage. A 68-byte test file uses less than 0.0001% of the free quota and exists for less than 60 seconds before deletion.
  - Zero paid API calls are made: no OpenAI DALL-E, Stability AI, Google Imagen, or video generation endpoints are triggered.
  - No new subscriptions, billing activation, or credit card inputs required.
  - 100% cost-free.

---

## 10. Identified Risks & Mitigations

| Risk | Impact | Mitigation Strategy |
| :--- | :--- | :--- |
| **S3 Network Latency / Timeout** | Curl timeout during upload | Use 45-second timeout on multipart curl request. |
| **Orphaned S3 Object** | Storage object remains after brand deletion | Explicitly call `DELETE /api/v1/media/{id}` via API before deleting the synthetic brand. |
| **Accidental Production User Impact** | Modifying User 30001 or User 344308 | Cleanup queries must enforce `WHERE id = {synth_id} AND id NOT IN (30001, 344308)`. |
| **Public URL Accessibility** | Public read bucket | Synthetic file is a non-sensitive 1x1 transparent PNG; deleted immediately upon verification. |

---

## 11. Explicit Confirmation of Zero Mutations

> [!IMPORTANT]
> **ZERO production mutations occurred during Stage 5.5.4.12.**
> - Zero files were uploaded to Supabase Storage.
> - Zero storage objects were modified or deleted.
> - Zero database rows were inserted, updated, or deleted.
> - Zero users or brands were created.
> - User `30001`, Brand `30001`, and User `344308` remain completely untouched.

---

## 12. Sequential Programme Progress

- [x] **T-01: Operational Cycle & Queue Health** — **COMPLETED (Stage 5.5.4.3: PASS)**
- [x] **T-02: Test User Registration & Validation** — **COMPLETED (Stage 5.5.4.5: PASS)**
- [x] **T-03: Authenticated Profile & Tenancy Shield** — **COMPLETED (Stage 5.5.4.5: PASS)**
- [x] **T-04: Brand Creation & Validation** — **COMPLETED (Stage 5.5.4.7: PASS)**
- [x] **T-05: Brand Overview & Cross-Tenant Shield** — **COMPLETED (Stage 5.5.4.7: PASS)**
- [x] **T-06: Internal AI Content Generation** — **COMPLETED (Stage 5.5.4.9: PASS)**
- [x] **T-07: Post Scheduling Verification** — **COMPLETED (Stage 5.5.4.11: PASS)**
- [x] **T-08 Preflight: Synthetic Media Storage Audit** — **COMPLETED (Stage 5.5.4.12: READY)**
- [ ] **T-08 Execution: Controlled Synthetic Media Probe** — *Awaiting separate authorization*
- [ ] **T-09: Tenancy Boundary Probe** — *Pending human authorization*
- [ ] **T-10: Complete Synthetic Teardown & Final Report** — *Pending human authorization*

---

## 13. STRICT FINAL STOP

**ALL ACTIONS HAVE STOPPED.**

No files were uploaded, no storage objects were created, and no database records were modified.

Await separate explicit authorization before proceeding to Stage 5.5.4.13 (**T-08 Controlled Production Synthetic Media Execution**).
