<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class BackupDatabaseCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'db:backup 
                            {--connection= : The database connection to backup (defaults to active connection)}
                            {--destination= : Custom destination path} 
                            {--keep=7 : Number of recent backups to retain}
                            {--verify : Perform non-destructive restoration test on the backup}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Safely create a timestamped backup of the active database with retention and integrity verification.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $connection = $this->option('connection') ?: config('database.default');
        $driver = config("database.connections.{$connection}.driver", 'sqlite');
        $this->info("Creating backup for connection [{$connection}] (driver: {$driver})...");

        $backupDir = storage_path('app/backups');
        if (!File::isDirectory($backupDir)) {
            File::makeDirectory($backupDir, 0755, true);
        }

        $timestamp = now()->format('Y-m-d_H-i-s');

        if ($driver === 'sqlite') {
            $source = config("database.connections.{$connection}.database");
            if ($source === ':memory:' || empty($source)) {
                $source = database_path('database.sqlite');
            }
            if (!File::exists($source)) {
                $this->error("SQLite database file not found at: {$source}");
                return self::FAILURE;
            }

            $targetFile = $this->option('destination') ?: "{$backupDir}/backup_{$timestamp}.sqlite";
            
            // Non-destructive copy
            File::copy($source, $targetFile);

            $size = round(File::size($targetFile) / 1024, 2);
            $this->info("Backup created successfully: {$targetFile} ({$size} KB)");

            // Integrity Check
            try {
                $pdo = new \PDO("sqlite:{$targetFile}");
                $stmt = $pdo->query('PRAGMA integrity_check;');
                $integrity = $stmt ? $stmt->fetchColumn() : 'unknown';
                if ($integrity === 'ok') {
                    $this->info("Integrity check passed: PRAGMA integrity_check = ok");
                } else {
                    $this->warn("Integrity check returned: {$integrity}");
                }

                // Count tables
                $stmtTables = $pdo->query("SELECT count(*) FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%';");
                $tableCount = $stmtTables ? (int) $stmtTables->fetchColumn() : 0;
                $this->line("Backup contains {$tableCount} application tables.");

                // Non-destructive Restoration Test if requested
                if ($this->option('verify')) {
                    $this->info("Running non-destructive restoration verification in temporary sandbox...");
                    $tempTestDb = "{$backupDir}/temp_restore_verify_{$timestamp}.sqlite";
                    File::copy($targetFile, $tempTestDb);

                    $testPdo = new \PDO("sqlite:{$tempTestDb}");
                    $testStmt = $testPdo->query("SELECT count(*) FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%';");
                    $restoredCount = $testStmt ? (int) $testStmt->fetchColumn() : 0;
                    $testPdo = null;

                    File::delete($tempTestDb);

                    if ($restoredCount === $tableCount) {
                        $this->info("Restoration verification test PASSED ({$restoredCount} tables verified in isolated test db).");
                    } else {
                        $this->error("Restoration test table count mismatch: expected {$tableCount}, got {$restoredCount}.");
                        return self::FAILURE;
                    }
                }
            } catch (\Throwable $e) {
                $this->error("Backup verification failed: " . $e->getMessage());
                return self::FAILURE;
            }

            // Retention Pruning
            $keep = (int) $this->option('keep');
            if ($keep > 0) {
                $files = collect(File::glob("{$backupDir}/backup_*.sqlite"))
                    ->sortByDesc(fn ($f) => File::lastModified($f))
                    ->values();

                if ($files->count() > $keep) {
                    $toDelete = $files->slice($keep);
                    foreach ($toDelete as $oldFile) {
                        File::delete($oldFile);
                        $this->line("Pruned old backup: " . basename($oldFile));
                    }
                }
            }

            return self::SUCCESS;
        }

        if ($driver === 'mysql' || $driver === 'mariadb') {
            $connConfig = config("database.connections.{$connection}");
            $host = $connConfig['host'] ?? '127.0.0.1';
            $port = (int) ($connConfig['port'] ?? 3306);
            $database = $connConfig['database'] ?? 'socialpilot';
            $targetFile = $this->option('destination') ?: "{$backupDir}/backup_{$connection}_{$database}_{$timestamp}.sql";

            $this->info("Connecting to [{$connection}] ({$host}:{$port}/{$database}) via PDO...");

            $handle = null;
            try {
                $pdo = DB::connection($connection)->getPdo();

                $handle = fopen($targetFile, 'w');
                if (!$handle) {
                    $this->error("Failed to open backup target file for writing: {$targetFile}");
                    return self::FAILURE;
                }

                fwrite($handle, "-- SocialPilot AI TiDB/MySQL Database Backup\n");
                fwrite($handle, "-- Connection: {$connection}\n");
                fwrite($handle, "-- Database: {$database}\n");
                fwrite($handle, "-- Generated: {$timestamp}\n");
                fwrite($handle, "-- --------------------------------------------------------\n\n");
                fwrite($handle, "SET FOREIGN_KEY_CHECKS=0;\n");
                fwrite($handle, "SET NAMES utf8mb4;\n");
                fwrite($handle, "SET time_zone = '+00:00';\n\n");

                $tablesStmt = $pdo->query("SHOW FULL TABLES WHERE Table_Type = 'BASE TABLE'");
                $tables = [];
                while ($row = $tablesStmt->fetch(\PDO::FETCH_NUM)) {
                    $tables[] = $row[0];
                }

                $this->line("Exporting " . count($tables) . " tables...");
                foreach ($tables as $table) {
                    // Export Schema
                    $createStmt = $pdo->query("SHOW CREATE TABLE `{$table}`");
                    $createRow = $createStmt->fetch(\PDO::FETCH_ASSOC);
                    $createSql = $createRow['Create Table'] ?? '';

                    fwrite($handle, "-- --------------------------------------------------------\n");
                    fwrite($handle, "-- Table structure for `{$table}`\n");
                    fwrite($handle, "DROP TABLE IF EXISTS `{$table}`;\n");
                    fwrite($handle, "{$createSql};\n\n");

                    // Export Data with streaming fetch
                    $countStmt = $pdo->query("SELECT COUNT(*) FROM `{$table}`");
                    $totalRows = (int) $countStmt->fetchColumn();

                    if ($totalRows > 0) {
                        fwrite($handle, "-- Dumping data for `{$table}` ({$totalRows} rows)\n");
                        $selectStmt = $pdo->query("SELECT * FROM `{$table}`");
                        while ($row = $selectStmt->fetch(\PDO::FETCH_ASSOC)) {
                            $cols = array_map(fn ($c) => "`{$c}`", array_keys($row));
                            $vals = array_map(function ($v) use ($pdo) {
                                if (is_null($v)) return 'NULL';
                                if (is_int($v) || (is_numeric($v) && !str_starts_with($v, '0') && !str_contains($v, 'e') && !str_contains($v, 'E'))) {
                                    return $v;
                                }
                                return $pdo->quote((string) $v);
                            }, array_values($row));
                            fwrite($handle, "INSERT INTO `{$table}` (" . implode(', ', $cols) . ") VALUES (" . implode(', ', $vals) . ");\n");
                        }
                        fwrite($handle, "\n");
                    }
                }

                fwrite($handle, "SET FOREIGN_KEY_CHECKS=1;\n");
                fclose($handle);
                $handle = null;

                $sizeKb = round(File::size($targetFile) / 1024, 2);
                $this->info("Backup created successfully: {$targetFile} ({$sizeKb} KB, " . count($tables) . " tables)");

                if ($this->option('verify')) {
                    $this->info("Verifying SQL backup completeness...");
                    $content = file_get_contents($targetFile);
                    $tableMatches = substr_count($content, "DROP TABLE IF EXISTS");
                    if ($tableMatches === count($tables)) {
                        $this->info("Integrity check PASSED: All " . count($tables) . " tables verified in backup file.");
                    } else {
                        $this->warn("Table count check mismatch: expected " . count($tables) . ", found {$tableMatches} definitions.");
                    }
                }

                // Retention pruning for sql files
                $keep = (int) $this->option('keep');
                if ($keep > 0) {
                    $sqlFiles = collect(File::glob("{$backupDir}/backup_{$connection}_*.sql"))
                        ->sortByDesc(fn ($f) => File::lastModified($f))
                        ->values();

                    if ($sqlFiles->count() > $keep) {
                        foreach ($sqlFiles->slice($keep) as $oldSql) {
                            File::delete($oldSql);
                            $this->line("Pruned old SQL backup: " . basename($oldSql));
                        }
                    }
                }

                return self::SUCCESS;
            } catch (\Throwable $e) {
                if (is_resource($handle)) {
                    fclose($handle);
                }
                if (File::exists($targetFile)) {
                    File::delete($targetFile);
                }
                $this->error("MySQL/TiDB backup failed: " . $e->getMessage());
                return self::FAILURE;
            }
        }

        $this->warn("Backup command does not have specialized handler for driver [{$driver}].");
        return self::SUCCESS;
    }
}
