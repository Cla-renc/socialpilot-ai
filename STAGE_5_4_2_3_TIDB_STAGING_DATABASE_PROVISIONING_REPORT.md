# SOCIALPILOT AI — PHASE 11, STAGE 5.4.2.3
## Controlled TiDB Staging Database & Account Provisioning Report

**Repository:** `Cla-renc/socialpilot-ai`  
**Branch:** `main`  
**Stage:** 5.4.2.3 (Controlled TiDB Staging Database & Account Provisioning)  
**Date:** 2026-11-18  
**Preceding Commit:** `5505e8a` (Stage 5.4.2.2 Least-Privilege Security Review)  
**Final Decision:** **A. STAGING DATABASE AND ACCOUNTS SUCCESSFULLY PROVISIONED**  

---

## 1. EXECUTIVE SUMMARY & OBJECTIVE

Under explicit human authorization granted for Stage 5.4.2.3, the isolated staging database and two least-privilege accounts were successfully created and verified on the live TiDB Serverless cluster (`gateway01.eu-central-1.prod.aws.tidbcloud.com:4000`).

In strict compliance with Phase 11 safety rules:
- **Zero migrations were executed.** The staging database is empty and prepared for subsequent controlled migration operations.
- **Zero changes were made to production (`socialpilot`) or migration testing (`socialpilot_migration_test`).** Table counts remain exactly 59 tables each.
- **Zero secrets, passwords, or hashes are exposed in this report or in GitHub.** Generated credentials were saved strictly to local persistent storage outside the Git repository.
- **Zero cost incurred.** Operation executed entirely within the existing TiDB Serverless free tier.

---

## 2. PRE-EXECUTION SAFETY VERIFICATION

Before executing any DDL or user management statements, non-destructive safety checks verified cluster health:

| Safety Check | Expected | Actual Measured | Status |
|:-------------|:---------|:----------------|:-------|
| TLS Transport Encryption | Active | `TLS_AES_128_GCM_SHA256` | ✅ **PASS** |
| TiDB Server Version | `8.0.11-TiDB-v8.5.3-serverless` | `8.0.11-TiDB-v8.5.3-serverless` | ✅ **PASS** |
| Production Database (`socialpilot`) | Present | Present (59 tables) | ✅ **PASS** |
| Test Database (`socialpilot_migration_test`) | Present | Present (59 tables) | ✅ **PASS** |
| Staging Database (`socialpilot_staging`) | Not Present | Not Present | ✅ **PASS (Clean)** |
| Staging User Accounts | Not Present | 0 matching accounts found | ✅ **PASS (Clean)** |

---

## 3. PROVISIONING EXECUTION DETAILS

### 3.1 Database Creation
The staging database was created using the exact approved character set and collation:
```sql
CREATE DATABASE IF NOT EXISTS `socialpilot_staging`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
```

**Verification (`information_schema.schemata`):**
- **Schema Name:** `socialpilot_staging`
- **Default Character Set:** `utf8mb4`
- **Default Collation:** `utf8mb4_unicode_ci`
- **Initial Table Count:** `0`

### 3.2 Account Creation & Least-Privilege Grant Assignment

Two dedicated accounts were provisioned using local, cryptographically strong random credentials:

#### Account 1: Staging Migrator
- **Username:** `AZDARrqgy4MBFGE.staging_migrator`
- **Host Mask:** `%`
- **Purpose:** Used strictly for running controlled Laravel migrations (`php artisan migrate`).
- **Executed Grants:**
  ```sql
  GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, DROP, INDEX, REFERENCES
  ON `socialpilot_staging`.* TO 'AZDARrqgy4MBFGE.staging_migrator'@'%';
  ```

#### Account 2: Staging Application
- **Username:** `AZDARrqgy4MBFGE.staging_app`
- **Host Mask:** `%`
- **Purpose:** Used strictly by the running Laravel application (HTTP requests, sessions, queues, cache).
- **Executed Grants:**
  ```sql
  GRANT SELECT, INSERT, UPDATE, DELETE
  ON `socialpilot_staging`.* TO 'AZDARrqgy4MBFGE.staging_app'@'%';
  ```

`FLUSH PRIVILEGES;` was executed immediately following grant application.

---

## 4. POST-PROVISIONING SECURITY & ISOLATION VERIFICATION

Comprehensive functional and boundary testing was executed across administrative and provisioned connections:

### 4.1 Grant Inspection (`SHOW GRANTS`)

#### Staging Migrator Grants:
```sql
GRANT USAGE ON *.* TO 'AZDARrqgy4MBFGE.staging_migrator'@'%'
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, DROP, REFERENCES, ALTER, INDEX 
ON `socialpilot_staging`.* TO 'AZDARrqgy4MBFGE.staging_migrator'@'%'
```
*Confirmed: No global privileges, no `GRANT OPTION`, no access to `socialpilot` or `socialpilot_migration_test`.*

#### Staging Application Grants:
```sql
GRANT USAGE ON *.* TO 'AZDARrqgy4MBFGE.staging_app'@'%'
GRANT SELECT, INSERT, UPDATE, DELETE 
ON `socialpilot_staging`.* TO 'AZDARrqgy4MBFGE.staging_app'@'%'
```
*Confirmed: Least-privilege DML only. Complete absence of DDL privileges (`CREATE`, `DROP`, `ALTER`, `INDEX`).*

---

### 4.2 Cross-Database Isolation Tests

Live connection handshakes and query executions were performed for both accounts:

| Actor Connection | Action / Query Attempt | Result Code | Outcome |
|:-----------------|:-----------------------|:------------|:--------|
| `staging_migrator` | Connect to `socialpilot_staging` | Success | ✅ **AUTHORIZED** |
| `staging_migrator` | Query `socialpilot.users` | `SQLSTATE 42000` (Access denied) | 🛡️ **BLOCKED & ISOLATED** |
| `staging_migrator` | Query `socialpilot_migration_test.users` | `SQLSTATE 42000` (Access denied) | 🛡️ **BLOCKED & ISOLATED** |
| `staging_app` | Connect to `socialpilot_staging` | Success | ✅ **AUTHORIZED** |
| `staging_app` | Query `socialpilot.users` | `SQLSTATE 42000` (Access denied) | 🛡️ **BLOCKED & ISOLATED** |
| `staging_app` | Query `socialpilot_migration_test.users` | `SQLSTATE 42000` (Access denied) | 🛡️ **BLOCKED & ISOLATED** |
| `staging_app` | Execute DDL (`CREATE TABLE ...`) | `SQLSTATE 42000` (Access denied) | 🛡️ **BLOCKED (LEAST PRIVILEGE)** |

---

### 4.3 Database Schema Integrity Audit

An audit of `information_schema.tables` confirmed zero collateral disruption:

| Database Schema | Expected Tables | Actual Tables | Schema State |
|:----------------|:----------------|:--------------|:-------------|
| `socialpilot_staging` | 0 | **0** | Clean, ready for migrations |
| `socialpilot` (Production) | 59 | **59** | 100% untouched |
| `socialpilot_migration_test` (Test) | 59 | **59** | 100% untouched |

---

## 5. CREDENTIAL STORAGE & DEPLOYMENT PREPARATION

In accordance with strict security rules:
- Generated credentials were **not** output to terminal streams.
- Credentials were saved to a protected local configuration outside the repository:  
  `<scratch_dir>/tidb_staging_credentials.json`
- **Application Configuration:** When configuring Render's Staging Environment Variables, `DB_USERNAME` must be set to `AZDARrqgy4MBFGE.staging_app`.
- **Migration Configuration:** The migration account `AZDARrqgy4MBFGE.staging_migrator` is strictly reserved for controlled schema migration executions (`php artisan migrate --force`).

---

## 6. AUDIT SUMMARY & FINAL DECISION

- **Database `socialpilot_staging`:** Created and verified with `utf8mb4` / `utf8mb4_unicode_ci`.
- **Account `AZDARrqgy4MBFGE.staging_migrator`:** Created and verified with DDL + DML on `socialpilot_staging.*`.
- **Account `AZDARrqgy4MBFGE.staging_app`:** Created and verified with DML-only on `socialpilot_staging.*`.
- **Environment Isolation:** Cryptographically and syntactically enforced (access to `socialpilot` and `socialpilot_migration_test` denied).
- **Collateral Impact:** Zero. Production and test databases remain fully intact at 59 tables each.
- **Migration Status:** No migrations executed.
- **Deployment Status:** No public Render deployment initiated.

**Final Decision:**
### **A. STAGING DATABASE AND ACCOUNTS SUCCESSFULLY PROVISIONED**

*The isolated TiDB staging infrastructure is fully provisioned, verified, and ready for Stage 5.4.3 (Controlled Staging Database Migration & Verification).*
