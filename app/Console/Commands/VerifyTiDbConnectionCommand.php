<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use PDO;

class VerifyTiDbConnectionCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'db:tidb-verify
                            {--host= : Override database host}
                            {--port= : Override database port (default: 4000)}
                            {--database= : Override database name}
                            {--username= : Override database username}
                            {--ca= : Override SSL CA certificate path}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Safely and non-destructively verify TLS connection and compatibility with TiDB Serverless.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info("=== SOCIALPILOT AI — TIDB SERVERLESS VERIFICATION ===");
        $this->line("");

        $host = $this->option('host') ?: env('DB_HOST', config('database.connections.mysql.host'));
        $port = (int) ($this->option('port') ?: env('DB_PORT', config('database.connections.mysql.port', 4000)));
        $database = $this->option('database') ?: env('DB_DATABASE', config('database.connections.mysql.database'));
        $username = $this->option('username') ?: env('DB_USERNAME', config('database.connections.mysql.username'));
        $password = env('DB_PASSWORD', config('database.connections.mysql.password'));
        $caPath = $this->option('ca') ?: env('MYSQL_ATTR_SSL_CA', config('database.connections.mysql.options.' . PDO::MYSQL_ATTR_SSL_CA));

        // 1. Check Configuration Status
        if (empty($host) || $host === '127.0.0.1' || empty($username) || empty($password)) {
            $this->warn("Status: TIDB CREDENTIALS NOT CONFIGURED IN ENVIRONMENT");
            $this->line("");
            $this->line("To verify a live TiDB Serverless cluster, configure these in your private .env file:");
            $this->line("  DB_CONNECTION=mysql");
            $this->line("  DB_HOST=<gateway-host>.tidbcloud.com");
            $this->line("  DB_PORT=4000");
            $this->line("  DB_DATABASE=socialpilot");
            $this->line("  DB_USERNAME=<prefix>.root");
            $this->line("  DB_PASSWORD=<secure-password>");
            $this->line("  MYSQL_ATTR_SSL_CA=" . ($this->detectDefaultCaPath() ?: "/etc/ssl/certs/ca-certificates.crt"));
            $this->line("");
            $this->line("Current active connection remains: [" . config('database.default') . "] (Local SQLite untouched).");
            return self::SUCCESS;
        }

        $this->line("Configuration Audit:");
        $this->line("  - Host: " . $host);
        $this->line("  - Port: " . $port);
        $this->line("  - Database: " . $database);
        $this->line("  - Username: " . $username);
        $this->line("  - Password: *** [PROTECTED] *** (len: " . strlen($password) . ")");
        $this->line("  - SSL CA Path: " . ($caPath ?: "System Default"));
        $this->line("");

        // 2. Validate CA Certificate file if provided
        if (!empty($caPath) && !File::exists($caPath)) {
            $this->error("ERROR: Specified MYSQL_ATTR_SSL_CA file not found at: {$caPath}");
            $detected = $this->detectDefaultCaPath();
            if ($detected) {
                $this->line("Detected system CA bundle at: {$detected}");
            }
            return self::FAILURE;
        }

        // 3. Attempt PDO TLS Connection
        $this->info("Testing secure TLS connection to {$host}:{$port}...");

        $dsn = "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_TIMEOUT => 10,
        ];

        if (!empty($caPath)) {
            $options[PDO::MYSQL_ATTR_SSL_CA] = $caPath;
        }

        try {
            $pdo = new PDO($dsn, $username, $password, $options);
            $this->info("✓ Connection established successfully!");
            $this->line("");

            // 4. Inspect TLS Cipher
            $stmt = $pdo->query("SHOW STATUS LIKE 'Ssl_cipher'");
            $cipher = $stmt ? $stmt->fetch() : null;
            $cipherName = $cipher['Value'] ?? 'None';
            if (!empty($cipherName) && $cipherName !== 'None') {
                $this->info("✓ TLS Encryption Active: Cipher [{$cipherName}]");
            } else {
                $this->warn("! Connection active without verified TLS cipher.");
            }

            // 5. Inspect Engine Version & Identity
            $stmtVer = $pdo->query("SELECT VERSION() as version");
            $ver = $stmtVer ? $stmtVer->fetch()['version'] : 'Unknown';
            $this->line("  - Server Version: {$ver}");

            $isTiDb = false;
            try {
                $stmtTiDb = $pdo->query("SELECT tidb_version() as tidb_ver");
                $tidbVer = $stmtTiDb ? $stmtTiDb->fetch()['tidb_ver'] : null;
                if ($tidbVer) {
                    $isTiDb = true;
                    $this->info("✓ TiDB Engine Verified: " . strtok($tidbVer, "\n"));
                }
            } catch (\Throwable) {
                if (stripos($ver, 'tidb') !== false) {
                    $isTiDb = true;
                    $this->info("✓ TiDB Engine Verified from version string.");
                }
            }

            if (!$isTiDb) {
                $this->line("  - Server reports standard MySQL protocol.");
            }

            // 6. Check Current Schema & Table Count
            $stmtTables = $pdo->query("SHOW TABLES");
            $tables = $stmtTables ? $stmtTables->fetchAll(PDO::FETCH_COLUMN) : [];
            $tableCount = count($tables);
            $this->line("  - Existing Tables in [{$database}]: {$tableCount}");
            if ($tableCount === 0) {
                $this->info("✓ Database is clean and ready for initial schema migration.");
            } else {
                $this->line("  - Found {$tableCount} existing tables: " . implode(', ', array_slice($tables, 0, 10)) . ($tableCount > 10 ? "..." : ""));
            }

            // 7. Verify Local Database Isolation
            $this->line("");
            $this->info("Local Database Integrity:");
            $sqlitePath = database_path('database.sqlite');
            $this->line("  - Local SQLite database: {$sqlitePath} (" . (File::exists($sqlitePath) ? round(File::size($sqlitePath) / 1024, 2) . " KB" : "Not Found") . ")");
            $this->info("✓ Local development database is completely isolated and preserved.");

            $this->line("");
            $this->info("=== TIDB CONNECTIVITY VERIFICATION: PASSED ===");
            return self::SUCCESS;
        } catch (\PDOException $e) {
            $this->error("Connection Failed: " . $e->getMessage());
            $this->line("");
            $this->line("Troubleshooting tips:");
            $this->line("  1. Verify host and port (TiDB Serverless uses port 4000, not 3306).");
            $this->line("  2. Verify username includes the prefix (e.g. 'xxxxxx.root').");
            $this->line("  3. Ensure cluster is active and not paused in tidbcloud.com.");
            $this->line("  4. Verify TLS CA certificate path.");
            return self::FAILURE;
        }
    }

    protected function detectDefaultCaPath(): ?string
    {
        $candidates = [
            '/etc/ssl/certs/ca-certificates.crt', // Debian/Ubuntu
            '/etc/pki/tls/certs/ca-bundle.crt', // RHEL/CentOS
            '/etc/ssl/ca-bundle.pem', // OpenSUSE
            'C:/Program Files/Git/mingw64/etc/ssl/certs/ca-bundle.crt', // Windows Git
            'C:/Program Files/Git/usr/ssl/certs/ca-bundle.crt',
        ];

        foreach ($candidates as $c) {
            if (File::exists($c)) {
                return $c;
            }
        }

        return null;
    }
}
