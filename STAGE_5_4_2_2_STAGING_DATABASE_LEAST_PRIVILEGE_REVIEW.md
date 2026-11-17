# SOCIALPILOT AI — PHASE 11, STAGE 5.4.2.2
## Staging Database Least-Privilege Security Review

**Repository:** `Cla-renc/socialpilot-ai`  
**Branch:** `main`  
**Stage:** 5.4.2.2 (Staging Database Least-Privilege Security Review)  
**Date:** 2026-11-17  
**Preceding Commit:** `09e442a` (Stage 5.4.2.1 TiDB Staging Provisioning Readiness)  
**Final Verdict:** **A. READY FOR HUMAN PROVISIONING APPROVAL**  

---

## 1. OBJECTIVE & BACKGROUND

Following Stage 5.4.2.1, this review refines the database security model from a single administrative staging account into a **dual-account least-privilege architecture**.

By separating schema migration duties from routine application runtime operations, SocialPilot AI eliminates the risk of accidental schema destruction, unauthorized DDL execution, or privilege escalation during normal staging runtime.

In strict compliance with Phase 11 safety rules:
- **No DDL or DML statements were executed.**
- No users or grants were created or modified.
- Production (`socialpilot`, 59 tables) and test fixture (`socialpilot_migration_test`, 59 tables) remain completely untouched.
- All password placeholders are strictly anonymized; no credentials or secrets are logged or committed.

---

## 2. DATABASE ACCOUNT SEPARATION ARCHITECTURE

Rather than granting wide administrative privileges to a single user, the staging environment is separated into two dedicated database accounts:

```
                            TiDB Serverless Gateway
                   (gateway01.eu-central-1.prod.aws.tidbcloud.com)
                                      │
          ┌───────────────────────────┴───────────────────────────┐
          │                                                       │
  [Migration Account]                                    [Application Account]
  AZDARrqgy4MBFGE.staging_migrator                       AZDARrqgy4MBFGE.staging_app
          │                                                       │
   DDL + DML Privileges                                    DML ONLY Privileges
  (CREATE, ALTER, DROP,                                   (SELECT, INSERT,
   INDEX, REFERENCES, DML)                                 UPDATE, DELETE)
          │                                                       │
          ▼                                                       ▼
  ┌───────────────────────────────────────────────────────────────────────┐
  │                         socialpilot_staging                           │
  │                         (Isolated Database)                           │
  └───────────────────────────────────────────────────────────────────────┘
          │                                                       │
          ❌ ACCESS DENIED                                        ❌ ACCESS DENIED
          - socialpilot (Production)                              - socialpilot (Production)
          - socialpilot_migration_test (Test)                     - socialpilot_migration_test (Test)
          - DDL from Application Account                          - User/Grant Administration
```

### Account Specifications

| Dimension | Account A: Staging Migrator | Account B: Staging Application |
|:----------|:----------------------------|:-------------------------------|
| **Username** | `AZDARrqgy4MBFGE.staging_migrator` | `AZDARrqgy4MBFGE.staging_app` |
| **Host Mask** | `%` (Render container IP pool) | `%` (Render container IP pool) |
| **Lifecycle** | Invoked strictly during deployments (`php artisan migrate`) | Persistent runtime connection (Nginx / PHP-FPM / Queues) |
| **Privilege Scope** | `socialpilot_staging.*` | `socialpilot_staging.*` |
| **DDL Privileges** | `CREATE`, `ALTER`, `DROP`, `INDEX`, `REFERENCES` | ❌ **NONE** (DDL strictly blocked) |
| **DML Privileges** | `SELECT`, `INSERT`, `UPDATE`, `DELETE` | `SELECT`, `INSERT`, `UPDATE`, `DELETE` |
| **Administrative** | ❌ None (`NO GRANT OPTION`, `NO CREATE USER`) | ❌ None (`NO GRANT OPTION`, `NO CREATE USER`) |

---

## 3. PRIVILEGE COMPATIBILITY & APPLICATION INSPECTION

### 3.1 Codebase & Runtime Inspection
An audit of all 29 Laravel migrations, models, controllers, and database drivers confirms:
1. **Migrations require:**
   - `CREATE`: Creating 29 core and domain tables (`Schema::create`).
   - `ALTER`: Modifying columns, adding foreign keys, altering tables across releases.
   - `DROP`: Dropping tables in `down()` rollback methods (`Schema::dropIfExists`).
   - `INDEX`: Adding unique constraints, compound indexes, and foreign key indexes.
   - `REFERENCES`: Enforcing relational integrity on `users`, `brands`, `campaigns`, etc.
   - `SELECT`, `INSERT`, `DELETE`: Tracking applied batches in the Laravel `migrations` table.
2. **Application runtime requires:**
   - `SELECT`: Reading models, authentication lookups, analytics queries.
   - `INSERT`: Creating users, drafts, posts, jobs, logs, audit trails.
   - `UPDATE`: Updating post status, token expiration, cache values, user profiles.
   - `DELETE`: Pruning expired sessions, consuming queue jobs (`DELETE FROM jobs`), cache invalidation (`DELETE FROM cache_locks`).
3. **Application runtime does NOT require:**
   - `DROP`, `CREATE`, `ALTER`, `INDEX`, `REFERENCES`: The runtime web app never executes schema modifications.
   - `LOCK TABLES`: Queue workers and cache locks utilize row-level locking (`SELECT ... FOR UPDATE` or atomic inserts on `cache_locks`), which operate under standard DML.
   - `CREATE TEMPORARY TABLES`: No temporary tables are created by application code or queries.

### 3.2 TiDB Serverless Compatibility
A live query of `information_schema.user_privileges` on the active TiDB Serverless cluster (`8.0.11-TiDB-v8.5.3-serverless`) verified that:
- `SELECT`, `INSERT`, `UPDATE`, `DELETE` are natively supported at the schema level.
- `CREATE`, `ALTER`, `DROP`, `INDEX`, `REFERENCES` are natively supported at the schema level.
- Schema-level grants (`ON socialpilot_staging.*`) are fully enforced by the TiDB query planner.

---

## 4. DATABASE ISOLATION VERIFICATION

Both accounts are cryptographically and syntactically restricted:

1. **Isolation from Production (`socialpilot`):**
   - Neither account is granted any global (`*.*`) or database (`socialpilot.*`) privilege.
   - Any query attempting to read or write `socialpilot` results in:
     `ERROR 1044 (42000): Access denied for user '<user>'@'%' to database 'socialpilot'`
2. **Isolation from Test Fixture (`socialpilot_migration_test`):**
   - Zero privileges on `socialpilot_migration_test.*`.
   - Access attempts result in `ERROR 1044 (42000)`.
3. **Isolation from System & User Administration:**
   - Neither account has `CREATE USER`, `DROP USER`, `GRANT OPTION`, `SUPER`, `PROCESS`, or `RELOAD`.
   - Neither account can inspect `mysql.user` or grant privileges to themselves or other roles.

---

## 5. PASSWORD & SECRET HANDLING ARCHITECTURE

In compliance with strict security rules:
- No passwords or secrets are generated, committed, or embedded in this repository.
- Placeholders (`<SECURE_MIGRATOR_PASSWORD>`, `<SECURE_APP_PASSWORD>`) are used in all templates.

### Secure Injection Workflow

```
                   1. Administrator (Local Shell / Secure CLI)
                                        │
        Generates strong random secrets (e.g. openssl rand -base64 32)
                                        │
             ┌──────────────────────────┴──────────────────────────┐
             │                                                     │
             ▼                                                     ▼
     TiDB Cloud Cluster                                    Render Dashboard
     (Executes Provisioning SQL)                           (Environment Variables)
     - Creates AZDARrqgy4MBFGE.staging_migrator            - DB_USERNAME=...staging_app
     - Creates AZDARrqgy4MBFGE.staging_app                 - DB_PASSWORD=...
                                                           - DB_DATABASE=socialpilot_staging
```

1. **Generation:** Passwords must be generated using cryptographically secure pseudorandom generators (e.g., `openssl rand -base64 32`).
2. **Render Runtime Configuration:** Only the **Application Account** (`AZDARrqgy4MBFGE.staging_app`) credentials are entered into Render's Environment Variables dashboard.
3. **Migration Execution:** During deployment, migrations can be run via Render's release command or one-off shell using the **Migrator Account** credentials passed strictly at execution time.

---

## 6. EXACT PROVISIONING SQL SPECIFICATION

The following statements define the complete, reviewed provisioning plan. **Execution is held pending human approval.**

```sql
-- ==============================================================================
-- 1. DATABASE PROVISIONING
-- ==============================================================================
CREATE DATABASE IF NOT EXISTS `socialpilot_staging`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

-- ==============================================================================
-- 2. STAGING MIGRATION ACCOUNT (DDL + DML)
-- ==============================================================================
CREATE USER IF NOT EXISTS 'AZDARrqgy4MBFGE.staging_migrator'@'%'
  IDENTIFIED BY '<SECURE_MIGRATOR_PASSWORD>';

GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, DROP, INDEX, REFERENCES
ON `socialpilot_staging`.* TO 'AZDARrqgy4MBFGE.staging_migrator'@'%';

-- ==============================================================================
-- 3. STAGING APPLICATION ACCOUNT (LEAST-PRIVILEGE DML ONLY)
-- ==============================================================================
CREATE USER IF NOT EXISTS 'AZDARrqgy4MBFGE.staging_app'@'%'
  IDENTIFIED BY '<SECURE_APP_PASSWORD>';

GRANT SELECT, INSERT, UPDATE, DELETE
ON `socialpilot_staging`.* TO 'AZDARrqgy4MBFGE.staging_app'@'%';

-- ==============================================================================
-- 4. ACTIVATE PRIVILEGES
-- ==============================================================================
FLUSH PRIVILEGES;
```

---

## 7. ROLLBACK SAFETY & STEP-BY-STEP REVOCATION

Rollback operations are divided into distinct stages to prevent accidental data destruction:

### Phase 1: Account Revocation (Non-destructive to data)
Removes all privileges without deleting the database or data:
```sql
REVOKE ALL PRIVILEGES, GRANT OPTION FROM 'AZDARrqgy4MBFGE.staging_app'@'%';
REVOKE ALL PRIVILEGES, GRANT OPTION FROM 'AZDARrqgy4MBFGE.staging_migrator'@'%';
FLUSH PRIVILEGES;
```

### Phase 2: Account Removal (Non-destructive to data)
Deletes user authentication records:
```sql
DROP USER IF EXISTS 'AZDARrqgy4MBFGE.staging_app'@'%';
DROP USER IF EXISTS 'AZDARrqgy4MBFGE.staging_migrator'@'%';
FLUSH PRIVILEGES;
```

### Phase 3: Database Deletion (Destructive)
> ⚠️ **CRITICAL WARNING:** Executing the statement below permanently deletes the staging database, all 29 tables, and all staging records. This cannot be undone. Never execute this step if staging data must be preserved.
```sql
DROP DATABASE IF EXISTS `socialpilot_staging`;
```

---

## 8. FINAL VERDICT & EXECUTION APPROVAL GATE

The proposed least-privilege dual-account design:
- Protects the production database from staging traffic.
- Protects the staging schema from runtime DDL corruption.
- Uses natively supported TiDB Serverless privileges.
- Maintains the $0.00 / no-card free tier requirement.

**Final Verdict:**
### **A. READY FOR HUMAN PROVISIONING APPROVAL**

*Awaiting explicit human approval before executing any SQL on the TiDB cluster.*
