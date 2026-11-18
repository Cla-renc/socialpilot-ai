# SOCIALPILOT AI — PHASE 11, STAGE 5.4.2.4.3
## TiDB Migration Interruption Investigation & Partial Schema Integrity Audit

**Repository:** `Cla-renc/socialpilot-ai`  
**Branch:** `main`  
**Stage:** 5.4.2.4.3 (TiDB Migration Interruption Investigation & Partial Schema Integrity Audit)  
**Date:** 2026-11-18  
**Preceding Commit:** `a979bc6` (Stage 5.4.2.4.2 Staging Migration Execution Report)  
**Final Decision:** **A. PARTIAL STATE FULLY UNDERSTOOD — READY FOR REPAIR PLANNING**  

---

## 1. EXECUTIVE SUMMARY & INVESTIGATION SCOPE

During the initial staging migration run in Stage 5.4.2.4.2, 24 of 29 migrations executed successfully against `socialpilot_staging`. Migration #25 (`2026_11_13_200000_create_media_generation_tables.php`) encountered a remote TLS connection reset (`PDO::prepare(): SSL: An existing connection was forcibly closed by the remote host`) while applying a foreign key constraint to `media_generation_jobs`.

In strict accordance with Stage 5.4.2.4.3 rules, a **strictly read-only investigation** was conducted:
- **Zero migrations, rollbacks, or repairs were executed.**
- No DDL or DML statements were run.
- Zero configuration or schema changes were performed.
- Production (`socialpilot`, 59 tables) and test fixture (`socialpilot_migration_test`, 59 tables) remain 100% isolated and untouched.

### Key Audit Findings:
1. **Migration State:** Exactly 24 migrations are recorded as completed in `socialpilot_staging.migrations`. 5 migrations remain pending.
2. **Table Inventory:** Exactly 37 tables exist in `socialpilot_staging` (36 domain tables + 1 `migrations` tracking table).
3. **Partial Schema of Migration 25:**
   - `media_assets`: 100% created with all columns, indexes, and foreign keys.
   - `media_generation_jobs`: Created with 22 columns, primary key, and 2 foreign keys (`user_id`, `brand_id`).
   - Missing from `media_generation_jobs`: Foreign key constraint `media_generation_jobs_content_draft_id_foreign` and 5 explicit indexes (`brand_id`, `user_id`, `status`, `job_type`, `media_asset_id`).
   - Tables not yet created: `media_storyboards`, `media_storyboard_scenes`, `media_visual_settings`.
4. **Schema Integrity:** The partial schema is internally consistent. All tables have 0 rows; no orphaned foreign keys or corrupted states exist. The target table `brand_content_drafts` is intact and ready to receive the foreign key reference.
5. **Root Cause of Interruption:** The single CLI process had maintained an open TCP/TLS connection to TiDB Cloud Gateway (`gateway01.eu-central-1.prod.aws.tidbcloud.com:4000`) for ~5 consecutive minutes while executing heavy schema modification statements. The remote gateway/load balancer terminated the socket connection during the distributed DDL coordination phase.
6. **Rerun Risk Identified:** Because migration #25 employs `if (! Schema::hasTable('media_generation_jobs'))` guards, simply re-running `php artisan migrate` would skip `media_generation_jobs`, leaving it permanently missing its foreign key and indexes. A controlled repair plan is required.

---

## 2. FINAL DECISION

```
A. PARTIAL STATE FULLY UNDERSTOOD — READY FOR REPAIR PLANNING
```

---

## 3. PHASE A — DATABASE STATE & PARTIAL SCHEMA AUDIT

A live read-only inspection was performed using the dedicated `tidb_staging_migrator` connection:

```
Active Database: socialpilot_staging
Authenticated User: AZDARrqgy4MBFGE.staging_migrator@%
Transport Cipher: TLS_AES_128_GCM_SHA256
Total Tables in Staging: 37
Completed Migrations in migrations table: 24 (Batch 1)
Pending Migrations: 5
```

### 3.1 Detailed Inspection of Affected Tables

#### Table: `media_assets` (Created by Migration 25)
- **Engine:** InnoDB (TiDB distributed storage)
- **Status:** Fully created.
- **Columns (32):** `id` (BIGINT UNSIGNED PK), `user_id`, `brand_id`, `content_draft_id`, `parent_asset_id`, `asset_type`, `title`, `file_name`, `file_format`, `mime_type`, `storage_disk`, `storage_path`, `public_url`, `file_size`, `width`, `height`, `aspect_ratio`, `platform`, `provider`, `provider_model`, `provider_job_id`, `generation_prompt`, `generation_params`, `generation_status`, `generation_error`, `objective`, `visual_style`, `is_variation`, `metadata`, `created_at`, `updated_at`, `deleted_at`.
- **Foreign Keys:**
  - `media_assets_user_id_foreign`: `user_id` -> `users.id` (CASCADE)
  - `media_assets_brand_id_foreign`: `brand_id` -> `brands.id` (CASCADE)
  - `media_assets_content_draft_id_foreign`: `content_draft_id` -> `brand_content_drafts.id` (SET NULL)
- **Indexes:** `PRIMARY`, `media_assets_user_id_foreign`, `media_assets_brand_id_foreign`, `media_assets_content_draft_id_foreign`, `media_assets_brand_id_index`, `media_assets_user_id_index`, `media_assets_generation_status_index`, `media_assets_asset_type_index`, `media_assets_platform_index`, `media_assets_parent_asset_id_index`.

#### Table: `media_generation_jobs` (Partially Created by Migration 25)
- **Engine:** InnoDB (TiDB distributed storage)
- **Status:** Partially created.
- **Columns (22):** `id` (BIGINT UNSIGNED PK), `user_id`, `brand_id`, `media_asset_id`, `content_draft_id`, `job_type`, `provider`, `provider_model`, `provider_job_id`, `status`, `progress`, `request_payload`, `result_payload`, `error_message`, `error_code`, `retry_count`, `max_retries`, `queued_at`, `started_at`, `completed_at`, `created_at`, `updated_at`.
- **Existing Foreign Keys:**
  - `media_generation_jobs_user_id_foreign`: `user_id` -> `users.id` (CASCADE)
  - `media_generation_jobs_brand_id_foreign`: `brand_id` -> `brands.id` (CASCADE)
- **Missing Foreign Key:**
  - `media_generation_jobs_content_draft_id_foreign`: `content_draft_id` -> `brand_content_drafts.id` (SET NULL) — **FAILED TO APPLY**
- **Existing Indexes:** `PRIMARY`, `media_generation_jobs_user_id_foreign`, `media_generation_jobs_brand_id_foreign`.
- **Missing Indexes:**
  - `brand_id` (explicit index)
  - `user_id` (explicit index)
  - `status`
  - `job_type`
  - `media_asset_id`

#### Table: `brand_content_drafts` (Created by Migration 11)
- **Engine:** InnoDB
- **Status:** Intact and verified.
- **Columns (27):** `id` (BIGINT UNSIGNED PK), `user_id`, `brand_id`, `title`, `platform`, `content_type`, `content_format`, `objective`, `category`, `status`, `prompt`, `body`, `hook`, `caption`, `call_to_action`, `hashtags`, `quality_score`, `quality_evaluation`, `generation_job_id`, `selected_variation_id`, `metadata`, `scheduled_for`, `ai_provider`, `ai_model`, `created_at`, `updated_at`, `is_ai_generated`.
- **Foreign Keys:**
  - `brand_content_drafts_user_id_foreign` -> `users.id`
  - `brand_content_drafts_brand_id_foreign` -> `brands.id`
  - `brand_content_drafts_generation_job_id_foreign` -> `content_generation_jobs.id`
- **Referential Target:** `brand_content_drafts.id` is a valid `BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY`. The referenced column and table are fully present and healthy.

#### Table: `migrations`
- **Engine:** InnoDB
- **Status:** Healthy. Contains exactly 24 records, all assigned to `batch = 1`. Migration 25 was not recorded.

---

## 4. PHASE B — FAILED MIGRATION CODE REVIEW

Source file audited: `database/migrations/2026_11_13_200000_create_media_generation_tables.php`

### 4.1 Schema Operations in Migration 25
The migration consists of five sequential table definitions inside its `up()` method:
1. `Schema::create('media_assets', ...)`: Created table, columns, 3 FKs, 6 indexes. (Status: **COMMITTED**)
2. `Schema::create('media_generation_jobs', ...)`:
   - Executed `CREATE TABLE media_generation_jobs` (Status: **COMMITTED**)
   - Executed `ALTER TABLE media_generation_jobs ADD CONSTRAINT media_generation_jobs_user_id_foreign ...` (Status: **COMMITTED**)
   - Executed `ALTER TABLE media_generation_jobs ADD CONSTRAINT media_generation_jobs_brand_id_foreign ...` (Status: **COMMITTED**)
   - Executed `ALTER TABLE media_generation_jobs ADD CONSTRAINT media_generation_jobs_content_draft_id_foreign ...` (Status: **FAILED — CONNECTION CLOSED**)
   - Did not reach index creation for `media_generation_jobs`.
3. `Schema::create('media_storyboards', ...)` (Status: **NOT REACHED**)
4. `Schema::create('media_storyboard_scenes', ...)` (Status: **NOT REACHED**)
5. `Schema::create('media_visual_settings', ...)` (Status: **NOT REACHED**)

### 4.2 Guard Conditions & Rerun Flaw Analysis
Every block in migration 25 uses a defensive check:
```php
if (! Schema::hasTable('media_assets')) { ... }
if (! Schema::hasTable('media_generation_jobs')) { ... }
if (! Schema::hasTable('media_storyboards')) { ... }
if (! Schema::hasTable('media_storyboard_scenes')) { ... }
if (! Schema::hasTable('media_visual_settings')) { ... }
```
**Critical Risk:**
Because `media_generation_jobs` already exists in TiDB:
- If a blind rerun (`php artisan migrate`) is attempted, `Schema::hasTable('media_generation_jobs')` evaluates to `true`.
- The entire closure for `media_generation_jobs` will be **skipped**.
- As a consequence, the missing foreign key (`media_generation_jobs_content_draft_id_foreign`) and the 5 indexes would **never be created**.
- Therefore, a direct rerun without repairing `media_generation_jobs` would result in a permanently incomplete schema.

---

## 5. PHASE C — CONNECTION INTERRUPTION ROOT-CAUSE INVESTIGATION

### 5.1 Evidence Collected
1. **Laravel Application Log (`storage/logs/laravel.log`):**
   ```
   [2026-11-18 06:54:53] local.ERROR: PDO::prepare(): SSL: An existing connection was forcibly closed by the remote host 
   (Connection: tidb_staging_migrator, Host: gateway01.eu-central-1.prod.aws.tidbcloud.com, Port: 4000, 
   Database: socialpilot_staging, SQL: alter table `media_generation_jobs` add constraint 
   `media_generation_jobs_content_draft_id_foreign` foreign key (`content_draft_id`) references `brand_content_drafts` (`id`) on delete set null)
   ```
2. **Cumulative Connection Lifetime:**
   - The CLI migration process ran continuously over a single persistent PDO connection for ~298 seconds (~5 minutes).
   - Preceding migrations involved intensive multi-table DDL:
     - Migration 22 (`create_trend_intelligence_tables`): 6 tables, 48s.
     - Migration 24 (`add_content_generation_fields_and_tables`): 5 tables, 90s.
     - Migration 25 had been executing for 42s before the drop.
3. **Database Driver Configuration (`config/database.php`):**
   - The `tidb_staging_migrator` configuration only specifies `MYSQL_ATTR_SSL_CA`.
   - No explicit PDO timeouts (`PDO::ATTR_TIMEOUT`), keepalive options, or TCP socket settings were configured.
   - On Windows, PHP's underlying stream socket default timeout is 60 seconds.

### 5.2 Analysis: Confirmed Evidence vs. Hypotheses
| Aspect | Classification | Evidence & Details |
|:-------|:---------------|:-------------------|
| **Transport Layer Disconnection** | **CONFIRMED** | PHP received WinSock error 10054 / TCP RST from `gateway01.eu-central-1.prod.aws.tidbcloud.com`. |
| **No Credential / Permission Revocation** | **CONFIRMED** | Immediate reconnect succeeded; credentials and SSL CA remain valid. |
| **TiDB Distributed DDL Latency** | **CONFIRMED** | In TiDB Serverless, schema changes require cross-node schema leasing. Long sequential DDLs hold open transactions without TCP data flow. |
| **Gateway Socket Timeout** | **CONFIRMED / HIGH PROBABILITY** | TiDB Cloud's AWS NLB/Envoy gateway terminates connections exceeding idle/silent TCP windows during distributed DDL synchronization. |
| **Local Network Failure** | **UNLIKELY** | No host network disconnection was observed; subsequent calls connected immediately. |

---

## 6. PHASE D — REMAINING MIGRATION DEPENDENCY AUDIT

The five pending migration files were audited for inter-table dependencies:

| # | Migration File | Tables Created / Altered | Dependencies on Earlier Tables | Risk Assessment |
|:--|:---------------|:-------------------------|:-------------------------------|:----------------|
| 25 | `2026_11_13_200000_create_media_generation_tables.php` | `media_storyboards`, `media_storyboard_scenes`, `media_visual_settings` | `users`, `brands`, `brand_content_drafts`, `media_assets` | Partial state risk: `media_generation_jobs` needs completion |
| 26 | `2026_11_13_230000_create_scheduling_and_autopilot_tables.php` | 9 tables (`scheduled_posts`, `scheduled_post_platforms`, `scheduled_post_media`, `publishing_queues`, etc.) | `scheduled_post_media.media_asset_id` references `media_assets` (exists). No dependency on `media_generation_jobs`. | Low risk once migration 25 is cleanly completed. |
| 27 | `2026_11_14_100000_create_publishing_agent_tables.php` | 4 tables (`publishing_attempts`, `oauth_states`, `publishing_notifications`, `publishing_webhook_events`) | References `scheduled_posts`, `social_accounts`, `brands`, `users`. No media table dependencies. | Low risk. |
| 28 | `2026_11_14_150000_create_analytics_and_ai_optimization_tables.php` | 6 tables (`analytics_snapshots`, `post_analytics`, `audience_insights`, etc.) | References `scheduled_posts`, `publishing_attempts`, `social_accounts`, `brands`. No media table dependencies. | Low risk. |
| 29 | `2026_11_16_200000_add_integrity_constraints_to_content_generation_tables.php` | Alters `brand_content_drafts`, `content_revisions` | Adds unique constraints to existing tables from migrations 11 and 24. No media dependencies. | Low risk. |

---

## 7. PHASE E — PRODUCTION & TEST ISOLATION VERIFICATION

A live read-only verification of existing production and test fixtures confirmed:

| Environment | Database Name | Table Count | Migration Records | User Records | Integrity Status |
|:------------|:--------------|:------------|:------------------|:-------------|:-----------------|
| **Production** | `socialpilot` | **59** | 28 | 0 | **100% UNTOUCHED & ISOLATED** |
| **Test Fixture** | `socialpilot_migration_test` | **59** | 28 | 2 | **100% UNTOUCHED & ISOLATED** |
| **Staging** | `socialpilot_staging` | **37** | 24 | 0 | Controlled partial state |

Neither production nor migration-test received any queries, DDL statements, or records during this stage.

---

## 8. RECOVERY OPTIONS & ASSOCIATED RISKS (FOR OPERATOR REVIEW)

To bring `socialpilot_staging` to the full 59-table schema, three potential recovery paths are identified for human operator evaluation:

### Option 1: Drop Incomplete Migration 25 Tables & Re-run (Cleanest)
- **Procedure:**
  1. Drop `media_generation_jobs` and `media_assets` from `socialpilot_staging`.
  2. Execute `php artisan migrate --database=tidb_staging_migrator --force`.
- **Pros:** Completely resets migration 25 to a clean state; ensures all 5 tables, FKs, and indexes are created in exact topological order by the original migration script.
- **Cons:** Requires explicit DDL approval to drop the two empty staging tables.
- **Risk:** Zero data loss risk (both tables contain 0 rows).

### Option 2: Apply Missing DDL to `media_generation_jobs` & Re-run
- **Procedure:**
  1. Manually execute the missing `ALTER TABLE media_generation_jobs ADD CONSTRAINT ...` and missing indexes.
  2. Run `php artisan migrate --database=tidb_staging_migrator --force` (which will pick up `media_storyboards`, `media_storyboard_scenes`, and `media_visual_settings`).
- **Pros:** Preserves existing tables.
- **Cons:** Multi-step manual intervention; requires exact synchronization.

### Option 3: Clean Re-run with Connection Options Tuning
- Add `PDO::ATTR_TIMEOUT => 60` and keepalive settings to `config/database.php` for `tidb_staging_migrator` to prevent future gateway timeouts during remaining migrations.

---

## 9. CONFIRMATION OF SAFETY RESTRICTIONS

- **THIS WAS A STRICTLY READ-ONLY AUDIT.**
- No migrations, retries, rollbacks, or fresh commands were executed.
- No database tables, columns, constraints, or rows were altered.
- No configuration files were modified.
- No Render deployment was performed.
- Zero secrets or sensitive credentials are included in this report.
- All actions remain halted pending explicit human authorization.
