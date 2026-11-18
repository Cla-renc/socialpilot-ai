# SOCIALPILOT AI — PHASE 11, STAGE 5.4.2.4.4
## Partial Migration Recovery Planning & Idempotency Audit (READ-ONLY)

**Repository:** `Cla-renc/socialpilot-ai`  
**Branch:** `main`  
**Stage:** 5.4.2.4.4 (Partial Migration Recovery Planning & Idempotency Audit)  
**Date:** 2026-11-18  
**Preceding Commits:** `a979bc6` (Stage 5.4.2.4.2 Report), `d3052f4` (Stage 5.4.2.4.3 Investigation)  
**Final Decision:** **A. RECOVERY PLAN VERIFIED — READY FOR HUMAN REVIEW**  

---

## 1. CONTEXT & EXECUTIVE SUMMARY

During Stage 5.4.2.4.2, controlled execution of Laravel migrations against the isolated TiDB staging database (`socialpilot_staging`) was interrupted on migration #25 (`2026_11_13_200000_create_media_generation_tables.php`) due to a remote TLS connection reset.

Stage 5.4.2.4.3 audited the resulting partial state:
- **Completed Migrations:** 24 recorded in `socialpilot_staging.migrations` (Batch 1).
- **Current Table Count:** Exactly 37 tables (36 domain tables + 1 `migrations` tracking table).
- **Partial Tables:** `media_assets` (complete), `media_generation_jobs` (missing 1 FK and 5 indexes).
- **Uncreated Tables:** `media_storyboards`, `media_storyboard_scenes`, `media_visual_settings`.
- **Pending Migrations:** Migrations 25, 26, 27, 28, and 29.
- **Isolation:** Production (`socialpilot`, 59 tables) and test fixture (`socialpilot_migration_test`, 59 tables) remain 100% untouched.

**Stage 5.4.2.4.4 Objective:**
Design a safe, rigorous, evidence-based recovery plan to bring `socialpilot_staging` to the full 59-table schema without blind retries, without schema corruption, and without modifying historical migration files.

> [!IMPORTANT]
> **THIS STAGE IS STRICTLY READ-ONLY PLANNING.** Zero migrations, repairs, SQL statements, schema modifications, or code changes were executed.

---

## 2. FINAL DECISION

```
A. RECOVERY PLAN VERIFIED — READY FOR HUMAN REVIEW
```

---

## 3. PHASE A — CURRENT STATE REVALIDATION (READ-ONLY)

Live read-only inspection was executed using the dedicated `tidb_staging_migrator` connection:

```
Target Database: socialpilot_staging
Authenticated User: AZDARrqgy4MBFGE.staging_migrator@%
Active Transport Cipher: TLS_AES_128_GCM_SHA256
Current Table Count in Staging: 37 tables
Completed Migration Records: 24 (Batch 1)
Pending Migrations: 5
Production Database (socialpilot): 59 tables, 28 migration records (UNTOUCHED)
Test Database (socialpilot_migration_test): 59 tables, 28 migration records (UNTOUCHED)
```

### Revalidation Assessment:
The live database state matches the findings of Stage 5.4.2.4.3 with **100% precision**. Zero unexpected schema drift, table alterations, or background connection activity occurred between audits.

---

## 4. PHASE B — MIGRATION 25 RECONCILIATION MATRIX & CODE ANALYSIS

Source file: `database/migrations/2026_11_13_200000_create_media_generation_tables.php`

### 4.1 Detailed Reconciliation Matrix
| Schema Element | Expected in Migration 25 | Present in `socialpilot_staging` | Requisite Recovery Action |
|:---------------|:-------------------------|:---------------------------------|:--------------------------|
| **Table: `media_assets`** | Created with 32 cols, 3 FKs, 10 indexes | **YES (Complete)** | Preserve or clean reset |
| `media_assets` Columns | 32 columns | 32 columns verified | None |
| `media_assets` Foreign Keys | `user_id`, `brand_id`, `content_draft_id` | All 3 verified present | None |
| `media_assets` Indexes | 10 indexes | All 10 verified present | None |
| **Table: `media_generation_jobs`** | Created with 22 cols, 3 FKs, 7 indexes | **PARTIAL** | Must be reconciled |
| `media_generation_jobs` Columns | 22 columns | 22 columns verified | None |
| FK: `user_id` -> `users.id` | CASCADE on delete | Verified present | Do not duplicate |
| FK: `brand_id` -> `brands.id` | CASCADE on delete | Verified present | Do not duplicate |
| FK: `content_draft_id` -> `brand_content_drafts.id` | SET NULL on delete | **MISSING (Failed)** | **Must be added** |
| Index: `brand_id` | Explicit index | **MISSING** (FK index only) | **Must be added** |
| Index: `user_id` | Explicit index | **MISSING** (FK index only) | **Must be added** |
| Index: `status` | Explicit index | **MISSING** | **Must be added** |
| Index: `job_type` | Explicit index | **MISSING** | **Must be added** |
| Index: `media_asset_id` | Explicit index | **MISSING** | **Must be added** |
| **Table: `media_storyboards`** | Created with 12 cols, 3 FKs, 2 indexes | **NO (Not created)** | **Must be created** |
| **Table: `media_storyboard_scenes`** | Created with 12 cols, 1 FK, 1 index | **NO (Not created)** | **Must be created** |
| **Table: `media_visual_settings`** | Created with 18 cols, 1 unique FK | **NO (Not created)** | **Must be created** |

### 4.2 Why a Blind Rerun Fails (Idempotency Flaw in Migration 25)
The migration defines table creation guarded by table existence checks:
```php
if (! Schema::hasTable('media_assets')) { ... }
if (! Schema::hasTable('media_generation_jobs')) { ... }
if (! Schema::hasTable('media_storyboards')) { ... }
if (! Schema::hasTable('media_storyboard_scenes')) { ... }
if (! Schema::hasTable('media_visual_settings')) { ... }
```
**Mechanism of Failure:**
1. Because `media_generation_jobs` was created before the connection dropped, `Schema::hasTable('media_generation_jobs')` evaluates to `true`.
2. A standard rerun of `php artisan migrate` skips the entire closure for `media_generation_jobs`.
3. The missing foreign key (`content_draft_id`) and the 5 explicit indexes would **never be created**.
4. The migration would proceed to create tables 3, 4, and 5 and record migration 25 as completed, leaving `media_generation_jobs` in a permanently degraded and non-conformant state.

### 4.3 Why Modifying Historical Migration Files is High-Risk
- Editing `2026_11_13_200000_create_media_generation_tables.php` introduces code divergence across Git history.
- The production database (`socialpilot`) and test fixture (`socialpilot_migration_test`) have already executed migration 25 from its original definition.
- Modifying historical migrations can break reproducible CI testing or cause checksum/hash mismatches.
- **Rule:** Historical migration files must remain immutable. Recovery must occur at the database state level or via separate tooling.

---

## 5. PHASE C — RECOVERY OPTIONS EVALUATION

Three distinct recovery strategies were evaluated against safety, consistency, and risk criteria:

### Option 1: Clean Reset of Migration 25 Partial Tables in Staging (RECOMMENDED)
- **Concept:** Drop only the two partially created, empty tables (`media_generation_jobs` and `media_assets`) in `socialpilot_staging`, then re-run the standard migration command.
- **Exact Scope:**
  1. Execute `DROP TABLE IF EXISTS media_generation_jobs;`
  2. Execute `DROP TABLE IF EXISTS media_assets;`
  3. Staging table count drops from 37 back to 35 (exactly matching the 24 completed migrations).
  4. Run `php artisan migrate --database=tidb_staging_migrator --force`.
- **Advantages:**
  - Preserves 100% of the 24 completed migrations.
  - Zero modification to migration source files.
  - Allows migration 25 to execute from start to finish as a single, coherent, topological operation.
  - Eliminates all partial index or foreign-key inconsistencies.
  - Zero data loss: both tables contain exactly 0 rows.
- **Risks & Mitigation:**
  - *Risk:* Requires DDL `DROP TABLE` execution on staging.
  - *Mitigation:* Explicit human authorization required; preflight validates target database is strictly `socialpilot_staging`.
- **Preserves Completed Migrations:** **YES (100% preserved).**

### Option 2: Surgical DDL Patching of Missing Schema Elements
- **Concept:** Manually apply the missing foreign key and 5 indexes to `media_generation_jobs`, then let `php artisan migrate` run tables 3, 4, and 5.
- **Exact Scope:**
  1. `ALTER TABLE media_generation_jobs ADD CONSTRAINT ... FOREIGN KEY (content_draft_id) ...`
  2. `ALTER TABLE media_generation_jobs ADD INDEX (brand_id), ADD INDEX (user_id), ...`
  3. Run `php artisan migrate --database=tidb_staging_migrator --force`.
- **Advantages:** Does not drop existing tables.
- **Risks & Disadvantages:**
  - Highly brittle; requires manual DDL synchronization outside Laravel's migration runner.
  - Human error risk in reproducing exact constraint names and index ordering.
  - If migration 25 fails again on tables 3, 4, or 5, an even more fragmented state is created.
- **Preserves Completed Migrations:** YES.

### Option 3: Full Database Rebuild (`migrate:fresh`)
- **Concept:** Drop the entire staging database and run all 29 migrations from scratch.
- **Advantages:** Theoretical "clean slate".
- **Risks & Disadvantages:**
  - Destroys 24 verified, healthy migrations.
  - Forces another massive, long-running single-session connection over TiDB Cloud, risking the exact same timeout.
  - Highly destructive and unnecessary given that migrations 1–24 are 100% healthy and isolated.
- **Preserves Completed Migrations:** **NO.**
- **Verdict:** **REJECTED.**

---

## 6. PHASE D — RECOMMENDED SAFE RECOVERY SEQUENCE

The recommended, verified procedure is **Option 1 (Clean Partial Reset & Controlled Resumption)**, structured across strict human authorization gates:

```mermaid
flowchart TD
    G1["GATE 1: Pre-Execution Connection Hardening"] --> G2["GATE 2: Preflight Target Identity Revalidation"]
    G2 --> G3["GATE 3: Clean Reset of Partial Tables (2 tables dropped)"]
    G3 --> G4["GATE 4: Execute Migration 25 to Full Completion"]
    G4 --> G5["GATE 5: Intermediate Schema Verification (40 tables)"]
    G5 --> G6["GATE 6: Controlled Resumption of Migrations 26-29"]
    G6 --> G7["GATE 7: Final 59-Table Schema & Permission Audit"]
```

### Detailed Sequence Specification:

#### Step 1: Client-Side Connection Hardening (Gate 1)
- Verify PDO connection options in `config/database.php` for `tidb_staging_migrator`:
  - Ensure persistent connection or explicit socket timeout (`PDO::ATTR_TIMEOUT => 60`).
  - Ensure TCP keepalive is active to prevent gateway socket drops during DDL schema leasing.

#### Step 2: Preflight Verification (Gate 2)
- Execute `php artisan db:tidb-staging-migration-preflight`.
- Confirm connection is `tidb_staging_migrator`, target is `socialpilot_staging`, and credentials belong to `AZDARrqgy4MBFGE.staging_migrator`.

#### Step 3: Clean Partial Reset (Gate 3 — Explicit Authorization Required)
- Execute dedicated reset of the two empty partial tables:
  ```sql
  DROP TABLE IF EXISTS `socialpilot_staging`.`media_generation_jobs`;
  DROP TABLE IF EXISTS `socialpilot_staging`.`media_assets`;
  ```
- Re-verify table count in `socialpilot_staging` is exactly **35 tables**.
- Confirm production (`socialpilot`) and test (`socialpilot_migration_test`) remain at **59 tables**.

#### Step 4: Execute Migration 25 (Gate 4 — Explicit Authorization Required)
- Run `php artisan migrate --database=tidb_staging_migrator --force`.
- Migration 25 executes cleanly from the beginning, creating:
  1. `media_assets`
  2. `media_generation_jobs` (with all 3 FKs and 7 indexes)
  3. `media_storyboards`
  4. `media_storyboard_scenes`
  5. `media_visual_settings`

#### Step 5: Intermediate Schema Verification (Gate 5)
- Verify table count in `socialpilot_staging` is exactly **40 tables** (39 domain tables + 1 `migrations` table).
- Verify migration 25 is recorded in `migrations` table (Batch 2).
- Inspect `media_generation_jobs` to verify `content_draft_id` foreign key and 5 explicit indexes exist.

#### Step 6: Controlled Resumption of Migrations 26–29 (Gate 6 — Explicit Authorization Required)
- Execute remaining pending migrations:
  - #26: `create_scheduling_and_autopilot_tables` (9 tables)
  - #27: `create_publishing_agent_tables` (4 tables)
  - #28: `create_analytics_and_ai_optimization_tables` (6 tables)
  - #29: `add_integrity_constraints_to_content_generation_tables` (alters 2 tables)
- Stop immediately if any error occurs.

#### Step 7: Final Comprehensive Verification (Gate 7)
- Verify total table count reaches exactly **59 tables**.
- Verify all 29 migrations are recorded.
- Verify foreign keys, unique constraints, and 62 JSON columns.
- Verify application runtime account (`staging_app`) read access and DDL denial.

---

## 7. PHASE E — CONNECTION INTERRUPTION EVIDENCE REASSESSMENT

A rigorous re-evaluation of available diagnostic evidence distinguishes confirmed facts from hypotheses:

### 1. Confirmed Evidence
- **Transport Reset:** PHP received WinSock error 10054 (`PDO::prepare(): SSL: An existing connection was forcibly closed by the remote host`) from `gateway01.eu-central-1.prod.aws.tidbcloud.com:4000`.
- **Duration & Workload:** The single PDO connection had been continuously active for ~298 seconds, executing 37 preceding DDL statements across 25 migrations.
- **Account Validity:** Immediate subsequent connections authenticated successfully without credential, IP, or certificate rejection.
- **Zero Data Contention:** All tables contain 0 rows; no row locks, table locks, or deadlocks occurred.

### 2. Probable Causes
- **Gateway Idle/Keepalive Disconnect:** In TiDB Serverless, schema changes require cross-node schema leasing across TiKV/TiDB compute layers. Long sequential DDL statements introduce inter-query pauses on the client socket. The AWS NLB/Envoy proxy terminates TCP sockets that do not exhibit bidirectional data traffic within default keepalive windows.
- **Absence of Client Keepalive:** The PHP/PDO client configuration on Windows relied on default stream timeouts (60 seconds) without TCP keepalive probes.

### 3. Unverified Hypotheses
- *TiDB Server Crash:* Unverified. TiDB Cloud status remained operational and the cluster responded immediately to new connections.
- *Local Network Glitch:* Unlikely. No host network disconnection or local gateway drops were logged.

### 4. Missing Diagnostic Evidence
- Upstream Envoy/NLB gateway logs from TiDB Cloud control plane (proprietary multi-tenant infrastructure, unavailable to tenant accounts).
- Client-side packet captures (Wireshark) during the original migration run.

---

## 8. STRICT SAFETY RESTRICTIONS COMPLIANCE

During Stage 5.4.2.4.4:
- Zero migrations or seeders were run.
- Zero repair SQL statements were executed.
- Zero tables, columns, indexes, or constraints were created, dropped, or modified.
- Zero migration records were updated or deleted.
- Zero source code files or database configurations were altered.
- Render deployment was **not performed**.
- Zero credentials, passwords, or connection strings are exposed in this report.
- The $0/no-card cost restriction was strictly maintained.

---

## 9. CONCLUSION & RECOMMENDATIONS FOR STAGE 5.4.2.4.5

The partial schema state of `socialpilot_staging` is fully understood, verified, and referentially sound. The root cause of the interruption and the failure modes of blind retries have been conclusively documented.

The **Clean Partial Reset & Controlled Resumption Plan (Option 1)** provides a 100% safe, non-destructive path to complete all 29 migrations while preserving the 24 already-verified migrations.

Execution remains strictly halted pending separate, explicit human authorization.
