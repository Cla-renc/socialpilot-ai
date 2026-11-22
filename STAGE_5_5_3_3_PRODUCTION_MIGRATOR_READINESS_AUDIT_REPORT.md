# STAGE 5.5.3.3 — Production Migration User Provisioning Readiness Audit Report

**Date:** 2026-11-22  
**Mode:** READ-ONLY — No schema changes, no account creation, no data mutations  
**Executed as:** `AZDARrqgy4MBFGE.root` (admin, read-only queries only)  
**Cluster:** TiDB Cloud Serverless — `gateway01.eu-central-1.prod.aws.tidbcloud.com:4000` (TiDB v8.5.3-serverless)  
**Target database:** `socialpilot`

---

## 1. Executive Summary

**Verdict: Ready to provision.**

All readiness conditions are met:

- `AZDARrqgy4MBFGE.prod_app` exists and its grants exactly match the Stage 5.5.3.1 specification.
- `AZDARrqgy4MBFGE.prod_migrator` does **not** yet exist — no conflict.
- The `staging_migrator` account provides the validated reference privilege model.
- The pending migration (`2026_11_16_200000_add_integrity_constraints_to_content_generation_tables`) is **confirmed still pending** in production.
- No conflicting indexes exist on either target table.
- Both duplicate pre-flight checks pass with 0 duplicates.
- Production TLS is active.
- No blockers or security discrepancies found.

---

## 2. Cluster & Connection Verification

| Item | Verified Value |
|------|---------------|
| Admin user | `AZDARrqgy4MBFGE.root@%` |
| TiDB version | `8.0.11-TiDB-v8.5.3-serverless` |
| Cluster host | `gateway01.eu-central-1.prod.aws.tidbcloud.com` |
| Production database | `socialpilot` (59 tables confirmed) |
| Production TLS cipher | `TLS_AES_128_GCM_SHA256` |

---

## 3. Full Account Inventory

| Account | Host | Auth Plugin | Purpose |
|---------|------|-------------|---------|
| `AZDARrqgy4MBFGE.cloud_admin` | `%` | `mysql_native_password` | Provider admin |
| `AZDARrqgy4MBFGE.root` | `%` | `mysql_native_password` | Cluster superuser |
| `AZDARrqgy4MBFGE.staging_app` | `%` | `mysql_native_password` | Staging DML app account |
| `AZDARrqgy4MBFGE.staging_migrator` | `%` | `mysql_native_password` | Staging DDL migration account |
| `AZDARrqgy4MBFGE.prod_app` | `%` | `mysql_native_password` | Production DML app account ✅ |
| `AZDARrqgy4MBFGE.yaacla_43atDMWC` | `%` | `tidb_auth_token` | Personal/admin (token-based) |
| `role_admin` | `%` | `mysql_native_password` | Admin role |
| `AZDARrqgy4MBFGE.prod_migrator` | — | — | **NOT YET CREATED** (target of this stage) |

---

## 4. `prod_app` Verification

**Status: VERIFIED — matches Stage 5.5.3.1 specification exactly.**

Evidence source: Stage 5.5.3.1 Provisioning Report (`STAGE_5_5_3_1_PRODUCTION_DATABASE_USER_PROVISIONING_REPORT.md`) — present in project root.

Live `SHOW GRANTS` result (retrieved this audit):

```sql
GRANT USAGE ON *.* TO 'AZDARrqgy4MBFGE.prod_app'@'%'
GRANT SELECT,INSERT,UPDATE,DELETE ON `socialpilot`.* TO 'AZDARrqgy4MBFGE.prod_app'@'%'
```

| Check | Result |
|-------|--------|
| Account exists | YES |
| DML privileges on `socialpilot.*` | SELECT, INSERT, UPDATE, DELETE |
| DDL privileges | NONE |
| GRANT OPTION | NONE |
| Global privileges | NONE |
| Matches 5.5.3.1 specification | EXACT MATCH |

---

## 5. `prod_migrator` Existence Check

```
SHOW GRANTS FOR 'AZDARrqgy4MBFGE.prod_migrator'@'%'
→ Error 1141: There is no such grant defined for user 'AZDARrqgy4MBFGE.prod_migrator' on host '%'
```

**Result: Account does NOT exist. No conflict. Safe to create.**

---

## 6. Reference Model — `staging_migrator` Grants

The existing `staging_migrator` account was established during the staging readiness phase. Its grants define the reference privilege model for a migration-only account:

```sql
GRANT USAGE ON *.* TO 'AZDARrqgy4MBFGE.staging_migrator'@'%'
GRANT SELECT,INSERT,UPDATE,DELETE,CREATE,DROP,REFERENCES,ALTER,INDEX ON `socialpilot_staging`.* TO 'AZDARrqgy4MBFGE.staging_migrator'@'%'
```

This model will be replicated for `prod_migrator`, scoped to `socialpilot.*`.

---

## 7. Migration Privilege Justification

### Pending migration analysis

**File:** `2026_11_16_200000_add_integrity_constraints_to_content_generation_tables.php`

| Step | Operation | Laravel Schema Builder Call | SQL Generated |
|------|-----------|----------------------------|---------------|
| Pre-flight 1 | Read check | `DB::table()->select()->groupBy()->having()->get()` | `SELECT ... GROUP BY ... HAVING` |
| Pre-flight 2 | Read check | `DB::table()->select()->groupBy()->having()->get()` | `SELECT ... GROUP BY ... HAVING` |
| DDL 1 | Add unique index | `$table->unique('generation_job_id', 'bcd_generation_job_id_unique')` | `ALTER TABLE brand_content_drafts ADD UNIQUE INDEX ...` |
| DDL 2 | Add unique index | `$table->unique([...], 'cr_draft_id_revision_number_unique')` | `ALTER TABLE content_revisions ADD UNIQUE INDEX ...` |
| Rollback 1 | Drop index | `$table->dropUnique('cr_draft_id_revision_number_unique')` | `ALTER TABLE content_revisions DROP INDEX ...` |
| Rollback 2 | Drop index | `$table->dropUnique('bcd_generation_job_id_unique')` | `ALTER TABLE brand_content_drafts DROP INDEX ...` |

Laravel also internally uses `SELECT` to inspect existing schema via `SHOW INDEX` / `information_schema` before applying DDL, and writes to the `migrations` table using `INSERT`.

### Privilege-by-privilege justification

| Privilege | Required? | Justification |
|-----------|-----------|--------------|
| **SELECT** | YES | Pre-flight duplicate checks; schema inspection by Laravel's SchemaBuilder; `migrations` table reads |
| **INSERT** | YES | Writing the completed migration record into `migrations` table |
| **UPDATE** | YES | Laravel internally updates `migrations` batch numbers on rollback |
| **DELETE** | YES | `migrate:rollback` removes the migration record from `migrations` table |
| **ALTER** | YES | `ALTER TABLE ... ADD UNIQUE INDEX` and `ALTER TABLE ... DROP INDEX` |
| **INDEX** | YES | Required by TiDB for index DDL operations (implicit in `ALTER`, but explicitly listed for safety) |
| **CREATE** | NO — not needed for this migration | No new tables created. However, included in `staging_migrator` as a standard migrator privilege for future-proofing. **Included to match reference model and allow future migrations.** |
| **DROP** | NO — not needed for this migration | No tables dropped. Included in `staging_migrator` reference model for future migrations. **Included to match reference model.** |
| **REFERENCES** | NO — not strictly needed | Foreign key support. Included in `staging_migrator` reference. **Included to match reference model.** |
| **GRANT OPTION** | **NO — must NOT be granted** | Migration accounts never delegate privileges |
| **Global privileges** | **NO — must NOT be granted** | Scope is `socialpilot.*` only |
| **CREATE TEMPORARY TABLES** | NO | Laravel migrations do not use temporary tables |
| **LOCK TABLES** | NO | TiDB online DDL does not require explicit table locks |
| **EXECUTE** | NO | No stored procedures invoked |
| **EVENT / TRIGGER** | NO | No events or triggers created |

> [!NOTE]
> `CREATE`, `DROP`, and `REFERENCES` are included to match the `staging_migrator` reference model and to avoid blocking any future production migration that creates or drops a table. This is consistent with the established staging account design. If a stricter scope is preferred, these three can be omitted — the pending migration alone does not require them.

### Final privilege set

```sql
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, DROP, REFERENCES, ALTER, INDEX
ON `socialpilot`.*
TO 'AZDARrqgy4MBFGE.prod_migrator'@'%';
```

---

## 8. Pending Migration Verification

### Status

| Check | Result |
|-------|--------|
| Migration name | `2026_11_16_200000_add_integrity_constraints_to_content_generation_tables` |
| Present in `socialpilot.migrations` table | **NO — CONFIRMED PENDING** |
| Present in `socialpilot_staging.migrations` table | YES (batch 6) |

### Index conflict check

| Table | Expected index (would be added) | Conflict found? |
|-------|--------------------------------|----------------|
| `brand_content_drafts` | `bcd_generation_job_id_unique` | **NONE** |
| `content_revisions` | `cr_draft_id_revision_number_unique` | **NONE** |

Current indexes on `brand_content_drafts`:
`PRIMARY`, `brand_content_drafts_user_id_foreign`, `brand_content_drafts_brand_id_foreign`, `brand_content_drafts_brand_id_status_index`, `brand_content_drafts_is_ai_generated_index`, `brand_content_drafts_generation_job_id_foreign`

Current indexes on `content_revisions`:
`PRIMARY`, `content_revisions_draft_id_foreign`, `content_revisions_user_id_foreign`, `content_revisions_draft_id_revision_number_index`

### Duplicate pre-flight re-check (live production data, read-only)

| Check | Table | Duplicates Found | Status |
|-------|-------|-----------------|--------|
| Duplicate `generation_job_id` | `brand_content_drafts` | 0 | PASS |
| Duplicate `(draft_id, revision_number)` | `content_revisions` | 0 | PASS |

The migration's internal pre-flight guards **will not abort** when the migration runs.

---

## 9. Security & Isolation Requirements

| Requirement | Plan |
|-------------|------|
| Account scope | `socialpilot.*` only |
| `socialpilot_staging` access | Must be denied — not in GRANT scope |
| `socialpilot_migration_test` access | Must be denied — not in GRANT scope |
| Global privileges | None |
| GRANT OPTION | Absent |
| SSL/TLS required | `REQUIRE SSL` enforced (consistent with `prod_app`) |
| Credential storage | Gitignored `.env.staging.local` only |
| Credential in report | Never printed |

---

## 10. Exact Provisioning Plan — NOT EXECUTED

The following SQL is documented for review only. **None of these statements have been executed.**

```sql
-- ════════════════════════════════════════════════════════════════════════════
-- STAGE 5.5.3.3 — Provisioning Plan (NOT EXECUTED — requires separate auth)
-- Target cluster:  gateway01.eu-central-1.prod.aws.tidbcloud.com:4000
-- Target database: socialpilot
-- Account:         AZDARrqgy4MBFGE.prod_migrator
-- ════════════════════════════════════════════════════════════════════════════

-- Step 1: Pre-execution verification (read-only, run first)
SELECT current_user();                   -- must return root or cloud_admin
SELECT schema_name FROM information_schema.schemata WHERE schema_name = 'socialpilot';
SHOW GRANTS FOR 'AZDARrqgy4MBFGE.prod_migrator'@'%';  -- must return ERROR 1141

-- Step 2: Create account with strong generated credential
-- (credential generated locally via: openssl rand -hex 32)
-- (credential stored in .env.staging.local under TIDB_PROD_MIGRATOR_PASSWORD)
CREATE USER 'AZDARrqgy4MBFGE.prod_migrator'@'%'
    IDENTIFIED BY '<generated-password>'
    REQUIRE SSL;

-- Step 3: Grant migration privileges on production database only
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, DROP, REFERENCES, ALTER, INDEX
    ON `socialpilot`.*
    TO 'AZDARrqgy4MBFGE.prod_migrator'@'%';

-- Step 4: Verify grants (admin view)
SHOW GRANTS FOR 'AZDARrqgy4MBFGE.prod_migrator'@'%';
-- Expected output:
--   GRANT USAGE ON *.* TO 'AZDARrqgy4MBFGE.prod_migrator'@'%'
--   GRANT SELECT,INSERT,UPDATE,DELETE,CREATE,DROP,REFERENCES,ALTER,INDEX
--       ON `socialpilot`.* TO 'AZDARrqgy4MBFGE.prod_migrator'@'%'
```

### Post-creation verification steps (also not yet executed)

```sql
-- Using new prod_migrator credentials:
-- 1. Confirm connection and identity
SELECT current_user(), database();

-- 2. Confirm TLS
SHOW STATUS LIKE 'Ssl_cipher';    -- must not be empty

-- 3. Confirm socialpilot access
SELECT COUNT(*) FROM `socialpilot`.`migrations`;   -- must succeed

-- 4. Confirm staging isolation
USE `socialpilot_staging`;         -- must return Access denied

-- 5. Confirm no global DDL (safe negative test)
CREATE DATABASE _migrator_test;   -- must return Access denied
```

### Credential generation method

```bash
# Local, offline, no network:
openssl rand -hex 32
# Produces: 64-char lowercase hex string
# Store result in .env.staging.local as TIDB_PROD_MIGRATOR_PASSWORD
```

### Credential storage

Same gitignored file as `prod_app`:

```
# .env.staging.local (gitignored — never committed)
TIDB_PROD_MIGRATOR_USERNAME=AZDARrqgy4MBFGE.prod_migrator
TIDB_PROD_MIGRATOR_PASSWORD=<generated>
```

### Rollback procedure

```sql
-- If provisioning must be undone:
DROP USER 'AZDARrqgy4MBFGE.prod_migrator'@'%';
-- Verify removal:
SHOW GRANTS FOR 'AZDARrqgy4MBFGE.prod_migrator'@'%';  -- must return ERROR 1141
```

---

## 11. Risk Assessment

| Risk | Severity | Mitigation |
|------|----------|------------|
| Account accidentally granted global privileges | HIGH | Explicit scope `socialpilot.*` only; GRANT statement reviewed before execution |
| Account given GRANT OPTION | HIGH | GRANT statement does not include `WITH GRANT OPTION` |
| Credential exposed in Git | HIGH | Stored only in gitignored `.env.staging.local` |
| Account used to access staging | MEDIUM | Grants scoped to `socialpilot.*` — `socialpilot_staging` access denied by design |
| Migration run before `prod_migrator` verified | MEDIUM | Verification steps required before migration execution |
| Migration blocked by duplicate data | LOW | Pre-flight checks confirmed 0 duplicates — will not trigger |
| Index conflict causing DDL failure | LOW | Index conflict check confirmed NONE on both target tables |

---

## 12. Audit Integrity Confirmation

The following were NOT performed during this audit:

- NO account created or modified
- NO `CREATE USER`, `ALTER USER`, `DROP USER`, `GRANT`, or `REVOKE` executed
- NO migrations executed
- NO schema altered
- NO data mutated
- NO environment variables modified
- NO Render production or staging services touched
- NO credentials generated, printed, or exposed
- Both `socialpilot_staging` and `socialpilot_migration_test` queried read-only only

---

## 13. Final Decision

**Decision: A — Migration account provisioning plan verified and ready for separate authorization.**

| Item | Status |
|------|--------|
| `prod_app` existence and grants | VERIFIED — exact match to 5.5.3.1 spec |
| `prod_migrator` pre-existence conflict | NONE — safe to create |
| Privilege justification | DOCUMENTED per migration analysis |
| Pending migration confirmed | YES — still pending in production |
| Index conflicts | NONE |
| Duplicate pre-flight data | 0 duplicates — migration will not abort |
| Reference model (`staging_migrator`) | CONFIRMED — matching pattern |
| Provisioning SQL | DOCUMENTED (not executed) |
| Credential handling plan | DOCUMENTED (no credentials generated yet) |
| Authorization status | **PENDING — do not create account without separate explicit authorization** |
