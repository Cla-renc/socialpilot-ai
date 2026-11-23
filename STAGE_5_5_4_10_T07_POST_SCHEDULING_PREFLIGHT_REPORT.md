# Stage 5.5.4.10 — T-07 Post Scheduling Preflight Report

**Project:** SocialPilot AI  
**Repository:** `Cla-renc/socialpilot-ai`  
**Branch:** `main`  
**Deployed Commit:** `7cbd0bca529c112eaa9dd536414ee36ad691e5cc` (`7cbd0bc`)  
**Production Web Service Name:** `socialpilot-ai`  
**Production URL:** `https://socialpilot-ai-y4lm.onrender.com`  
**Database:** `socialpilot` (TiDB Cloud Serverless, `gateway01.eu-central-1.prod.aws.tidbcloud.com:4000`)  
**Runtime Database Account:** `AZDARrqgy4MBFGE.prod_app` (DML only, TLS active: `TLS_AES_128_GCM_SHA256`)  
**Stage:** 5.5.4.10 (T-07 Post Scheduling Preflight — Read-Only)  
**Preflight Timestamp:** 2026-11-23T11:26:54+00:00  
**Report Generated:** 2026-11-23T14:29:00+03:00  
**Script:** `scripts/stage_5_5_4_10_preflight.php`  
**Results:** `scripts/stage_5_5_4_10_preflight_results.json`  

---

## 1. FINAL DECISION

```
A — READY
```

> [!IMPORTANT]
> **CLASSIFICATION RATIONALE:**
> The post-scheduling architecture is architecturally sound and operationally safe for a separately authorized controlled test. All 6 critical safety criteria have been verified:
> 1. Scheduling is brand-scoped and authenticated with strict per-request ownership checks (`brand->user_id !== $request->user()->id → HTTP 403`).
> 2. Future-timestamp safety is enforced by the service layer — past timestamps are rejected with `HTTP 422` unless `allow_past: true` is explicitly passed.
> 3. The `publishing:dispatch-scheduled` scheduler only processes posts where `scheduled_at <= now()` — a far-future timestamp is **provably safe** from automatic triggering.
> 4. Saving a scheduled post does **not** queue a publishing job — only `publishing:dispatch-scheduled` and `POST /api/v1/publishing/scheduled-posts/{id}/publish` trigger `PublishScheduledPostJob`.
> 5. The notification channel for scheduling events is strictly `['database']` — no SMTP, SMS, or webhook dispatched.
> 6. All deletion paths are covered: `DELETE /api/v1/scheduled-posts/{id}` cascades through `scheduled_post_platforms`, `scheduled_post_media`, `publishing_attempts`, `publishing_notifications`, `post_analytics`; a companion `brand_posts` row must be cleaned separately via SQL.

> [!WARNING]
> **KEY ADVISORY — `brand_posts` shadow row:** `ContentSchedulingService::schedulePost()` unconditionally creates a `brand_posts` row for backward Phase-4 calendar compatibility. This row is **not** automatically deleted by API (`DELETE /api/v1/scheduled-posts/{id}`) because it has no foreign-key back-reference in that direction — only the `metadata->scheduled_post_id` JSON value links them. The cleanup procedure for the controlled test must include an explicit SQL scoped DELETE on `brand_posts` by `brand_id`. This is a known advisory, not a blocker.

---

## 2. Sources Inspected

| File | Purpose |
| :--- | :--- |
| `routes/api.php` (lines 171–179, 227–228) | Scheduling and publishing route declarations |
| `app/Http/Controllers/Api/V1/ScheduledPostApiController.php` | All scheduling CRUD actions |
| `app/Services/ContentSchedulingService.php` | Core scheduling orchestration |
| `app/Services/SchedulingConflictService.php` | Conflict detection |
| `app/Services/SchedulingIntelligenceService.php` | AI bridge + fallback conflict checker |
| `app/Models/ScheduledPost.php` | Model relationships, casts, `SoftDeletes` trait |
| `app/Models/BrandPost.php` | Legacy Phase-4 shadow row model |
| `app/Console/Commands/DispatchScheduledPublishingCommand.php` | `publishing:dispatch-scheduled` command |
| `app/Jobs/Publishing/PublishScheduledPostJob.php` | Actual publishing job (ShouldQueue) |
| `app/Http/Controllers/Api/V1/PublishingController.php` | Manual publish and cancel endpoints |
| `database/migrations/2026_11_13_230000_create_scheduling_and_autopilot_tables.php` | Schema: `scheduled_posts`, `scheduled_post_platforms`, `scheduled_post_media`, `publishing_queues`, `publishing_queue_items` |
| `database/migrations/2026_11_09_180000_create_brand_posts_table.php` | Schema: `brand_posts` |
| `database/migrations/2026_11_14_100000_create_publishing_agent_tables.php` | Schema: `publishing_attempts` |
| `database/migrations/2026_11_14_150000_create_analytics_and_ai_optimization_tables.php` | Schema: `post_analytics` |
| `routes/console.php` | Laravel scheduler schedule definitions |
| `scripts/stage_5_5_4_10_preflight_results.json` | Live DB baseline evidence |

---

## 3. T-07.1 — Scheduling API Architecture

### Endpoints

| Method | Route | Controller Action | Auth |
| :--- | :--- | :--- | :--- |
| `GET` | `/api/v1/brands/{brand}/scheduled-posts` | `ScheduledPostApiController@index` | Sanctum + brand ownership |
| **`POST`** | **`/api/v1/brands/{brand}/scheduled-posts`** | **`ScheduledPostApiController@store`** | **Sanctum + brand ownership** |
| `GET` | `/api/v1/scheduled-posts/{id}` | `ScheduledPostApiController@show` | Sanctum + brand ownership |
| `PUT` | `/api/v1/scheduled-posts/{id}` | `ScheduledPostApiController@update` | Sanctum + brand ownership |
| `DELETE` | `/api/v1/scheduled-posts/{id}` | `ScheduledPostApiController@destroy` | Sanctum + brand ownership |
| `POST` | `/api/v1/scheduled-posts/{id}/reschedule` | `ScheduledPostApiController@reschedule` | Sanctum + brand ownership |
| `POST` | `/api/v1/scheduled-posts/{id}/cancel` | `ScheduledPostApiController@cancel` | Sanctum + brand ownership |
| `POST` | `/api/v1/scheduled-posts/{id}/approve` | `ScheduledPostApiController@approve` | Sanctum + brand ownership |
| `POST` | `/api/v1/publishing/scheduled-posts/{id}/publish` | `PublishingController@executeScheduled` | Sanctum + brand ownership |

### Dispatch Flow

`POST /api/v1/brands/{brand}/scheduled-posts`
→ `ScheduledPostApiController::store()`
→ `ContentSchedulingService::schedulePost()`
→ DB::transaction:
  - `ScheduledPost::create()`
  - `ScheduledPostPlatform::create()` (per platform)
  - `ScheduledPostMedia::create()` (if media attached)
  - `BrandContentDraft::update()` (if `content_draft_id` supplied)
  - `BrandPost::create()` (always, as backward-compat shadow)
  - `$user->notify(DashboardEventNotification)` via `['database']` channel
→ HTTP 201 Created

**Key observation:** No `dispatch()` call anywhere in this path. No queue job is dispatched during post creation.

### Operating on Existing Draft vs. New Content

- `content_draft_id` is **nullable**. The endpoint supports both:
  - **Draft-linked:** pass `content_draft_id` referencing an existing `brand_content_drafts.id` owned by the brand. The service validates ownership, inherits title/caption/body/hashtags, and sets the draft's `status = 'scheduled'`.
  - **Standalone:** pass `title`/`caption`/`body`/`hashtags` directly — no draft required.

- **Immediate vs. future scheduling:** Only future timestamps are accepted (unless `allow_past: true` is passed). No concept of "post immediately" through this endpoint; that uses `POST /api/v1/publishing/scheduled-posts/{id}/publish`.

---

## 4. T-07.2 — Validation and Time-Zone Handling

### Validation Rules (from `ScheduledPostApiController::store()`)

| Field | Validation | Required |
| :--- | :--- | :---: |
| `content_draft_id` | `nullable\|integer\|exists:brand_content_drafts,id` | No |
| `title` | `nullable\|string\|max:255` | No (defaults to draft title or `'Scheduled Post'`) |
| `caption` | `nullable\|string` | No |
| `body` | `nullable\|string` | No |
| `hashtags` | `nullable\|array` | No |
| **`scheduled_at`** | **`required\|string`** | **Yes** |
| `timezone` | `nullable\|string\|max:64` | No (defaults to brand's `brand_settings.timezone`, then `'UTC'`) |
| `platform` | `nullable\|string\|max:32` | No (shorthand for single-platform) |
| `platforms` | `nullable\|array` | No |
| `platforms.*.platform` | `required_with:platforms\|string\|max:32` | Conditional |
| `platforms.*.social_account_id` | `nullable\|integer\|exists:social_accounts,id` | No |
| `media_asset_ids` | `nullable\|array` | No |
| `media_asset_ids.*` | `integer\|exists:media_assets,id` | Conditional |
| `allow_past` | `nullable\|boolean` | No |
| `force` | `nullable\|boolean` | No |
| `requires_approval` | `nullable\|boolean` | No |
| `is_approved` | `nullable\|boolean` | No |

> [!NOTE]
> **Platform requirement:** At least one platform must be supplied (either `platform` shorthand or the `platforms[]` array). If neither is provided, `ContentSchedulingService::schedulePost()` throws `ValidationException` with `'platforms' => 'At least one target social platform is required.'`

### Time-Zone Implementation

```php
// From ContentSchedulingService::schedulePost()
$tz = $data['timezone'] ?? ($brand->settings?->timezone ?: 'UTC');
$scheduledAtUtc = Carbon::parse($rawScheduledAt, $tz)->setTimezone('UTC');
```

- Input is parsed **in the specified timezone** using Carbon.
- Immediately converted to UTC for storage in `scheduled_posts.scheduled_at` (`DATETIME` column).
- Original timezone string is preserved in `scheduled_posts.timezone` (`VARCHAR(64)`, default `'UTC'`).
- If `timezone` field is omitted, the brand's `brand_settings.timezone` is used. If that is also absent, `'UTC'` is assumed.
- **DST transitions:** Carbon handles DST via PHP's `DateTimeZone`. No application-level guard against ambiguous DST wall-clock times is implemented (gap/overlap hours). This is a minor gap, not a blocker for testing with an explicit UTC offset string.
- **Past-date guard:** `$scheduledAtUtc->isPast() && empty($data['allow_past'])` → throws `ValidationException` → HTTP 422.

### Recommendation for Controlled Test

Pass `scheduled_at` as a fully-qualified ISO 8601 string well in the future (e.g., `2027-01-01T12:00:00+00:00`) and `timezone: "UTC"` to avoid any DST ambiguity.

---

## 5. T-07.3 — Tenant Isolation

### Authorization Mechanism

All `ScheduledPostApiController` actions call `$this->authorizeBrandOwner()`:

```php
protected function authorizeBrandOwner(Request $request, Brand $brand): void
{
    if ($brand->user_id !== $request->user()->id) {
        abort(403, 'Unauthorized access to brand resources.');
    }
}
```

- For brand-scoped routes (`index`, `store`): the `{brand}` model is resolved by route-model binding; `brand->user_id` is compared directly.
- For post-scoped routes (`show`, `update`, `destroy`, `reschedule`, `cancel`, `approve`): the post is loaded with its `brand`; authorization checks `post->brand->user_id`.
- `ContentSchedulingService::schedulePost()` performs a **second** ownership check at the service layer: `if ($brand->user_id !== $user->id) → ValidationException`.

### Cross-Tenant Protection Matrix

| Action | Expected Behavior |
| :--- | :--- |
| Read a scheduled post belonging to another brand | `HTTP 403 Forbidden` |
| List scheduled posts of another brand | `HTTP 403 Forbidden` (brand guard) |
| Create a scheduled post on another's brand | `HTTP 403 Forbidden` (controller + service double-check) |
| Update another user's scheduled post | `HTTP 403 Forbidden` |
| Cancel another user's scheduled post | `HTTP 403 Forbidden` |
| Delete another user's scheduled post | `HTTP 403 Forbidden` |
| Publish another user's scheduled post | `HTTP 403 Forbidden` (`PublishingController@executeScheduled`) |

No cross-tenant bypass paths identified.

---

## 6. T-07.4 — Database Schema and Relational Integrity

### `scheduled_posts` Table (Verified in Production)

| Column | Type | Nullable | Default |
| :--- | :--- | :---: | :--- |
| `id` | `bigint unsigned` | No | auto-increment |
| `user_id` | `bigint unsigned` | No | — (FK → `users.id` CASCADE DELETE) |
| `brand_id` | `bigint unsigned` | No | — (FK → `brands.id` CASCADE DELETE) |
| `content_draft_id` | `bigint unsigned` | Yes | `null` (FK → `brand_content_drafts.id` SET NULL) |
| `title` | `varchar(255)` | No | — |
| `caption` | `text` | Yes | `null` |
| `body` | `longtext` | Yes | `null` |
| `hashtags` | `json` | Yes | `null` |
| `scheduled_at` | `datetime` | No | — (stored UTC) |
| `timezone` | `varchar(64)` | No | `'UTC'` |
| `status` | `varchar(32)` | No | `'scheduled'` |
| `requires_approval` | `tinyint(1)` | No | `0` |
| `is_approved` | `tinyint(1)` | No | `0` |
| `approved_by` | `bigint unsigned` | Yes | `null` (FK → `users.id` SET NULL) |
| `approved_at` | `datetime` | Yes | `null` |
| `autopilot_managed` | `tinyint(1)` | No | `0` |
| `autopilot_rule_id` | `bigint unsigned` | Yes | `null` |
| `cancellation_reason` | `varchar(255)` | Yes | `null` |
| `failure_reason` | `text` | Yes | `null` |
| `metadata` | `json` | Yes | `null` |
| `created_at` | `timestamp` | Yes | — |
| `updated_at` | `timestamp` | Yes | — |
| `deleted_at` | `timestamp` | Yes | `null` (SoftDeletes) |

**Indexes:** `(brand_id, scheduled_at)`, `(brand_id, status)`, `(user_id, status)`.

### Status State Machine

| Status Value | Meaning |
| :--- | :--- |
| `scheduled` | Normal future-scheduled post awaiting dispatch |
| `awaiting_approval` | Created with `requires_approval: true` but not yet approved |
| `in_queue` | Enqueued by `publishing:dispatch-scheduled` for processing |
| `publishing` | Currently being processed by `PublishScheduledPostJob` |
| `cancelled` | Cancelled by user or service |
| `failed` | Publishing failed |
| `paused` | Reserved for autopilot logic |

### Foreign Keys Referencing `scheduled_posts` (All Verified in Production)

| Referencing Table | Column | Constraint Name | Delete Behavior |
| :--- | :--- | :--- | :--- |
| `scheduled_post_platforms` | `scheduled_post_id` | `scheduled_post_platforms_scheduled_post_id_foreign` | **CASCADE** |
| `scheduled_post_media` | `scheduled_post_id` | `scheduled_post_media_scheduled_post_id_foreign` | **CASCADE** |
| `publishing_queue_items` | `scheduled_post_id` | `publishing_queue_items_scheduled_post_id_foreign` | **SET NULL** |
| `publishing_attempts` | `scheduled_post_id` | `publishing_attempts_scheduled_post_id_foreign` | **CASCADE** |
| `publishing_notifications` | `scheduled_post_id` | `publishing_notifications_scheduled_post_id_foreign` | **CASCADE** |
| `post_analytics` | `scheduled_post_id` | `post_analytics_scheduled_post_id_foreign` | **SET NULL** |

### `brand_posts` Shadow Table (Phase-4 Backward Compatibility)

The `brand_posts` table stores a shadow row unconditionally during `schedulePost()`. It is linked to the `scheduled_post` only via `metadata->scheduled_post_id` (JSON), **not** a foreign key. Columns:

| Column | Type |
| :--- | :--- |
| `id` | auto-increment |
| `user_id` | FK → `users.id` CASCADE |
| `brand_id` | FK → `brands.id` CASCADE |
| `content_draft_id` | FK → `brand_content_drafts.id` SET NULL |
| `provider` | `varchar`, default `'internal'` |
| `platform` | `varchar`, default `'linkedin'` |
| `status` | `varchar`, default `'queued'` |
| `scheduled_for` | `timestamp`, nullable |
| `content` | `longtext` |
| `metadata` | `json`, contains `{"scheduled_post_id": N, "source": "phase8_scheduler"}` |

> [!WARNING]
> **Cleanup Responsibility:** `DELETE /api/v1/scheduled-posts/{id}` does **not** delete the companion `brand_posts` row because there is no reverse FK. The brand cascade (`DELETE FROM brands WHERE id = ...`) will cascade-delete both `scheduled_posts` and `brand_posts` simultaneously. For the controlled test using the API's brand deletion endpoint, this is fully covered. No separate SQL cleanup of `brand_posts` is needed if brand is deleted via API.

---

## 7. T-07.5 — Publishing and Queue Side-Effect Analysis

### Three Distinct Operations — Clearly Distinguished

| Operation | Trigger | External Call? | Queued Job? | Status Change |
| :--- | :--- | :---: | :---: | :--- |
| **Saving a scheduled post** | `POST /api/v1/brands/{brand}/scheduled-posts` | ❌ No | ❌ No | `→ 'scheduled'` or `'awaiting_approval'` |
| **Dispatching a scheduled post** | `publishing:dispatch-scheduled` command (runs `scheduled_at <= now()`) | ❌ No (only queues a job) | ✅ Yes (`PublishScheduledPostJob`) | Remains `'scheduled'` until job runs |
| **Publishing to external platform** | `PublishScheduledPostJob::handle()` via `PublishingManager::publish()` | ✅ Yes (LinkedIn API, etc.) | — | `→ 'publishing'` → `'published'/'failed'` |

### Analysis of `publishing:dispatch-scheduled`

```php
// DispatchScheduledPublishingCommand::handle()
$posts = ScheduledPost::where('status', 'scheduled')
    ->where('scheduled_at', '<=', now())   // ← TIME GATE
    ->get();
```

A post with `scheduled_at` set to a **far-future date** (e.g., `2027-01-01T12:00:00+00:00`) will **never** match this query during the controlled test. Even if the scheduler fires every minute during the test window, it will produce zero matches and dispatch zero jobs. This is deterministically safe.

### Scheduler Schedule for Dispatch Command

```php
Schedule::command('publishing:dispatch-scheduled')->everyMinute()->withoutOverlapping();
```

The command runs every minute via `POST /api/internal/ops/cycle` (which runs `artisan schedule:run`). It is bounded: it only dispatches posts where `scheduled_at <= now()`.

### Notification Side Effects

`ContentSchedulingService::schedulePost()` dispatches exactly one `DashboardEventNotification` via the `['database']` channel:
- Written to the `notifications` table as a single JSON row.
- Zero SMTP, zero webhook, zero SMS.
- Must be included in cleanup.

### No Queue Records Created by Scheduling

Saving a scheduled post does not create any record in:
- `jobs` table (Laravel queue)
- `publishing_queues` table
- `publishing_queue_items` table

These tables remain at `0` after scheduling a future post.

---

## 8. T-07.6 — Production Baseline Inspection

**DB Connection:** `socialpilot` via `AZDARrqgy4MBFGE.prod_app@%`, TLS: `TLS_AES_128_GCM_SHA256`  
**Inspection Timestamp:** 2026-11-23T11:26:54+00:00

| Table | Live Count | Assessment |
| :--- | :---: | :--- |
| `users` | **1** | Baseline: User 30001 only |
| `brands` | **1** | Baseline: Brand 30001 only |
| `brand_posts` | **0** | Clean baseline |
| `scheduled_posts` | **0** | Clean baseline |
| `scheduled_post_platforms` | **0** | Clean baseline |
| `scheduled_post_media` | **0** | Clean baseline |
| `publishing_queues` | **0** | Clean baseline |
| `publishing_queue_items` | **0** | Clean baseline |
| `publishing_attempts` | **0** | Clean baseline |
| `post_analytics` | **0** | Clean baseline |
| `publishing_notifications` | **0** | Clean baseline |
| `jobs` | **0** | No queued jobs |
| `failed_jobs` | **0** | No failed jobs |
| `notifications` | **0** | Clean baseline |
| `personal_access_tokens` | **0** | Clean baseline |

**Baseline Invariant Verification:**

| Invariant | Value | Status |
| :--- | :--- | :---: |
| User 30001 present | ✅ ID `30001` | ✅ INTACT |
| Brand 30001 present | ✅ ID `30001` | ✅ INTACT |
| Brand 30001 owned by User 30001 | `user_id = 30001` | ✅ INTACT |
| Brand 30001 status | `draft` | ✅ INTACT |

---

## 9. T-07.7 — Synthetic Test Readiness & Proposed Test

### Feasibility Assessment

| Requirement | Feasible? | Evidence |
| :--- | :---: | :--- |
| Temporary synthetic user | ✅ Yes | Demonstrated in T-02/T-03, T-04/T-05, T-06 |
| Temporary synthetic brand | ✅ Yes | Demonstrated in T-04/T-05, T-06 |
| Uniquely identified synthetic post | ✅ Yes | Use unique `title` prefix `[PROD-TEST]` |
| Far-future timestamp (safe from dispatch) | ✅ Yes | `scheduled_at <= now()` guard in dispatcher |
| Documented cleanup procedure | ✅ Yes | Defined below |

### Proposed Controlled Test Sequence (T-07, for separate authorization)

**Setup:**
1. Register synthetic user `prod-sched-audit-<uuid>@example.invalid` → obtain Sanctum token.
2. Create synthetic brand `[PROD-TEST] Scheduling Brand`.

**Test Steps:**

| Step | Description | Endpoint | Expected HTTP |
| :---: | :--- | :--- | :---: |
| T-07.1 | Negative validation probe (empty payload) | `POST /api/v1/brands/{brand}/scheduled-posts` | `422` |
| T-07.2 | Past-timestamp rejection | Same endpoint, `scheduled_at` = 1 hour ago | `422` |
| T-07.3 | Valid future-scheduled post creation | Same endpoint, `scheduled_at` = `2027-01-01T12:00:00+00:00`, `platform: "linkedin"` | `201` |
| T-07.4 | List own brand's scheduled posts | `GET /api/v1/brands/{brand}/scheduled-posts` | `200` (returns post) |
| T-07.5 | Detail fetch of synthetic post | `GET /api/v1/scheduled-posts/{id}` | `200` |
| T-07.6 | Cross-tenant protection: attempt to view Brand 30001's posts | `GET /api/v1/brands/30001/scheduled-posts` | `403` |
| T-07.7 | Cross-tenant protection: attempt to view another user's post by ID | `GET /api/v1/scheduled-posts/<any_id_belonging_to_30001>` | `403` or `404` |
| T-07.8 | Cancel synthetic scheduled post | `POST /api/v1/scheduled-posts/{id}/cancel` | `200` |

**Teardown (via API brand deletion, then explicit SQL scoped to synthetic records):**
1. `DELETE /api/v1/brands/{brand}` → HTTP 200 (cascades `scheduled_posts`, `scheduled_post_platforms`, `scheduled_post_media`).
2. Direct SQL: `DELETE FROM brand_posts WHERE brand_id = {brand_id} AND brand_id != 30001` (removes shadow row; also cascades from brand deletion if done in correct order).
3. Direct SQL: `DELETE FROM notifications WHERE notifiable_id = {synthetic_user_id} AND notifiable_id != 30001`.
4. Direct SQL: `DELETE FROM personal_access_tokens WHERE tokenable_id = {synthetic_user_id}`.
5. Direct SQL: `DELETE FROM users WHERE id = {synthetic_user_id} AND id != 30001`.

> [!NOTE]
> Since `brand_posts.brand_id` has `ON DELETE CASCADE` referencing `brands.id`, and the brand is deleted via the API in step 1, the `brand_posts` shadow row **will be cascade-deleted** automatically — no additional SQL step needed for it.

---

## 10. Identified Blockers and Risks

### No Blockers

No blockers were identified. The controlled test can proceed as proposed.

### Advisory Items (Non-Blocking)

| # | Advisory | Impact | Mitigation |
| :--- | :--- | :--- | :--- |
| A1 | `brand_posts` shadow row is not cleaned up by `DELETE /api/v1/scheduled-posts/{id}` | Low — a dangling row in `brand_posts` if post is deleted individually (not via brand deletion) | In the controlled test, delete the brand via API — the cascade covers `brand_posts` automatically |
| A2 | `SchedulingIntelligenceService::checkConflicts()` calls internal FastAPI engine (`http://127.0.0.1:8080/internal/v1/scheduling/conflicts`) with 5s timeout | Low — if FastAPI is slow, the conflict check falls back to `fallbackConflictCheck()` gracefully (no blocker) | Use future timestamp far from any existing scheduled posts (zero conflict risk since `scheduled_posts` count = 0) |
| A3 | `ScheduledPost` uses Laravel `SoftDeletes` — `destroy()` sets `deleted_at` rather than hard-deleting | Low — Eloquent soft-delete means DB rows remain with `deleted_at` set; they are excluded from normal queries | Brand cascade delete hard-deletes all child rows including soft-deleted scheduled posts |
| A4 | `platform: "linkedin"` with no `social_account_id` creates a platform record with `social_account_id = null`; `PublishScheduledPostJob` skips publishing if no social account is linked | Desired — zero external publishing risk during test | Use `platform: "linkedin"` without `social_account_id` for guaranteed zero-publish outcome |

---

## 11. Mutations Confirmation

> [!IMPORTANT]
> **ZERO production mutations occurred during this stage.**
>
> This stage was strictly read-only. The following activities were performed and no others:
> - Source code inspection (PHP files, migration files, route files).
> - Read-only SQL SELECT queries via the `prod_app` database account.
> - No INSERT, UPDATE, DELETE, TRUNCATE, ALTER, DROP, or CREATE SQL was executed.
> - No API endpoints were called against the production service.
> - No synthetic users, brands, posts, or records were created.
> - No environment variables, configuration, or deployments were modified.
> - No queue workers, scheduler commands, or dispatch commands were invoked.
> - No external services, social media platforms, email, SMS, or paid APIs were contacted.
>
> User 30001 and Brand 30001 remain completely untouched.

---

## 12. Recommended Next Stage and Authorization Requirements

### Stage 5.5.4.11 — T-07 Controlled Production Post Scheduling Execution

**Prerequisites for Authorization:**
1. This report accepted.
2. Explicit written authorization for one controlled post-scheduling test.
3. Authorization must explicitly permit:
   - Creation of one temporary synthetic user.
   - Creation of one temporary synthetic brand.
   - Creation of one synthetic scheduled post with `scheduled_at ≥ 2027-01-01T00:00:00+00:00`.
   - Execution of the 8 test steps enumerated in Section 9.
   - Teardown via API brand deletion and scoped SQL.
4. Authorization must explicitly confirm Brand 30001 must remain untouched.

**Risk level:** LOW — far-future timestamp guarantees no automatic dispatch or external publishing during the test window.

---

## 13. Sequential Programme Progress

- [x] **T-01: Operational Cycle & Queue Health** — **COMPLETED (Stage 5.5.4.3: PASS)**
- [x] **T-02: User Registration & Validation** — **COMPLETED (Stage 5.5.4.5: PASS)**
- [x] **T-03: Authenticated Profile & Tenancy Shield** — **COMPLETED (Stage 5.5.4.5: PASS)**
- [x] **T-04: Brand Creation & Validation** — **COMPLETED (Stage 5.5.4.7: PASS)**
- [x] **T-05: Brand Overview & Cross-Tenant Shield** — **COMPLETED (Stage 5.5.4.7: PASS)**
- [x] **T-06: Internal AI Content Generation** — **COMPLETED (Stage 5.5.4.9: PASS)**
- [x] **T-07 Preflight: Post Scheduling Architecture Audit** — **COMPLETED (Stage 5.5.4.10: READY)**
- [ ] **T-07 Execution: Controlled Post Scheduling Test** — *Awaiting separate authorization*
- [ ] **T-08: Synthetic Media Probe** — *Pending*
- [ ] **T-09: Tenancy Boundary Probe** — *Pending*
- [ ] **T-10: Complete Synthetic Teardown & Final Audit** — *Pending*

---

## 14. STRICT FINAL STOP

**ALL ACTIVITY HAS STOPPED.**

No scheduled post was created. No test records were created. No production mutations occurred.

Await separate explicit authorization before proceeding to Stage 5.5.4.11 (T-07 Controlled Post Scheduling Execution).
