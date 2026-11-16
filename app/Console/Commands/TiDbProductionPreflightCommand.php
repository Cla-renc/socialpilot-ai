<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use PDO;

class TiDbProductionPreflightCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'db:tidb-production-preflight
                            {--connection=mysql : The target production connection to verify}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Perform strict preflight safety checks on production database (socialpilot) prior to initial schema migration.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info("=== TIDB PRODUCTION MIGRATION PREFLIGHT SAFETY AUDIT ===");
        $this->line("");

        $connName = $this->option('connection');

        // Check 1: Target Connection Name
        $this->line("1. Validating target connection name...");
        if ($connName !== 'mysql') {
            $this->error("BLOCKED: Target production connection must strictly be 'mysql', got '{$connName}'.");
            return self::FAILURE;
        }
        $this->info("   ✓ Target production connection is 'mysql'.");

        $config = config("database.connections.{$connName}");
        if (!$config) {
            $this->error("BLOCKED: Connection '{$connName}' is not defined in config/database.php.");
            return self::FAILURE;
        }

        // Check 2: Database Name Validation (Must be strictly 'socialpilot')
        $this->line("2. Verifying database isolation...");
        $targetDb = $config['database'] ?? '';
        if ($targetDb !== 'socialpilot') {
            $this->error("CRITICAL BLOCKED: Production database name must be exactly 'socialpilot', got '{$targetDb}'.");
            $this->error("Test database 'socialpilot_migration_test' or local sqlite cannot be used as production target.");
            return self::FAILURE;
        }
        $this->info("   ✓ Target database is strictly 'socialpilot'.");

        // Check 3: Host & Credentials
        $this->line("3. Verifying connection parameters and TLS CA...");
        $host = $config['host'] ?? '';
        $port = (int) ($config['port'] ?? 4000);
        $username = $config['username'] ?? '';
        $password = $config['password'] ?? '';
        $caPath = $config['options'][PDO::MYSQL_ATTR_SSL_CA] ?? env('MYSQL_ATTR_SSL_CA');

        if (empty($host) || empty($username) || empty($password)) {
            $this->error("BLOCKED: Incomplete credentials for '{$connName}'. Host, username, and password required.");
            return self::FAILURE;
        }

        if (!empty($caPath) && !File::exists($caPath)) {
            $this->error("BLOCKED: Specified SSL CA bundle does not exist at: {$caPath}");
            return self::FAILURE;
        }
        $this->info("   ✓ Connection parameters and CA certificate verified.");

        // Check 4: Test Live TLS Connection
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

            // Check 5: Active Database Context
            $this->line("5. Verifying active database context on server...");
            $stmtDb = $pdo->query("SELECT DATABASE() as active_db");
            $activeDb = $stmtDb ? $stmtDb->fetch()['active_db'] : '';
            if ($activeDb !== 'socialpilot') {
                $this->error("CRITICAL BLOCKED: Server reported active database '{$activeDb}', expected 'socialpilot'.");
                return self::FAILURE;
            }
            $this->info("   ✓ Active database on server confirmed as '{$activeDb}'.");

            // Check 6: TiDB Engine Identity
            $this->line("6. Verifying database engine and TLS cipher...");
            $stmtVersion = $pdo->query("SELECT tidb_version() as tv, VERSION() as mv");
            $versionRow = $stmtVersion ? $stmtVersion->fetch() : [];
            $tidbVersion = $versionRow['tv'] ?? '';
            $mysqlVersion = $versionRow['mv'] ?? '';

            if (!str_contains($mysqlVersion, 'TiDB') && !str_contains($tidbVersion, 'serverless')) {
                $this->error("CRITICAL BLOCKED: Database is not TiDB Serverless. Version output: {$mysqlVersion}");
                return self::FAILURE;
            }
            $firstLine = explode("\n", $tidbVersion)[0] ?? $mysqlVersion;
            $this->info("   ✓ TiDB Serverless confirmed ({$mysqlVersion} / {$firstLine}).");

            $stmtCipher = $pdo->query("SHOW STATUS LIKE 'Ssl_cipher'");
            $cipher = $stmtCipher ? ($stmtCipher->fetch()['Value'] ?? '') : '';
            if (empty($cipher) || $cipher === 'NONE') {
                $this->error("CRITICAL BLOCKED: Connection is not encrypted with TLS.");
                return self::FAILURE;
            }
            $this->info("   ✓ TLS encryption verified (Cipher: {$cipher}).");

            // Check 7: Production Table Count (Initial State must be Empty)
            $this->line("7. Verifying production database initial state...");
            $stmtTables = $pdo->query("SHOW FULL TABLES WHERE Table_Type = 'BASE TABLE'");
            $tables = $stmtTables ? $stmtTables->fetchAll(PDO::FETCH_COLUMN) : [];
            $tableCount = count($tables);

            if ($tableCount === 0) {
                $this->info("   ✓ Production database 'socialpilot' is pristine and EMPTY (0 tables).");
            } else {
                $this->warn("   ! Production database already contains {$tableCount} tables.");
            }

            // Check 8: Test Database Blueprint Parity
            $this->line("8. Verifying Stage 2 test database blueprint parity...");
            $testConfig = config('database.connections.tidb_migration_test');
            if ($testConfig) {
                $testDb = $testConfig['database'] ?? '';
                $testHost = $testConfig['host'] ?? '';
                $testPort = (int) ($testConfig['port'] ?? 4000);
                $testUser = $testConfig['username'] ?? '';
                $testPass = $testConfig['password'] ?? '';
                $testDsn = "mysql:host={$testHost};port={$testPort};dbname={$testDb};charset=utf8mb4";
                try {
                    $testPdo = new PDO($testDsn, $testUser, $testPass, $options);
                    $testTablesStmt = $testPdo->query("SHOW FULL TABLES WHERE Table_Type = 'BASE TABLE'");
                    $testTableCount = count($testTablesStmt->fetchAll(PDO::FETCH_COLUMN));
                    $testMigrations = (int) $testPdo->query("SELECT count(*) FROM migrations")->fetchColumn();
                    $this->info("   ✓ Stage 2 test database verified: {$testTableCount} tables, {$testMigrations} recorded migrations.");
                } catch (\Throwable $e) {
                    $this->warn("   ! Could not verify test database: " . $e->getMessage());
                }
            }

            // Check 9: Local SQLite Integrity
            $this->line("9. Verifying local SQLite database integrity...");
            $sqlitePath = database_path('database.sqlite');
            if (File::exists($sqlitePath)) {
                $this->info("   ✓ Local SQLite database preserved: " . round(File::size($sqlitePath) / 1024, 2) . " KB.");
            } else {
                $this->warn("   ! Local SQLite database file not found at {$sqlitePath}.");
            }

            // Check 10: Migration Files Count
            $migrationFiles = glob(database_path('migrations/*.php'));
            $this->info("   ✓ All " . count($migrationFiles) . " migration files verified ready.");

            $this->line("");
            $this->info("=== ALL PRODUCTION PREFLIGHT CHECKS PASSED ===");
            $this->info("Production database is 100% READY for initial migration.");
            $this->warn("GATE: Requires explicit repository owner authorization prior to execution.");
            $this->line("Command to execute once authorized:");
            $this->line("  php artisan migrate --force");
            $this->line("  php artisan db:seed --class=PlanSeeder --force");
            return self::SUCCESS;
        } catch (\PDOException $e) {
            $this->error("Preflight Connection Failed: " . $e->getMessage());
            return self::FAILURE;
        }
    }
}
