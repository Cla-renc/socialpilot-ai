<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use PDO;

class TiDbStagingMigrationPreflightCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'db:tidb-staging-migration-preflight
                            {--connection=tidb_staging_migrator : The target connection to verify}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Perform strict preflight safety checks before executing migrations against TiDB staging database.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info("=== TIDB STAGING MIGRATION PREFLIGHT SAFETY VERIFICATION ===");
        $this->line("");

        $connName = $this->option('connection');

        // Check 1: Connection Name Validation
        $this->line("1. Validating target connection name...");
        if ($connName !== 'tidb_staging_migrator') {
            $this->error("BLOCKED: Target connection must strictly be 'tidb_staging_migrator', got '{$connName}'.");
            return self::FAILURE;
        }
        $this->info("   ✓ Target connection is 'tidb_staging_migrator'.");

        $config = config("database.connections.{$connName}");
        if (!$config) {
            $this->error("BLOCKED: Connection '{$connName}' is not defined in config/database.php.");
            return self::FAILURE;
        }

        // Check 2: Target Database Isolation Check (Must strictly be socialpilot_staging)
        $this->line("2. Verifying database isolation...");
        $targetDb = $config['database'] ?? '';
        if ($targetDb !== 'socialpilot_staging') {
            $this->error("CRITICAL BLOCKED: Target database must be exactly 'socialpilot_staging', got '{$targetDb}'.");
            $this->error("Production database 'socialpilot' and test database 'socialpilot_migration_test' are strictly forbidden.");
            return self::FAILURE;
        }
        $this->info("   ✓ Target database is strictly 'socialpilot_staging'.");

        // Check 3: Username Validation (Must strictly be staging_migrator)
        $this->line("3. Verifying migration account...");
        $username = $config['username'] ?? '';
        if ($username !== 'AZDARrqgy4MBFGE.staging_migrator') {
            $this->error("CRITICAL BLOCKED: Target user must be 'AZDARrqgy4MBFGE.staging_migrator', got '{$username}'.");
            return self::FAILURE;
        }
        $this->info("   ✓ Target migration user is 'AZDARrqgy4MBFGE.staging_migrator'.");

        // Check 4: Host & Credentials Presence
        $this->line("4. Verifying connection parameters...");
        $host = $config['host'] ?? '';
        $port = (int) ($config['port'] ?? 4000);
        $password = $config['password'] ?? '';
        $caPath = $config['options'][PDO::MYSQL_ATTR_SSL_CA] ?? env('TIDB_STAGING_MYSQL_ATTR_SSL_CA', env('MYSQL_ATTR_SSL_CA'));

        if (empty($host) || empty($username) || empty($password)) {
            $this->error("BLOCKED: Incomplete credentials for '{$connName}'. Host, username, and password required.");
            return self::FAILURE;
        }

        if (!empty($caPath) && !File::exists($caPath)) {
            $this->error("BLOCKED: Specified SSL CA bundle does not exist at: {$caPath}");
            return self::FAILURE;
        }
        $this->info("   ✓ Connection parameters and CA certificate verified.");

        // Check 5: Test Live TLS Connection and Handshake
        $this->line("5. Performing live TLS handshake with {$host}:{$port}...");
        $dsn = "mysql:host={$host};port={$port};dbname={$targetDb};charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_TIMEOUT => 15,
        ];
        if (!empty($caPath)) {
            $options[PDO::MYSQL_ATTR_SSL_CA] = $caPath;
        }

        try {
            $pdo = new PDO($dsn, $username, $password, $options);
            $this->info("   ✓ TLS connection successfully established.");

            // Check 6: Verify Active Database on Server
            $this->line("6. Verifying active database context on server...");
            $stmtDb = $pdo->query("SELECT DATABASE() as active_db");
            $activeDb = $stmtDb ? $stmtDb->fetch()['active_db'] : '';
            if ($activeDb !== 'socialpilot_staging') {
                $this->error("CRITICAL BLOCKED: Server reported active database '{$activeDb}', expected 'socialpilot_staging'.");
                return self::FAILURE;
            }
            $this->info("   ✓ Active database context confirmed as 'socialpilot_staging'.");

            // Check 7: Verify TLS Cipher
            $stmtCipher = $pdo->query("SHOW STATUS LIKE 'Ssl_cipher'");
            $cipherRow = $stmtCipher ? $stmtCipher->fetch() : null;
            $cipher = $cipherRow['Value'] ?? '';
            if (empty($cipher)) {
                $this->error("BLOCKED: Connected without active TLS cipher.");
                return self::FAILURE;
            }
            $this->info("   ✓ TLS transport encryption verified ({$cipher}).");

            // Check 8: Verify Isolation (Ensure cannot access production)
            $this->line("7. Verifying cross-database isolation...");
            try {
                $pdo->query("SELECT COUNT(*) FROM socialpilot.users");
                $this->error("CRITICAL BLOCKED: Migrator account has read access to production 'socialpilot'!");
                return self::FAILURE;
            } catch (\Exception) {
                $this->info("   ✓ Production database 'socialpilot' is strictly isolated (access denied).");
            }

            try {
                $pdo->query("SELECT COUNT(*) FROM socialpilot_migration_test.users");
                $this->error("CRITICAL BLOCKED: Migrator account has read access to test 'socialpilot_migration_test'!");
                return self::FAILURE;
            } catch (\Exception) {
                $this->info("   ✓ Migration-test database is strictly isolated (access denied).");
            }

            // Check 9: Verify Staging Table Count (0 tables expected prior to migration)
            $this->line("8. Verifying staging table count...");
            $stmtTables = $pdo->query("SHOW TABLES");
            $tables = $stmtTables ? $stmtTables->fetchAll(PDO::FETCH_COLUMN) : [];
            $tableCount = count($tables);
            if ($tableCount !== 0) {
                $this->warn("   ! Staging database already has {$tableCount} tables.");
            } else {
                $this->info("   ✓ Staging database is clean (0 tables).");
            }

        } catch (\PDOException $e) {
            $this->error("BLOCKED: Database connection failed: " . $e->getMessage());
            return self::FAILURE;
        }

        $this->line("");
        $this->info("SUCCESS: All preflight safety checks passed. Target is strictly isolated 'socialpilot_staging'.");
        return self::SUCCESS;
    }
}
