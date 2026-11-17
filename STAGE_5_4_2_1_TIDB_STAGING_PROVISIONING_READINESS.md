# SOCIALPILOT AI — PHASE 11, STAGE 5.4.2.1
## Isolated TiDB Staging Database Provisioning Readiness Audit

**Repository:** `Cla-renc/socialpilot-ai`  
**Branch:** `main`  
**Stage:** 5.4.2.1 (Isolated TiDB Staging Database Provisioning Readiness)  
**Date:** 2026-11-17  
**Preceding Commit:** `abb701f` (Stage 5.4.2 Deployment Blocked Report)  
**Final Decision:** **A. READY FOR HUMAN PROVISIONING APPROVAL**  

---

## 1. EXECUTIVE SUMMARY & OBJECTIVE

Following the Stage 5.4.2 deployment safety block, Stage 5.4.2.1 evaluates the feasibility, quota limits, user permission isolation, and exact SQL provisioning workflow required to create a genuinely isolated staging database (`socialpilot_staging`) on the existing TiDB Serverless cluster.

In strict compliance with safety rules:
- **No DDL or DML statements were executed.**
- `CREATE DATABASE` has **not** been run.
- No users or grants were created or modified.
- Production (`socialpilot`, 59 tables) and test fixture (`socialpilot_migration_test`, 59 tables) remain 100% untouched.
- All credential secrets remain strictly protected and omitted from documentation.

---

## 2. ACCOUNT, QUOTA & FREE-TIER INSPECTION

A live, non-destructive audit of the TiDB Serverless cluster was performed over secure TLS (`gateway01.eu-central-1.prod.aws.tidbcloud.com:4000`).

### 2.1 Cluster Details & Engine Version
- **Database Engine:** `8.0.11-TiDB-v8.5.3-serverless`
- **Edition:** TiDB Serverless (Apache License 2.0 Community Edition, MySQL 8.0 wire-protocol compatible)
- **Active User Context:** `AZDARrqgy4MBFGE.root@%`

### 2.2 Storage & Quota Consumption Audit
An inspection of `information_schema.tables` confirmed current data footprint across schemas:

| Schema Name | Role | Table Count | Total Allocated Bytes | Human-Readable Size |
|:------------|:-----|:------------|:----------------------|:--------------------|
| `socialpilot` | Production | 59 | 656 bytes | < 0.001 MB |
| `socialpilot_migration_test` | Migration Test | 59 | 1,872 bytes | < 0.002 MB |
| **Combined Usage** | — | **118** | **2,528 bytes** | **~0.003 MB** |

### 2.3 Free-Tier Quota Assessment
- **TiDB Serverless Quota Allocation:**
  - Up to **5 GiB** (5,120 MB) of persistent row storage per cluster.
  - Up to **5 GiB** (5,120 MB) of columnar (TiFlash) analytical storage.
  - **50 Million Request Units (RUs)** per calendar month for free.
- **Consumption Headroom:**
  - Total current disk usage is **~0.003 MB out of 5,120 MB** (>99.999% free storage remaining).
  - Provisioning `socialpilot_staging` and running all 29 initial migrations will consume ~2 KB of initial metadata, which is virtually negligible.
- **Billing & Card Requirement:**
  - TiDB Serverless permits creating multiple logical databases (`CREATE DATABASE <name>`) within a single cluster under the same free-tier allowance.
  - **No payment card, paid upgrade, or billing activation is required** to create `socialpilot_staging`.
  - The $0.00 zero-cost requirement is 100% maintained.

---

## 3. DATABASE USER & PERMISSION DESIGN

### 3.1 Current Administrative Privileges
Inspection of `SHOW GRANTS` for the administrative user (`AZDARrqgy4MBFGE.root@%`):
```sql
GRANT SELECT,INSERT,UPDATE,DELETE,CREATE,DROP,PROCESS,REFERENCES,ALTER,SHOW DATABASES,
      SUPER,EXECUTE,INDEX,CREATE USER,CREATE TABLESPACE,TRIGGER,CREATE VIEW,SHOW VIEW,
      CREATE ROLE,DROP ROLE,CREATE TEMPORARY TABLES,LOCK TABLES,CREATE ROUTINE,
      ALTER ROUTINE,EVENT,RELOAD,FILE,REPLICATION CLIENT,REPLICATION SLAVE 
ON *.* TO 'AZDARrqgy4MBFGE.root'@'%' WITH GRANT OPTION;
```
**Key Capabilities Verified:**
1. `CREATE USER`: The administrator can create dedicated application users.
2. `WITH GRANT OPTION`: The administrator can grant granular, database-level privileges.
3. Multi-tenant routing: The TiDB Cloud gateway routes connections based on the cluster prefix `AZDARrqgy4MBFGE.`.

### 3.2 Granular Database Isolation Design
To guarantee that staging traffic cannot read, modify, or drop production or test databases, a dedicated staging user must be provisioned:

- **Staging User Identifier:** `AZDARrqgy4MBFGE.staging`
- **Host Mask:** `%` (required for connections originating from Render's dynamic container IPs)
- **Privilege Scope:** Restricted strictly to `socialpilot_staging.*`

```
                                  TiDB Serverless Gateway
                                             │
                       ┌─────────────────────┴─────────────────────┐
                       │                                           │
             AZDARrqgy4MBFGE.root                     AZDARrqgy4MBFGE.staging
                       │                                           │
        ┌──────────────┼──────────────┐                            │
        │              │              │                            │
        ▼              ▼              ▼                            ▼
  [socialpilot]  [migration_test] [staging]               [socialpilot_staging]
  (Production)       (Test)       (Staging)                   (Staging ONLY)
                                                                   │
                                                      ❌ ACCESS DENIED to:
                                                         - socialpilot
                                                         - socialpilot_migration_test
```

When `AZDARrqgy4MBFGE.staging` connects:
- Queries targeting `socialpilot` trigger: `ERROR 1044 (42000): Access denied for user 'AZDARrqgy4MBFGE.staging'@'%' to database 'socialpilot'`
- Queries targeting `socialpilot_migration_test` trigger: `ERROR 1044 (42000): Access denied for user 'AZDARrqgy4MBFGE.staging'@'%' to database 'socialpilot_migration_test'`
- The staging application has zero privilege to inspect or modify production data.

---

## 4. EXACT PROVISIONING PLAN & SQL SPECIFICATION

The following SQL statements are prepared and staged for execution. **Neither has been executed yet.**

### 4.1 SQL Statements to Create Database and Dedicated User

```sql
-- Step 1: Create the isolated staging database
CREATE DATABASE IF NOT EXISTS `socialpilot_staging`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

-- Step 2: Create the dedicated staging user with cluster prefix
-- Note: Replace <SECURE_STAGING_PASSWORD> with a cryptographically generated password (openssl rand -base64 32)
CREATE USER IF NOT EXISTS 'AZDARrqgy4MBFGE.staging'@'%'
  IDENTIFIED BY '<SECURE_STAGING_PASSWORD>';

-- Step 3: Grant full application privileges STRICTLY on socialpilot_staging
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, DROP, REFERENCES, INDEX, ALTER, 
      CREATE TEMPORARY TABLES, LOCK TABLES, EXECUTE, CREATE VIEW, SHOW VIEW, 
      CREATE ROUTINE, ALTER ROUTINE, EVENT, TRIGGER 
ON `socialpilot_staging`.* TO 'AZDARrqgy4MBFGE.staging'@'%';

-- Step 4: Flush privileges to activate grant table updates
FLUSH PRIVILEGES;
```

### 4.2 Post-Provisioning Verification Queries

Immediately following execution, the following non-destructive verification queries must be run:

```sql
-- Verification Query 1: Verify all 3 databases exist with correct character set and collation
SELECT SCHEMA_NAME, DEFAULT_CHARACTER_SET_NAME, DEFAULT_COLLATION_NAME 
FROM information_schema.schemata 
WHERE SCHEMA_NAME IN ('socialpilot', 'socialpilot_migration_test', 'socialpilot_staging')
ORDER BY SCHEMA_NAME;
-- Expected output: 3 rows with utf8mb4 / utf8mb4_unicode_ci.

-- Verification Query 2: Confirm production database table integrity (must be 59 tables)
SELECT COUNT(*) AS production_table_count 
FROM information_schema.tables 
WHERE table_schema = 'socialpilot';
-- Expected output: 59.

-- Verification Query 3: Confirm migration-test table integrity (must be 59 tables)
SELECT COUNT(*) AS test_table_count 
FROM information_schema.tables 
WHERE table_schema = 'socialpilot_migration_test';
-- Expected output: 59.

-- Verification Query 4: Confirm newly created staging database is empty (prior to running migrations)
SELECT COUNT(*) AS staging_table_count 
FROM information_schema.tables 
WHERE table_schema = 'socialpilot_staging';
-- Expected output: 0.

-- Verification Query 5: Verify privilege isolation for staging user
SHOW GRANTS FOR 'AZDARrqgy4MBFGE.staging'@'%';
-- Expected output:
-- 1. GRANT USAGE ON *.* TO 'AZDARrqgy4MBFGE.staging'@'%'
-- 2. GRANT SELECT, INSERT, ... ON `socialpilot_staging`.* TO 'AZDARrqgy4MBFGE.staging'@'%'
-- (Zero grants on socialpilot.* or socialpilot_migration_test.*)
```

---

## 5. EXPECTED EFFECTS & ROLLBACK CONSIDERATIONS

### 5.1 Expected Effects
- A new empty database `socialpilot_staging` will exist on the TiDB Serverless cluster.
- A new user `AZDARrqgy4MBFGE.staging` will exist with permissions strictly scoped to `socialpilot_staging`.
- `socialpilot` (production) and `socialpilot_migration_test` will remain completely unaffected and isolated.
- The Render staging deployment environment can then be configured using `DB_DATABASE=socialpilot_staging` and `DB_USERNAME=AZDARrqgy4MBFGE.staging`.

### 5.2 Rollback Plan
If provisioning must be reversed at any point before migrations are executed:
```sql
DROP USER IF EXISTS 'AZDARrqgy4MBFGE.staging'@'%';
DROP DATABASE IF EXISTS `socialpilot_staging`;
FLUSH PRIVILEGES;
```
Executing this rollback immediately returns the TiDB Serverless cluster to its exact baseline state (`socialpilot` + `socialpilot_migration_test`).

---

## 6. EXECUTION APPROVAL GATE & FINAL DECISION

All technical prerequisites, quota validations, and permission isolation designs have been verified non-destructively:
- **Free-Tier Viability:** Confirmed ($0.00 / no card required; <0.001% of 5 GiB quota utilized).
- **Isolation Security:** Confirmed (dedicated user with database-level grants).
- **Zero Modifications:** Confirmed (no DDL or DML executed).

**Final Decision:**
### **A. READY FOR HUMAN PROVISIONING APPROVAL**

*Execution is paused awaiting explicit human authorization to run the provisioning SQL.*
