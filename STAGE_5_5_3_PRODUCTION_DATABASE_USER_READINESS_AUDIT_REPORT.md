# Stage 5.5.3 — Production Database User Provisioning Readiness Audit Report

**Project:** SocialPilot AI  
**Target Environment:** Production Preparation  
**Target Database:** `socialpilot`  
**Database Cluster:** TiDB Cloud Serverless (`gateway01.eu-central-1.prod.aws.tidbcloud.com:4000`)  
**Assessment Date:** November 2026  
**Execution Mode:** STRICT READ-ONLY AUDIT (Zero Mutations Executed)  

---

## 1. Executive Summary

In accordance with Stage 5.5.3 guidelines, a strict read-only audit of the TiDB Cloud Serverless production database cluster was performed to evaluate database accounts, current privilege assignments, TLS enforcement, and application runtime requirements.

### Key Audit Findings:
1. **No Production Application Account Exists:** The database cluster currently has accounts for cluster administration (`cloud_admin`, `root`, `yaacla_43atDMWC`) and staging (`staging_app`, `staging_migrator`). **There is no dedicated `prod_app` account.**
2. **Current Administrative Accounts Over-Privileged for Runtime:** The existing cluster user `AZDARrqgy4MBFGE.yaacla_43atDMWC` possesses `role_admin` (`ALL PRIVILEGES ... ON *.* WITH GRANT OPTION`). Using this account for the production Laravel runtime would present a severe security risk.
3. **Application Runtime Needs Are Strictly DML:** Inspection of Laravel 12 configurations, Eloquent models, and database queries confirmed that SocialPilot AI requires exclusively **`SELECT, INSERT, UPDATE, DELETE`** on `socialpilot.*`. The application does not execute `CREATE TEMPORARY TABLE`, `LOCK TABLES`, or raw DDL during runtime.
4. **Schema Migrations Must Remain Isolated:** Database migrations (`php artisan migrate`) must be executed solely by an explicitly authorized migration account or administrator during release maintenance windows, completely separate from the daily application web server account.
5. **TLS Encryption Active:** TiDB Cloud Serverless enforces mandatory TLS encryption (`TLS_AES_128_GCM_SHA256`).
6. **Zero Mutations Executed:** No users were created, modified, or dropped. No schemas were altered.

**Final Decision:**  
`B — New least-privileged production application account required; ready for separate authorization.`

---

## 2. Existing Cluster Account Audit

A live read-only inspection of the cluster user table (`mysql.user`) and individual grants revealed the following 6 accounts:

| User Account | Host | Assigned Roles / Grants | Assessment |
| :--- | :--- | :--- | :--- |
| `AZDARrqgy4MBFGE.cloud_admin` | `%` | TiDB Cloud Internal System Admin | Provider-managed internal operator account |
| `AZDARrqgy4MBFGE.root` | `%` | `ALL PRIVILEGES ON *.* WITH GRANT OPTION` | Cluster master administrative account |
| `AZDARrqgy4MBFGE.yaacla_43atDMWC`| `%` | `role_admin` (`ALL PRIVILEGES ON *.* WITH GRANT OPTION`) | Initial cluster setup account. **Over-privileged for production runtime.** |
| `AZDARrqgy4MBFGE.staging_app` | `%` | `SELECT, INSERT, UPDATE, DELETE` on `socialpilot_staging.*` | Correctly restricted staging application account |
| `AZDARrqgy4MBFGE.staging_migrator`| `%` | DML + DDL on `socialpilot_staging.*` | Correctly scoped staging migration account |
| `role_admin` | `%` | Cluster Administrative Role | Administrative role definition |

### Finding on Production Database:
- The database `socialpilot` exists on the cluster (59 base tables, 28 migrations).
- Currently, **zero application accounts** have scoped access to `socialpilot`.
- Attempting to connect production using `staging_app` fails with `Access denied` (verified isolation).
- Deploying production with `root` or `yaacla_43atDMWC` would violate the principle of least privilege.

---

## 3. Least-Privilege & Application Runtime Assessment

### 3.1 Codebase Query & Privilege Inspection
A code audit across `app/`, `config/`, and `database/` was conducted to identify all database interactions:
- **Authentication & Sessions:** Database session driver (`sessions` table) requires `SELECT, INSERT, UPDATE, DELETE`.
- **Cache Store:** Database cache driver (`cache`, `cache_locks` tables) requires `SELECT, INSERT, UPDATE, DELETE`.
- **Queue Processing:** Database queue worker (`jobs`, `failed_jobs`, `job_batches` tables) requires `SELECT, INSERT, UPDATE, DELETE`.
- **Core Domain Logic:** Brands, drafts, social accounts, media assets, trends, analytics, and subscriptions utilize Eloquent ORM and Query Builder with standard DML operations.
- **Special Operations Check:**
  - `LOCK TABLES`: **Not used**.
  - `CREATE TEMPORARY TABLE`: **Not used**.
  - Stored Procedures / `EXECUTE`: **Not used**.
  - Raw DDL (`CREATE`, `ALTER`, `DROP`): **Not used during web request runtime**.

### 3.2 Required Production Privileges
To guarantee strict least privilege and protect production data:
- **Granted to `AZDARrqgy4MBFGE.prod_app`:**
  - `SELECT`
  - `INSERT`
  - `UPDATE`
  - `DELETE`
  - **Scope:** `socialpilot.*` strictly.
- **Explicitly Excluded:**
  - `CREATE`, `ALTER`, `DROP`, `REFERENCES`, `INDEX` (prevents runtime schema alterations).
  - `SUPER`, `PROCESS`, `SHUTDOWN`, `CONFIG` (prevents server administration).
  - `WITH GRANT OPTION` (prevents privilege escalation).
  - Access to `socialpilot_staging`, `socialpilot_migration_test`, `mysql`, `information_schema`.

---

## 4. Production Application Account Provisioning Plan

When authorized, the new account will be provisioned using the following secure procedure:

### 4.1 Proposed Account Details
- **Username:** `AZDARrqgy4MBFGE.prod_app`
- **Host:** `%` (All hosts, constrained by TiDB Cloud IP allowlists and mandatory TLS)
- **Database Scope:** `socialpilot.*`
- **Password Generation:** 32-character high-entropy cryptographic random string generated via `bin2hex(random_bytes(16))`.

### 4.2 SQL Statements for Provisioning (Stage 5.5.3.1 — Not Yet Executed)
```sql
-- 1. Create the least-privileged production application user
CREATE USER 'AZDARrqgy4MBFGE.prod_app'@'%' IDENTIFIED BY '<strong-generated-password>';

-- 2. Grant only necessary DML permissions on production database
GRANT SELECT, INSERT, UPDATE, DELETE ON `socialpilot`.* TO 'AZDARrqgy4MBFGE.prod_app'@'%';

-- 3. Enforce TLS connectivity
ALTER USER 'AZDARrqgy4MBFGE.prod_app'@'%' REQUIRE SSL;

-- 4. Flush privileges
FLUSH PRIVILEGES;
```

### 4.3 Production Migration Account (Optional / Separate)
If migrations are executed outside administrative credentials:
- **Username:** `AZDARrqgy4MBFGE.prod_migrator`
- **Scope:** `GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, DROP, ALTER, INDEX, REFERENCES ON socialpilot.*`
- Migrator credentials are kept strictly in deployment automation or local CLI and never shared with the web server.

### 4.4 Render Production Deployment Configuration
In the future Render Production web service:
```env
DB_CONNECTION=mysql
DB_HOST=gateway01.eu-central-1.prod.aws.tidbcloud.com
DB_PORT=4000
DB_DATABASE=socialpilot
DB_USERNAME=AZDARrqgy4MBFGE.prod_app
DB_PASSWORD=<secure-generated-password>
MYSQL_ATTR_SSL_CA=true
```

### 4.5 Testing & Rollback Plan
1. **Verification Test:** Connect via `AZDARrqgy4MBFGE.prod_app` and verify:
   - `SELECT 1` succeeds.
   - `SELECT count(*) FROM socialpilot.users` succeeds.
   - `CREATE TABLE socialpilot.test_fail (id INT)` fails with `Access denied` (verifying least privilege).
   - `SELECT count(*) FROM socialpilot_staging.users` fails with `Access denied` (verifying staging isolation).
2. **Rollback Procedure:** If connection fails, the user can be dropped with:
   `DROP USER IF EXISTS 'AZDARrqgy4MBFGE.prod_app'@'%';`

---

## 5. Security & Isolation Confirmations

- **Zero Mutations Executed:** No SQL writes, user creations, or permission grants were performed during this audit.
- **Environment Isolation:** Staging database (`socialpilot_staging`) and production database (`socialpilot`) remain completely untouched.
- **Credential Protection:** Zero passwords, access tokens, or private keys were printed or recorded in this document.

---

## 6. Final Decision

```
================================================================================
FINAL DECISION:
B — New least-privileged production application account required; ready for separate authorization.
================================================================================
```

### Next Steps:
In accordance with instructions, **execution is stopped**.

To proceed, please review this readiness report and grant explicit authorization for:
**Stage 5.5.3.1 — Authorized Provisioning of `AZDARrqgy4MBFGE.prod_app` Account on TiDB Cloud**.
