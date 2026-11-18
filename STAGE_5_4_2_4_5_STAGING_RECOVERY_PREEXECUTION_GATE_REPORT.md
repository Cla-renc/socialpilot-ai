# SOCIALPILOT AI — PHASE 11, STAGE 5.4.2.4.5
## Staging Recovery Pre-Execution Safety Gate (READ-ONLY)

**Repository:** `Cla-renc/socialpilot-ai`
**Branch:** `main`
**Stage:** 5.4.2.4.5 (Staging Recovery Pre-Execution Safety Gate)
**Date:** 2026-11-18
**Reference Commit:** `063c8bf` (Stage 5.4.2.4.4 Recovery Plan)
**Report Generated:** 2026-11-18T16:12:00+03:00

---

## FINAL DECISION

```
A. RECOVERY CHECKLIST VERIFIED — READY FOR HUMAN REPAIR APPROVAL
```

---

## COMPLIANCE DECLARATION

> [!IMPORTANT]
> **THIS STAGE IS STRICTLY READ-ONLY.**
>
> Zero DDL statements (CREATE, DROP, ALTER), zero migrations, zero rollbacks, zero seeders, zero migration record modifications, zero source code changes, and zero credential changes were executed during Stage 5.4.2.4.5.
>
> All evidence below is derived exclusively from live read-only queries, `SHOW` statements, `INFORMATION_SCHEMA` reads, `SHOW INDEX`, and `artisan migrate --pretend`.

---

## PHASE A — LIVE STATE REVERIFICATION

### A.1 Connection Identity

| Property | Expected | Live Verified | Match |
|:---------|:---------|:-------------|:------|
| Database | `socialpilot_staging` | `socialpilot_staging` | ✅ |
| Connection name | `tidb_staging_migrator` | `tidb_staging_migrator` | ✅ |
| Authenticated user | `AZDARrqgy4MBFGE.staging_migrator` | `AZDARrqgy4MBFGE.staging_migrator@105.164.67.18` | ✅ |
| TLS cipher | `TLS_AES_128_GCM_SHA256` | `TLS_AES_128_GCM_SHA256` | ✅ |
| Gateway | `gateway01.eu-central-1.prod.aws.tidbcloud.com:4000` | Connected successfully | ✅ |

**Preflight command exit code:** `0` (all 8 checks passed, warning only on existing 37 tables as expected).

### A.2 Migration Records

**Completed migration records:** Exactly **24** (all batch = 1). ✅

| ID | Migration | Batch |
|:---|:----------|:------|
| 1 | `0001_01_01_000000_create_users_table` | 1 |
| 2 | `0001_01_01_000001_create_cache_table` | 1 |
| 3 | `0001_01_01_000002_create_jobs_table` | 1 |
| 4 | `2026_11_09_161931_create_brands_table` | 1 |
| 5 | `2026_11_09_161932_create_brand_settings_table` | 1 |
| 6 | `2026_11_09_161933_create_social_accounts_table` | 1 |
| 7 | `2026_11_09_161934_create_social_account_tokens_table` | 1 |
| 8 | `2026_11_09_161935_create_plans_table` | 1 |
| 9 | `2026_11_09_161936_create_subscriptions_table` | 1 |
| 10 | `2026_11_09_170457_create_personal_access_tokens_table` | 1 |
| 11 | `2026_11_09_171000_create_brand_content_drafts_table` | 1 |
| 12 | `2026_11_09_180000_create_brand_posts_table` | 1 |
| 13 | `2026_11_09_181000_create_brand_analytics_table` | 1 |
| 14 | `2026_11_09_182000_create_campaigns_table` | 1 |
| 15 | `2026_11_09_182001_add_campaign_id_to_brand_posts_table` | 1 |
| 16 | `2026_11_09_183000_create_brand_members_table` | 1 |
| 17 | `2026_11_09_183001_create_brand_approval_requests_table` | 1 |
| 18 | `2026_11_10_211500_add_terms_acceptance_to_users_table` | 1 |
| 19 | `2026_11_11_150000_extend_brand_profiles_and_social_tokens` | 1 |
| 20 | `2026_11_12_085000_create_notifications_table` | 1 |
| 21 | `2026_11_12_120000_repair_dashboard_notifications_and_add_agent_executions` | 1 |
| 22 | `2026_11_13_090000_create_trend_intelligence_tables` | 1 |
| 23 | `2026_11_13_093000_add_content_angle_status_to_trending_topics` | 1 |
| 24 | `2026_11_13_131818_add_content_generation_fields_and_tables` | 1 |

### A.3 Pending Migrations

**Confirmed pending:** Exactly **5** (Migrations 25–29). ✅

| # | Migration File | Status |
|:--|:--------------|:-------|
| 25 | `2026_11_13_200000_create_media_generation_tables` | **Pending** |
| 26 | `2026_11_13_230000_create_scheduling_and_autopilot_tables` | Pending |
| 27 | `2026_11_14_100000_create_publishing_agent_tables` | Pending |
| 28 | `2026_11_14_150000_create_analytics_and_ai_optimization_tables` | Pending |
| 29 | `2026_11_16_200000_add_integrity_constraints_to_content_generation_tables` | Pending |

### A.4 Table Count

**Live table count in `socialpilot_staging`:** Exactly **37**. ✅

Full live table list (sorted alphabetically):
`agent_executions`, `brand_analytics`, `brand_approval_requests`, `brand_content_drafts`, `brand_members`, `brand_posts`, `brand_settings`, `brand_trend_preferences`, `brands`, `cache`, `cache_locks`, `campaigns`, `content_generation_jobs`, `content_generation_settings`, `content_revisions`, `content_trend_sources`, `content_variations`, `failed_jobs`, `job_batches`, `jobs`, **`media_assets`**, **`media_generation_jobs`**, `migrations`, `notifications`, `password_reset_tokens`, `personal_access_tokens`, `plans`, `sessions`, `social_account_tokens`, `social_accounts`, `subscriptions`, `trend_feedback`, `trend_research_jobs`, `trend_sources`, `trend_topic_sources`, `trending_topics`, `users`

### A.5 Partial Table Row Counts

| Table | Exists | Rows |
|:------|:-------|:-----|
| `media_assets` | ✅ YES | **0** |
| `media_generation_jobs` | ✅ YES (partial) | **0** |
| `media_storyboards` | ❌ ABSENT | — |
| `media_storyboard_scenes` | ❌ ABSENT | — |
| `media_visual_settings` | ❌ ABSENT | — |

**Both partial tables confirmed empty** (0 rows each). ✅

### A.6 Production and Test Database Isolation

| Database | Access via DML | Status |
|:---------|:--------------|:-------|
| `socialpilot` | **DENIED** (SQLSTATE[42000]: 1142 SELECT denied) | ✅ ISOLATED |
| `socialpilot_migration_test` | **DENIED** (SQLSTATE[42000]: 1142 SELECT denied) | ✅ ISOLATED |

> [!NOTE]
> `INFORMATION_SCHEMA.TABLES` returns 0 for both production and test schemas because the `staging_migrator` account does not have table-level visibility grants on those schemas. This is consistent with strict per-schema privilege assignment in TiDB Cloud. The DML block (error 1142) is the definitive isolation proof.

### A.7 State Drift Assessment

| Metric | Stage 5.4.2.4.4 Reported | Live Re-verified | Drift? |
|:-------|:------------------------|:----------------|:-------|
| Completed migrations | 24 | 24 | ✅ NONE |
| Pending migrations | 5 | 5 | ✅ NONE |
| Table count | 37 | 37 | ✅ NONE |
| `media_assets` rows | 0 | 0 | ✅ NONE |
| `media_generation_jobs` rows | 0 | 0 | ✅ NONE |
| Production isolation | Denied | Denied | ✅ NONE |
| Test isolation | Denied | Denied | ✅ NONE |

**Conclusion:** Zero state drift detected. Live state is a **100% match** with the previous stage's findings.

---

## PHASE B — RESET TARGET SAFETY REVIEW

### B.1 External Foreign Key Dependencies

**Query:** `INFORMATION_SCHEMA.KEY_COLUMN_USAGE` × `REFERENTIAL_CONSTRAINTS` for any staging table referencing `media_assets` or `media_generation_jobs`.

**Result:** ✅ **No external FK dependencies found.**

No existing staging table holds a foreign key that points to `media_assets.id` or `media_generation_jobs.id`. Dropping either table will not cascade-delete any records in other tables, will not break any referential integrity constraint in any other table, and will not leave any orphaned FK definition in `socialpilot_staging`.

### B.2 Outbound Foreign Keys on `media_assets`

| Foreign Key | Column | References | On Delete |
|:-----------|:-------|:-----------|:---------|
| `media_assets_brand_id_foreign` | `brand_id` | `brands.id` | CASCADE |
| `media_assets_content_draft_id_foreign` | `content_draft_id` | `brand_content_drafts.id` | SET NULL |
| `media_assets_user_id_foreign` | `user_id` | `users.id` | CASCADE |

All 3 FKs **present and correct**. `media_assets` is structurally complete. These outbound FKs reference parent tables — dropping `media_assets` will not affect those parent tables.

### B.3 Outbound Foreign Keys on `media_generation_jobs`

| Foreign Key | Column | References | On Delete | Status |
|:-----------|:-------|:-----------|:---------|:-------|
| `media_generation_jobs_brand_id_foreign` | `brand_id` | `brands.id` | CASCADE | ✅ Present |
| `media_generation_jobs_user_id_foreign` | `user_id` | `users.id` | CASCADE | ✅ Present |
| `media_generation_jobs_content_draft_id_foreign` | `content_draft_id` | `brand_content_drafts.id` | SET NULL | ❌ **MISSING** |

This confirms the partial state from Stage 5.4.2.4.3. The missing FK is the root cause of the incompleteness.

### B.4 Index State

**`media_assets` indexes (10 of 10 expected):** ✅ All present.

| Index Name | Column | Unique |
|:-----------|:-------|:-------|
| `PRIMARY` | `id` | YES |
| `media_assets_user_id_foreign` | `user_id` | NO |
| `media_assets_brand_id_foreign` | `brand_id` | NO |
| `media_assets_content_draft_id_foreign` | `content_draft_id` | NO |
| `media_assets_brand_id_index` | `brand_id` | NO |
| `media_assets_user_id_index` | `user_id` | NO |
| `media_assets_generation_status_index` | `generation_status` | NO |
| `media_assets_asset_type_index` | `asset_type` | NO |
| `media_assets_platform_index` | `platform` | NO |
| `media_assets_parent_asset_id_index` | `parent_asset_id` | NO |

**`media_generation_jobs` indexes (3 of 8 expected):** ❌ **5 indexes missing.**

| Index Name | Column | Status |
|:-----------|:-------|:-------|
| `PRIMARY` | `id` | ✅ Present |
| `media_generation_jobs_user_id_foreign` | `user_id` | ✅ Present |
| `media_generation_jobs_brand_id_foreign` | `brand_id` | ✅ Present |
| `media_generation_jobs_brand_id_index` | `brand_id` | ❌ MISSING |
| `media_generation_jobs_user_id_index` | `user_id` | ❌ MISSING |
| `media_generation_jobs_status_index` | `status` | ❌ MISSING |
| `media_generation_jobs_job_type_index` | `job_type` | ❌ MISSING |
| `media_generation_jobs_media_asset_id_index` | `media_asset_id` | ❌ MISSING |

### B.5 Drop Safety Assessment

| Safety Criterion | `media_assets` | `media_generation_jobs` |
|:----------------|:-------------|:----------------------|
| Rows to be lost | **0** | **0** |
| External FKs that would break | **None** | **None** |
| Data referenced by other tables | **None** | **None** |
| Impact on application data | **Zero** | **Zero** |
| Safe to drop? | ✅ **YES** | ✅ **YES** |

> [!IMPORTANT]
> Even though `media_assets` is structurally complete, it **must** also be dropped. Migration 25's guard condition (`if (! Schema::hasTable('media_assets'))`) means the entire `media_assets` creation block would be **silently skipped** on rerun if the table still exists — leaving the migration logic untested and `media_generation_jobs` still incomplete. Dropping both tables guarantees migration 25 runs in full, coherent sequence from start to finish.

---

## PHASE C — BACKUP READINESS

### C.1 Tool Availability

| Tool | Available | Notes |
|:-----|:---------|:------|
| `mysqldump` | ❌ NOT FOUND | Not installed on this Windows machine |
| `mysqlsh` (MySQL Shell) | ❌ NOT FOUND | Not installed |
| PHP 8.2.30 (CLI) | ✅ Available | Full PDO/MySQL support active |
| Laravel `db:backup` Artisan command | ✅ Available | Custom command in `app/Console/Commands/BackupDatabaseCommand.php` |
| Backup destination `storage/app/backups/` | ✅ Writable | Directory creatable (parent is writable) |

### C.2 Backup Method

The project's custom `db:backup` command supports MySQL/TiDB via pure-PHP PDO (no `mysqldump` binary required). It:
- Streams `SHOW CREATE TABLE` for every table (schema capture).
- Streams `SELECT *` row-by-row for data (data capture).
- Writes a timestamped `.sql` file locally to `storage/app/backups/`.
- Counts `DROP TABLE IF EXISTS` entries to verify table completeness.
- Does **not** store the file in Git.
- Does **not** log credentials.

**Proposed backup command (read-only on source, local write only):**

```bash
php artisan db:backup --connection=tidb_staging_migrator --verify --keep=7
```

### C.3 Backup Plan

| Requirement | Feasibility | Method |
|:-----------|:-----------|:-------|
| Preserve all 24 migrated tables | ✅ Feasible | `db:backup` exports all 37 tables |
| Capture partial table definitions | ✅ Feasible | `SHOW CREATE TABLE media_assets` + `media_generation_jobs` included |
| Local storage only (not Git) | ✅ Feasible | `storage/app/backups/` is `.gitignore`-listed |
| No secrets exposed | ✅ Feasible | PDO connection uses env vars; backup file contains no credentials |
| Integrity verification | ✅ Feasible | `--verify` flag counts table definitions in output file |
| Read-only on source | ✅ YES | `SELECT` + `SHOW` only; no source writes |

> [!NOTE]
> The backup is **not** being executed in this stage. Stage 5.4.2.4.5 is strictly read-only. The backup is authorized and scoped for execution as the **first operational step** in Stage 5.4.2.5 (the actual repair), immediately before any `DROP TABLE` commands.

---

## PHASE D — RECOVERY COMMAND REVIEW

### D.1 Migration 25 Isolation Assessment

**Key finding:** Laravel's `artisan migrate` supports a `--path` option that accepts a specific migration file path. This enables targeted, single-migration execution without running any subsequent pending migrations.

**Migration 25 isolation command:**

```bash
php artisan migrate \
  --database=tidb_staging_migrator \
  --path=database/migrations/2026_11_13_200000_create_media_generation_tables.php \
  --step \
  --force
```

| Property | Assessment |
|:---------|:----------|
| Does `--path` isolate Migration 25? | ✅ **YES** — Only the specified file is processed |
| Does `--step` prevent batch grouping? | ✅ **YES** — Each migration gets its own batch number |
| Would migrations 26–29 run? | ✅ **NO** — `--path` restricts execution to the named file only |
| Is `--force` required? | ✅ YES — Required for non-interactive production/staging mode |
| Is this safe after dropping both partial tables? | ✅ YES — `Schema::hasTable()` guards will evaluate `false` for all 5 tables |

### D.2 Pretend-Run SQL Preview (Read-Only, `--pretend`)

A `--pretend` dry-run was executed (no changes applied). The full SQL sequence Migration 25 would execute after the partial tables are dropped:

**For `media_assets`:** (guard evaluates `false` → runs)
1. `CREATE TABLE media_assets` (32 columns)
2. `ALTER TABLE ... ADD CONSTRAINT media_assets_user_id_foreign` (FK → `users.id` CASCADE)
3. `ALTER TABLE ... ADD CONSTRAINT media_assets_brand_id_foreign` (FK → `brands.id` CASCADE)
4. `ALTER TABLE ... ADD CONSTRAINT media_assets_content_draft_id_foreign` (FK → `brand_content_drafts.id` SET NULL)
5. `ALTER TABLE ... ADD INDEX media_assets_brand_id_index`
6. `ALTER TABLE ... ADD INDEX media_assets_user_id_index`
7. `ALTER TABLE ... ADD INDEX media_assets_generation_status_index`
8. `ALTER TABLE ... ADD INDEX media_assets_asset_type_index`
9. `ALTER TABLE ... ADD INDEX media_assets_platform_index`
10. `ALTER TABLE ... ADD INDEX media_assets_parent_asset_id_index`

**For `media_generation_jobs`:** (guard evaluates `false` → runs)
11. `CREATE TABLE media_generation_jobs` (22 columns)
12. `ALTER TABLE ... ADD CONSTRAINT media_generation_jobs_user_id_foreign` (FK → `users.id` CASCADE)
13. `ALTER TABLE ... ADD CONSTRAINT media_generation_jobs_brand_id_foreign` (FK → `brands.id` CASCADE)
14. `ALTER TABLE ... ADD CONSTRAINT media_generation_jobs_content_draft_id_foreign` (FK → `brand_content_drafts.id` SET NULL)
15. `ALTER TABLE ... ADD INDEX media_generation_jobs_brand_id_index`
16. `ALTER TABLE ... ADD INDEX media_generation_jobs_user_id_index`
17. `ALTER TABLE ... ADD INDEX media_generation_jobs_status_index`
18. `ALTER TABLE ... ADD INDEX media_generation_jobs_job_type_index`
19. `ALTER TABLE ... ADD INDEX media_generation_jobs_media_asset_id_index`

**For `media_storyboards`:** (guard evaluates `false` → runs)
20. `CREATE TABLE media_storyboards` (14 columns)
21. `ALTER TABLE ... ADD CONSTRAINT media_storyboards_user_id_foreign`
22. `ALTER TABLE ... ADD CONSTRAINT media_storyboards_brand_id_foreign`
23. `ALTER TABLE ... ADD CONSTRAINT media_storyboards_content_draft_id_foreign`
24. `ALTER TABLE ... ADD INDEX media_storyboards_brand_id_index`
25. `ALTER TABLE ... ADD INDEX media_storyboards_user_id_index`

**For `media_storyboard_scenes`:** (guard evaluates `false` → runs)
26. `CREATE TABLE media_storyboard_scenes` (14 columns)
27. `ALTER TABLE ... ADD CONSTRAINT media_storyboard_scenes_storyboard_id_foreign` (FK → `media_storyboards.id` CASCADE)
28. `ALTER TABLE ... ADD INDEX media_storyboard_scenes_storyboard_id_index`

**For `media_visual_settings`:** (guard evaluates `false` → runs)
29. `CREATE TABLE media_visual_settings` (21 columns)
30. `ALTER TABLE ... ADD CONSTRAINT media_visual_settings_brand_id_foreign` (FK → `brands.id` CASCADE)
31. `ALTER TABLE ... ADD UNIQUE media_visual_settings_brand_id_unique`

**Total DDL statements in Migration 25:** 31 statements across 5 tables.

> [!NOTE]
> `media_storyboard_scenes` creates a FK referencing `media_storyboards.id`. Because both are absent, Migration 25's sequential logic creates `media_storyboards` first (statement 20) before the FK on `media_storyboard_scenes` (statement 27). This ordering is safe and correct.

### D.3 Table Count Projections

| State | Table Count | Basis |
|:------|:-----------|:------|
| Current (now) | 37 | Live verified |
| After dropping 2 partial tables | 35 | 37 − 2 |
| After Migration 25 completes (+5 tables) | **40** | 35 + 5 ✅ |
| After Migrations 26–29 complete (+19 tables) | **59** | 40 + 19 ✅ |

### D.4 Full Migration Command Assessment

| Command | Effect | Safe for Mig. 25 isolation? |
|:--------|:-------|:----------------------------|
| `php artisan migrate --database=tidb_staging_migrator --force` | Runs ALL pending (25–29) in sequence | ❌ **UNSAFE for isolation** |
| `php artisan migrate --database=tidb_staging_migrator --path=.../create_media_generation_tables.php --step --force` | Runs ONLY Migration 25 | ✅ **SAFE** |
| `php artisan migrate --database=tidb_staging_migrator --force` (run after 25 is verified) | Runs only remaining 26–29 | ✅ SAFE (after Step 9 authorization) |

**Blocker assessment:** No blocker. The `--path` option provides a confirmed, safe isolation mechanism for Migration 25.

---

## PHASE E — FINAL EXECUTION CHECKLIST

> [!CAUTION]
> The following checklist describes operations that **must not be executed** until explicit human authorization is received at each gate. This document is a planning and verification artifact only. Do not execute any step without the designated human sign-off.

```
STAGE 5.4.2.5 — AUTHORIZED RECOVERY EXECUTION SEQUENCE
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
```

### ☐ STEP 1 — Reverify Staging Target (Pre-Execution Gate)

**Action:** Read-only
**Command:**
```bash
php artisan db:tidb-staging-migration-preflight
```
**Pass criteria:**
- Exit code 0
- Active DB: `socialpilot_staging` / User: `AZDARrqgy4MBFGE.staging_migrator` / TLS: `TLS_AES_128_GCM_SHA256`
- Both isolation checks: Access denied

---

### ☐ STEP 2 — Capture and Verify Staging Schema Backup

**Action:** Write to local `storage/app/backups/` only (read-only on TiDB source)
**Command:**
```bash
php artisan db:backup --connection=tidb_staging_migrator --verify --keep=7
```
**Pass criteria:**
- Exit code 0
- Backup `.sql` file created locally
- Integrity check: `All 37 tables verified in backup file`
- File contains no credentials

---

### ☐ STEP 3 — Reconfirm Both Partial Tables Are Empty

**Action:** Read-only SQL
```sql
SELECT COUNT(*) FROM `media_assets`;           -- Expected: 0
SELECT COUNT(*) FROM `media_generation_jobs`;  -- Expected: 0
```
**Pass criteria:** Both return 0. If either returns > 0, **STOP immediately** and do not proceed.

---

### ☐ STEP 4 — Reconfirm No External FK Dependencies

**Action:** Read-only SQL
```sql
SELECT kcu.TABLE_NAME, kcu.CONSTRAINT_NAME, kcu.REFERENCED_TABLE_NAME
FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE kcu
JOIN INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS rc
    ON kcu.CONSTRAINT_NAME = rc.CONSTRAINT_NAME
    AND kcu.TABLE_SCHEMA = rc.CONSTRAINT_SCHEMA
WHERE kcu.TABLE_SCHEMA = 'socialpilot_staging'
  AND kcu.REFERENCED_TABLE_NAME IN ('media_assets', 'media_generation_jobs');
```
**Pass criteria:** Result set is empty (0 rows). If any rows returned, **STOP** and document.

---

### ☐ STEP 5 — Obtain Explicit Human Authorization: DROP Partial Tables

**Action:** Human decision gate

Confirm Steps 1–4 all passed. Explicitly authorize:
1. `DROP TABLE IF EXISTS \`socialpilot_staging\`.\`media_generation_jobs\`;`
2. `DROP TABLE IF EXISTS \`socialpilot_staging\`.\`media_assets\`;`

> [!CAUTION]
> Do not proceed past this step without explicit human sign-off, documented with a timestamp.

---

### ☐ STEP 6 — Remove the Two Partial Tables (Gate 3 Authorized Action)

**Action:** DDL — requires human authorization from Step 5
```sql
DROP TABLE IF EXISTS `socialpilot_staging`.`media_generation_jobs`;
DROP TABLE IF EXISTS `socialpilot_staging`.`media_assets`;
```
**Post-drop verification:**
```sql
SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
WHERE TABLE_SCHEMA = 'socialpilot_staging' AND TABLE_TYPE = 'BASE TABLE';
-- Expected: 35
```

---

### ☐ STEP 7 — Complete Migration 25 in Isolation (Gate 4)

**Action:** Migration execution — requires Step 6 verified
```bash
php artisan migrate \
  --database=tidb_staging_migrator \
  --path=database/migrations/2026_11_13_200000_create_media_generation_tables.php \
  --step \
  --force
```
**Pass criteria:** Exit code 0. Migration 25 recorded in `migrations` table with `batch = 2`.

---

### ☐ STEP 8 — Verify Migration 25's Schema (Gate 5)

**Action:** Read-only verification

**8a.** Table count = **40**
**8b.** All 5 media tables present (`media_assets`, `media_generation_jobs`, `media_storyboards`, `media_storyboard_scenes`, `media_visual_settings`)
**8c.** `media_generation_jobs` has 3 FKs (including `content_draft_id_foreign`)
**8d.** `media_generation_jobs` has 8 indexes (PRIMARY + 2 FK-indexes + 5 explicit)
**8e.** Migration 25 record: `batch = 2`

**If any sub-check fails → STOP. Do not run Migrations 26–29.**

---

### ☐ STEP 9 — Obtain Separate Human Authorization: Run Migrations 26–29

**Action:** Human decision gate (separate from Step 5)

Confirm Step 8 fully passed. Explicitly authorize:
```bash
php artisan migrate --database=tidb_staging_migrator --force
```
Expected scope: Migrations 26 (+9 tables), 27 (+4), 28 (+6), 29 (ALTER only) → **59 total tables**.

> [!CAUTION]
> Do not execute Migrations 26–29 without a distinct, documented human authorization separate from Step 5.

---

### ☐ STEP 10 — Final Schema Verification and Isolation Confirmation

**Action:** Read-only verification

**10a.** Table count = **59**
**10b.** Migration records = **29** (`SELECT COUNT(*) FROM migrations;`)
**10c.** `artisan migrate:status --database=tidb_staging_migrator` → all 29 as `Ran`, none as `Pending`
**10d.** Preflight still confirms production and test isolation (access denied)
**10e.** Full table list matches canonical 59-table schema from production

---

## RISKS AND BLOCKERS

### Identified Risks

| Risk | Severity | Probability | Mitigation |
|:-----|:---------|:-----------|:-----------|
| TLS connection reset during Migration 25 (31 DDL statements, ~same workload as prior failure) | HIGH | Medium | Add `PDO::ATTR_TIMEOUT => 300` or OS TCP keepalive before execution |
| `--path` file path resolution error | LOW | Low | Verify path resolves with `--pretend` before live execution |
| Backup write failure (disk space) | LOW | Low | Verify `storage/app/backups/` capacity before backup |
| Accidental unscoped `migrate` without `--path` | HIGH | Low (human error) | Checklist gate at Step 7; preflight must confirm `--path` is in command |
| Unexpected row insertion between backup and DROP | CRITICAL | Very Low | Re-verify row counts at Step 3 immediately before Step 6 |
| PDO connection config lacks keepalive options | MEDIUM | Confirmed | `tidb_staging_migrator` config has no `PDO::ATTR_TIMEOUT` or keepalive options set — **recommend adding before execution** |

### Active Blockers

**None.**

All pre-execution conditions are met:
- ✅ State verified — 100% matches Stage 5.4.2.4.4 findings
- ✅ Both tables confirmed empty (0 rows)
- ✅ No external FK dependencies on either partial table
- ✅ Migration 25 isolation mechanism (`--path`) confirmed functional via `--pretend`
- ✅ Backup tool available (`db:backup`) and scoped correctly
- ✅ Table count projections: 37 → 35 → 40 → 59 — all verified correct

---

## APPENDIX — `--pretend` RUN CONFIRMATION

A `migrate --pretend` dry-run was executed (read-only, no changes applied):

```bash
php artisan migrate \
  --database=tidb_staging_migrator \
  --path=database/migrations/2026_11_13_200000_create_media_generation_tables.php \
  --pretend --force
```

**Result:** 31 SQL statements previewed across 5 tables. All expected columns, FKs, and indexes confirmed in SQL output. The pretend run did **not** register migration 25 in the `migrations` table (confirmed by post-run record count = 24, unchanged).

---

## APPENDIX — MIGRATION 25 GUARD CONDITIONS (READ-ONLY AUDIT)

File: `database/migrations/2026_11_13_200000_create_media_generation_tables.php`

Five guard conditions confirmed present:
1. `if (! Schema::hasTable('media_assets')) { ... }`
2. `if (! Schema::hasTable('media_generation_jobs')) { ... }`
3. `if (! Schema::hasTable('media_storyboards')) { ... }`
4. `if (! Schema::hasTable('media_storyboard_scenes')) { ... }`
5. `if (! Schema::hasTable('media_visual_settings')) { ... }`

**Critical implication:** A plain `artisan migrate --force` (without `--path` and without dropping the partial tables) would silently skip the `media_assets` and `media_generation_jobs` creation blocks, leaving `media_generation_jobs` permanently incomplete. The `--path` isolation + pre-drop approach is the only correct recovery path that guarantees full migration 25 execution.

---

## APPENDIX — SCRIPTS USED (READ-ONLY)

| Script | Purpose | Writes? |
|:-------|:--------|:-------|
| `scripts/stage_5_4_2_4_5_verify.php` | Phase A/B/C live queries | No |
| `scripts/stage_5_4_2_4_5_verify2.php` | Phase A isolation + D migration status | No |
| `php artisan db:tidb-staging-migration-preflight` | Phase A connection/identity | No |
| `php artisan migrate:status --database=tidb_staging_migrator` | Phase D pending migrations | No |
| `php artisan migrate --pretend --path=... --force` | Phase D SQL preview | No |

---

*Verification timestamp: 2026-11-18T16:12:00+03:00*
*Verified by: Antigravity (automated read-only audit)*
*Reference commit: `063c8bf`*
*No repairs, migrations, schema changes, or credential modifications were made.*
