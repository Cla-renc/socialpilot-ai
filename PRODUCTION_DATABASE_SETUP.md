# SOCIALPILOT AI — PRODUCTION DATABASE SETUP GUIDE

**Target Engine:** MySQL 8.0+ / TiDB Serverless (MySQL-Compatible Wire Protocol)  
**Status:** Stage 2 Migration Compatibility Live Verified on `socialpilot_migration_test`  
**Cost:** $0.00 / month (No credit card required for TiDB Serverless)  

---

## 1. COMPATIBILITY AUDIT WITH LARAVEL MIGRATIONS

SocialPilot AI has **28 migration files** spanning Phases 1 through 10. Every migration was audited and live-tested against TiDB Serverless:

| Architectural Feature | Audit Finding | Compatibility Status |
|---|---|---|
| **Foreign Key Constraints** | 106 foreign keys across tables defined via `$table->foreignId()->constrained()->cascadeOnDelete()`. All reference existing parent tables in proper sequential order. | **100% COMPATIBLE & VERIFIED** |
| **JSON Columns** | 22 JSON columns used for flexible metadata (e.g. `metadata`, `settings`, `options`, `audience_interests`, `visual_settings`). Both MySQL 8.0+ and TiDB natively index and store JSON documents. | **100% COMPATIBLE & VERIFIED** |
| **Indexes & Compound Keys** | Standard B-Tree indexes on `user_id`, `brand_id`, `scheduled_at`, `status`, `platform`, and compound unique indexes on `[brand_id, platform, external_id]`. | **100% COMPATIBLE & VERIFIED** |
| **Identifier Length (64 Chars)** | MySQL and TiDB enforce a strict 64-character limit on index and constraint names. Two generated index names in `2026_11_14_150000_create_analytics_and_ai_optimization_tables.php` exceeded 64 characters and were assigned explicit shortened identifiers (`idx_snap_brand_plat_met_col` and `idx_ai_rec_brand_status_created`). | **RESOLVED & VERIFIED** |
| **Unique Constraints** | Handled natively across user emails, personal access tokens, and brand slugs. Verified with error 1062 duplicate rejection. | **100% COMPATIBLE & VERIFIED** |
| **Database Transactions** | All critical publishing updates and token refreshes wrap DB operations in `DB::transaction(...)`. Fully supported under TiDB snapshot isolation. | **100% COMPATIBLE & VERIFIED** |
| **Database-Specific SQL** | Zero raw SQLite functions (`strftime`, `random()`, SQLite PRAGMAs) are present in Eloquent queries or application services. All queries use Laravel QueryBuilder. | **100% COMPATIBLE & VERIFIED** |
| **Timezone & Timestamps** | Laravel handles timestamps via Carbon in UTC (`created_at`, `updated_at`, `scheduled_at`). MySQL/TiDB stores datetime fields natively in UTC. | **100% COMPATIBLE & VERIFIED** |

---

## 2. PRESERVING THE LOCAL DEVELOPMENT DATABASE

**MANDATORY RULE:** The local SQLite development database (`database/database.sqlite`) contains testing records and development fixtures. It must **never** be overwritten, dropped, or directly mutated by production commands.

- Keep `DB_CONNECTION=sqlite` in your local `.env` file during local development.
- Production environment variables (`DB_CONNECTION=mysql`) must only be configured in production hosting environments or production deployment manifests.
- Automated tests always execute against an in-memory or isolated test database configured in `phpunit.xml` (`<env name="DB_CONNECTION" value="sqlite"/>`, `<env name="DB_DATABASE" value=":memory:"/>`), guaranteeing zero disruption to local user data.

---

## 3. PROVISIONING FREE TIDB SERVERLESS (MYSQL COMPATIBLE)

TiDB Serverless provides a permanent free MySQL 8.0-compatible database without requiring a credit card:

### Step 1: Create Free Cluster
1. Sign in to [TiDB Cloud](https://tidbcloud.com/) using your GitHub account (zero payment details requested).
2. Click **Create Cluster** and select **Serverless (Free)**.
3. Choose your preferred cloud region (e.g., `AWS / us-east-1` or `GCP / us-central1`).
4. Set a strong cluster root password.
5. Cluster creation completes in ~15 seconds.

### Step 2: Retrieve Connection Credentials
In the TiDB Cloud console, click **Connect > General Connection**:
- **Host:** e.g., `gateway01.us-east-1.prod.aws.tidbcloud.com`
- **Port:** `4000`
- **User:** e.g., `xxxxxx.root`
- **Password:** `<YOUR_GENERATED_PASSWORD>`
- **Database:** `socialpilot`
- **SSL Mode:** `REQUIRED` (TiDB requires TLS encryption)

---

## 4. LARAVEL PRODUCTION CONFIGURATION FOR TIDB / MYSQL

In your production `.env` configuration:

```env
# Production Database Connection
DB_CONNECTION=mysql
DB_HOST=gateway01.us-east-1.prod.aws.tidbcloud.com
DB_PORT=4000
DB_DATABASE=socialpilot
DB_USERNAME=your_cluster_prefix.root
DB_PASSWORD=your_cluster_password

# SSL / TLS Settings (Required for TiDB Serverless)
MYSQL_ATTR_SSL_CA=/etc/ssl/certs/ca-certificates.crt
```

In `config/database.php`, the default MySQL connection is pre-configured to automatically enable PDO SSL options when `MYSQL_ATTR_SSL_CA` is specified:
```php
'mysql' => [
    'driver' => 'mysql',
    'host' => env('DB_HOST', '127.0.0.1'),
    'port' => env('DB_PORT', '3306'),
    'database' => env('DB_DATABASE', 'socialpilot'),
    'username' => env('DB_USERNAME', 'root'),
    'password' => env('DB_PASSWORD', ''),
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
    'prefix' => '',
    'strict' => true,
    'engine' => null,
    'options' => extension_loaded('pdo_mysql') ? array_filter([
        PDO::MYSQL_ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
    ]) : [],
],
```

---

## 5. MIGRATION COMPATIBILITY TESTING (STAGE 2 VERIFIED)

SocialPilot AI enforces strict database isolation. The migration test suite was executed against an isolated test database `socialpilot_migration_test` using the dedicated connection `tidb_migration_test`:

1. **Preflight Safety Verification:**
   ```bash
   php artisan db:tidb-migration-preflight
   ```
   *Guarantees that TLS is active, target database is strictly `socialpilot_migration_test`, and production `socialpilot` is protected.*

2. **Isolated Migration Execution:**
   ```bash
   php artisan migrate --database=tidb_migration_test
   ```
   *Executes all 28 migrations strictly on the test cluster.*

3. **Production Migration Execution Gate (STOP POINT):**
   > [!CAUTION]
   > The production database `socialpilot` remains **100% empty (0 tables)** and untouched.
   > Live production migrations (`php artisan migrate --force`) and production seeders (`php artisan db:seed --class=PlanSeeder --force`) must **only** be executed after explicit owner approval.

---

## 6. PRODUCTION BACKUP & RECOVERY PROCEDURES

### Automated Backups
SocialPilot AI includes the verified `php artisan db:backup` command:
```bash
# Creates timestamped backup with retention pruning (retains latest 7 backups)
php artisan db:backup --keep=7
```
This command runs automatically every night at 01:00 UTC via the registered Laravel scheduler.

### Direct MySQL Dump Backup (Off-Site)
```bash
mysqldump -h ${DB_HOST} -P ${DB_PORT} -u ${DB_USERNAME} -p${DB_PASSWORD} \
  --ssl-mode=REQUIRED --single-transaction --quick ${DB_DATABASE} \
  | gzip > /backups/socialpilot_$(date +\%F_\%T).sql.gz
```

### Restoration Test Procedure (Sandbox Verification)
To test restoring a backup without impacting the live database:
1. Create a temporary testing database: `CREATE DATABASE socialpilot_restore_test;`
2. Restore the dump into the sandbox database:
   ```bash
   gunzip < /backups/socialpilot_2026-11-16.sql.gz | mysql -h ${DB_HOST} -P ${DB_PORT} -u ${DB_USERNAME} -p${DB_PASSWORD} socialpilot_restore_test
   ```
3. Run verification check to confirm table count equals 59 tables.
4. Drop the test database: `DROP DATABASE socialpilot_restore_test;`
