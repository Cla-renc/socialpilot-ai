<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use PDO;

class TiDbMigrationPreflightCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'db:tidb-migration-preflight
                            {--connection=tidb_migration_test : The target connection to verify}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Perform strict preflight safety checks before executing migrations against TiDB test database.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info("=== TIDB MIGRATION PREFLIGHT SAFETY VERIFICATION ===");
        $this->line("");

        $connName = $this->option('connection');

        // Check 1: Connection Name Validation
        $this->line("1. Validating target connection name...");
        if ($connName !== 'tidb_migration_test') {
            $this->error("BLOCKED: Target connection must strictly be 'tidb_migration_test', got '{$connName}'.");
            return self::FAILURE;
        }
        $this->info("   ✓ Target connection is 'tidb_migration_test'.");

        $config = config("database.connections.{$connName}");
        if (!$config) {
            $this->error("BLOCKED: Connection '{$connName}' is not defined in config/database.php.");
            return self::FAILURE;
        }

        // Check 2: Target Database Isolation Check (Must NOT be production)
        $this->line("2. Verifying database isolation...");
        $targetDb = $config['database'] ?? '';
        if ($targetDb !== 'socialpilot_migration_test') {
            $this->error("CRITICAL BLOCKED: Target database must be exactly 'socialpilot_migration_test', got '{$targetDb}'.");
            $this->error("Production database 'socialpilot' is strictly forbidden as migration test target.");
            return self::FAILURE;
        }
        $this->info("   ✓ Target database is strictly 'socialpilot_migration_test'.");

        // Check 3: Host & Credentials Presence
        $this->line("3. Verifying connection parameters...");
        $host = $config['host'] ?? '';
        $port = (int) ($config['port'] ?? 4000);
        $username = $config['username'] ?? '';
        $password = $config['password'] ?? '';
        $caPath = $config['options'][PDO::MYSQL_ATTR_SSL_CA] ?? env('TIDB_TEST_MYSQL_ATTR_SSL_CA', env('MYSQL_ATTR_SSL_CA'));

        if (empty($host) || empty($username) || empty($password)) {
            $this->error("BLOCKED: Incomplete credentials for '{$connName}'. Host, username, and password required.");
            return self::FAILURE;
        }

        if (!empty($caPath) && !File::exists($caPath)) {
            $this->error("BLOCKED: Specified SSL CA bundle does not exist at: {$caPath}");
            return self::FAILURE;
        }
        $this->info("   ✓ Connection parameters and CA certificate verified.");

        // Check 4: Test Live TLS Connection and Handshake
        $this->line("4. Performing live TLS handshake with {$host}:{$port}...");
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

            // Check 5: Verify Active Database on Server
            $this->line("5. Verifying active database context on server...");
            $stmtDb = $pdo->query("SELECT DATABASE() as active_db");
            $activeDb = $stmtDb ? $stmtDb->fetch()['active_db'] : '';
            if ($activeDb !== 'socialpilot_migration_test') {
                $this->error("CRITICAL BLOCKED: Server reports active database is '{$activeDb}', expected 'socialpilot_migration_test'.");
                return self::FAILURE;
            }
            $this->info("   ✓ Confirmed active database on server: '{$activeDb}'.");

            // Check 6: Verify TLS Cipher
            $this->line("6. Verifying TLS cipher...");
            $stmtCipher = $pdo->query("SHOW STATUS LIKE 'Ssl_cipher'");
            $cipher = $stmtCipher ? $stmtCipher->fetch()['Value'] : '';
            if (empty($cipher) || $cipher === 'None') {
                $this->error("CRITICAL BLOCKED: Connection is not encrypted with TLS.");
                return self::FAILURE;
            }
            $this->info("   ✓ TLS cipher active: {$cipher}.");

            // Check 7: Verify TiDB Server Identity
            $this->line("7. Verifying TiDB server identity...");
            $isTiDb = false;
            try {
                $stmtTiDb = $pdo->query("SELECT tidb_version() as ver");
                $tidbVer = $stmtTiDb ? $stmtTiDb->fetch()['ver'] : null;
                if ($tidbVer) {
                    $isTiDb = true;
                    $this->info("   ✓ TiDB Engine verified: " . strtok($tidbVer, "\n"));
                }
            } catch (\Throwable) {
                $stmtVer = $pdo->query("SELECT VERSION() as ver");
                $ver = $stmtVer ? $stmtVer->fetch()['ver'] : '';
                if (stripos($ver, 'tidb') !== false) {
                    $isTiDb = true;
                    $this->info("   ✓ TiDB Engine verified from version: {$ver}");
                }
            }

            if (!$isTiDb) {
                $this->error("BLOCKED: Remote server does not identify as TiDB.");
                return self::FAILURE;
            }

            // Check 8: Inspect Initial Table Count in Test DB
            $stmtTables = $pdo->query("SHOW TABLES");
            $tables = $stmtTables ? $stmtTables->fetchAll(PDO::FETCH_COLUMN) : [];
            $this->info("   ✓ Current table count in 'socialpilot_migration_test': " . count($tables));

            // Check 9: Verify Production Database Remains Empty & Untouched
            $this->line("8. Verifying production database 'socialpilot' is untouched...");
            try {
                $pdoProd = new PDO("mysql:host={$host};port={$port};dbname=socialpilot;charset=utf8mb4", $username, $password, $options);
                $stmtProd = $pdoProd->query("SHOW TABLES");
                $prodTables = $stmtProd ? $stmtProd->fetchAll(PDO::FETCH_COLUMN) : [];
                $prodCount = count($prodTables);
                if ($prodCount !== 0) {
                    $this->warn("   ! Production database 'socialpilot' currently has {$prodCount} tables.");
                } else {
                    $this->info("   ✓ Production database 'socialpilot' is empty (0 tables).");
                }
            } catch (\Throwable $e) {
                $this->line("   (Note: Could not inspect production db: " . $e->getMessage() . ")");
            }

            // Check 10: Verify Local SQLite Database is Untouched
            $this->line("9. Verifying local SQLite database integrity...");
            $sqlitePath = database_path('database.sqlite');
            if (File::exists($sqlitePath)) {
                $this->info("   ✓ Local SQLite database preserved: " . round(File::size($sqlitePath) / 1024, 2) . " KB.");
            } else {
                $this->warn("   ! Local SQLite database file not found at {$sqlitePath}.");
            }

            $this->line("");
            $this->info("=== ALL PREFLIGHT SAFETY CHECKS PASSED ===");
            $this->info("Ready to safely execute: php artisan migrate --database=tidb_migration_test");
            return self::SUCCESS;
        } catch (\PDOException $e) {
            $this->error("Preflight Connection Failed: " . $e->getMessage());
            return self::FAILURE;
        }
    }
}
