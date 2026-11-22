# STAGE 5.5.3.2 — Production Database Migration Audit & Alignment Report

**Date:** 2026-11-22  
**Mode:** READ-ONLY — No schema changes, no data mutations, no environment changes  
**Executed as:** `AZDARrqgy4MBFGE.root` (admin, read-only queries only)

---

## 1. Executive Summary

**Gap:** Production database `socialpilot` is behind by exactly **1 migration** relative to the codebase and staging.

| Database             | Migrations Run | Local Files | Pending |
|----------------------|---------------|-------------|---------|
| `socialpilot` (prod) | 28            | 29          | **1**   |
| `socialpilot_staging`| 29            | 29          | 0       |

The gap is **safe to close** — the pending migration adds two unique indexes and includes pre-flight duplicate checks. No duplicate data exists in production. The migration has a fully reversible `down()` method.

---

## 2. Production Migration State

All 28 previously run migrations confirmed present in `socialpilot.migrations`:

| Batch | Migration |
|-------|-----------|
| 1 | `0001_01_01_000000_create_users_table` |
| 1 | `0001_01_01_000001_create_cache_table` |
| 1 | `0001_01_01_000002_create_jobs_table` |
| 1 | `2026_11_09_161931_create_brands_table` |
| 1 | `2026_11_09_161932_create_brand_settings_table` |
| 1 | `2026_11_09_161933_create_social_accounts_table` |
| 1 | `2026_11_09_161934_create_social_account_tokens_table` |
| 1 | `2026_11_09_161935_create_plans_table` |
| 1 | `2026_11_09_161936_create_subscriptions_table` |
| 1 | `2026_11_09_170457_create_personal_access_tokens_table` |
| 1 | `2026_11_09_171000_create_brand_content_drafts_table` |
| 1 | `2026_11_09_180000_create_brand_posts_table` |
| 1 | `2026_11_09_181000_create_brand_analytics_table` |
| 1 | `2026_11_09_182000_create_campaigns_table` |
| 1 | `2026_11_09_182001_add_campaign_id_to_brand_posts_table` |
| 1 | `2026_11_09_183000_create_brand_members_table` |
| 1 | `2026_11_09_183001_create_brand_approval_requests_table` |
| 1 | `2026_11_10_211500_add_terms_acceptance_to_users_table` |
| 1 | `2026_11_11_150000_extend_brand_profiles_and_social_tokens` |
| 1 | `2026_11_12_085000_create_notifications_table` |
| 1 | `2026_11_12_120000_repair_dashboard_notifications_and_add_agent_executions` |
| 1 | `2026_11_13_090000_create_trend_intelligence_tables` |
| 1 | `2026_11_13_093000_add_content_angle_status_to_trending_topics` |
| 1 | `2026_11_13_131818_add_content_generation_fields_and_tables` |
| 1 | `2026_11_13_200000_create_media_generation_tables` |
| 1 | `2026_11_13_230000_create_scheduling_and_autopilot_tables` |
| 1 | `2026_11_14_100000_create_publishing_agent_tables` |
| 1 | `2026_11_14_150000_create_analytics_and_ai_optimization_tables` |

---

## 3. Pending Migration Analysis

### Migration: `2026_11_16_200000_add_integrity_constraints_to_content_generation_tables`

**File:** `database/migrations/2026_11_16_200000_add_integrity_constraints_to_content_generation_tables.php`

#### What it does

| Step | Action | Table | Detail |
|------|--------|-------|--------|
| Pre-flight 1 | SELECT + GROUP BY | `brand_content_drafts` | Aborts if any `generation_job_id` appears more than once |
| Pre-flight 2 | SELECT + GROUP BY | `content_revisions` | Aborts if any `(draft_id, revision_number)` pair appears more than once |
| DDL 1 | `ADD UNIQUE INDEX` | `brand_content_drafts` | Index name: `bcd_generation_job_id_unique` on column `generation_job_id` |
| DDL 2 | `ADD UNIQUE INDEX` | `content_revisions` | Index name: `cr_draft_id_revision_number_unique` on columns `(draft_id, revision_number)` |

#### Reversibility

`down()` method drops both unique indexes. Fully reversible with zero data loss.

#### Risk classification

| Risk Factor | Assessment |
|-------------|------------|
| Data deletion | **None** — index-only operation |
| Row mutation | **None** — read-only pre-flight, then DDL only |
| Table lock | **Minimal** — TiDB executes online DDL (non-blocking) |
| Downtime | **None expected** — online index build |
| Rollback | **Safe** — `down()` drops indexes |

---

## 4. Pre-Flight Duplicate Check Results (Production)

> [!IMPORTANT]
> These checks were run on live production data as part of this read-only audit.
> Both checks passed — the migration can safely proceed when authorized.

| Check | Table | Duplicate Rows Found | Status |
|-------|-------|---------------------|--------|
| Duplicate `generation_job_id` | `brand_content_drafts` | **0** | PASS |
| Duplicate `(draft_id, revision_number)` | `content_revisions` | **0** | PASS |

---

## 5. Current Index State in Production

### `brand_content_drafts` — existing indexes

| Index Name | Present |
|------------|---------|
| `PRIMARY` | YES |
| `brand_content_drafts_user_id_foreign` | YES |
| `brand_content_drafts_brand_id_foreign` | YES |
| `brand_content_drafts_brand_id_status_index` | YES |
| `brand_content_drafts_is_ai_generated_index` | YES |
| `brand_content_drafts_generation_job_id_foreign` | YES |
| `bcd_generation_job_id_unique` | **MISSING** (pending migration) |

### `content_revisions` — existing indexes

| Index Name | Present |
|------------|---------|
| `PRIMARY` | YES |
| `content_revisions_draft_id_foreign` | YES |
| `content_revisions_user_id_foreign` | YES |
| `content_revisions_draft_id_revision_number_index` | YES |
| `cr_draft_id_revision_number_unique` | **MISSING** (pending migration) |

---

## 6. TLS & Connection Verification

| Item | Value |
|------|-------|
| Admin connection user | `AZDARrqgy4MBFGE.root@%` |
| Production database verified | `socialpilot` |
| TLS cipher (production) | `TLS_AES_128_GCM_SHA256` |
| Staging database verified | `socialpilot_staging` |

---

## 7. Required Migration Account

The pending migration requires **DDL privileges** (`ALTER`, `INDEX`) on `socialpilot.*`. This is explicitly **not** granted to `AZDARrqgy4MBFGE.prod_app` by design.

A dedicated **production migrator account** is required for this operation, following the same pattern as `AZDARrqgy4MBFGE.staging_migrator` used on staging.

> [!WARNING]
> `prod_app` must NOT be used to run migrations. It has only SELECT/INSERT/UPDATE/DELETE and will fail on DDL operations. This is correct behavior.

---

## 8. Migration Execution Plan (Pending Authorization)

The following plan is documented here but **will not be executed without separate explicit authorization**.

### Account required

A production migrator account (`AZDARrqgy4MBFGE.prod_migrator`) must be provisioned with:

```sql
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, INDEX, DROP,
      CREATE TEMPORARY TABLES, REFERENCES
ON `socialpilot`.*
TO 'AZDARrqgy4MBFGE.prod_migrator'@'%';
```

Scope: `socialpilot.*` only. No global privileges. No GRANT OPTION.

### Execution method

Via a dedicated migration script (using `prod_migrator` credentials) or via Render one-off command:

```bash
php artisan migrate --database=tidb_prod_migrator --force
```

### Pre-execution checklist

- [ ] `prod_migrator` account provisioned and verified
- [ ] Duplicate pre-flight checks re-run immediately before execution
- [ ] Connection verified against `socialpilot` (not staging)
- [ ] `staging_migrator` or `prod_app` NOT used

### Rollback procedure

```bash
php artisan migrate:rollback --database=tidb_prod_migrator --step=1 --force
```

Runs `down()` which drops both unique indexes. Zero data loss.

---

## 9. Audit Integrity Confirmation

This report is audit-only. The following were NOT performed:

- NO migration executed
- NO schema altered
- NO data mutated
- NO environment variables modified
- NO Render production service touched
- NO credentials printed or exposed
- Staging database queried read-only only

---

## 10. Final Decision

**Decision: B — One pending migration identified; execution plan documented; separate authorization required before running.**

| Item | Status |
|------|--------|
| Production vs codebase gap | 1 migration behind |
| Migration type | DDL only (unique indexes) — no data change |
| Data safety pre-flight | PASSED (0 duplicates in both tables) |
| Migration reversibility | SAFE (rollback drops indexes) |
| Downtime risk | NONE (TiDB online DDL) |
| Account required for migration | `prod_migrator` (not yet provisioned) |
| Authorization status | PENDING — do not execute until separately authorized |

**Next logical stages:**
- **5.5.3.3** — Provision `AZDARrqgy4MBFGE.prod_migrator` (requires separate authorization)
- **5.5.3.4** — Production Render environment variable configuration (`DB_USERNAME=prod_app`, Supabase vars)
- **5.5.3.5** — Execute the production migration (requires separate authorization)
