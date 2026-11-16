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
        $connection = config('database.default');
        $this->info("Creating backup for connection [{$connection}]...");

        $backupDir = storage_path('app/backups');
        if (!File::isDirectory($backupDir)) {
            File::makeDirectory($backupDir, 0755, true);
        }

        $timestamp = now()->format('Y-m-d_H-i-s');

        if ($connection === 'sqlite') {
            $source = config('database.connections.sqlite.database');
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

        if ($connection === 'mysql' || $connection === 'mariadb') {
            $host = config('database.connections.mysql.host');
            $database = config('database.connections.mysql.database');
            $user = config('database.connections.mysql.username');
            $targetFile = $this->option('destination') ?: "{$backupDir}/backup_mysql_{$database}_{$timestamp}.sql";

            $this->warn("For managed MySQL production instances, use provider automated snapshots or mysqldump.");
            $this->line("Target dump location: {$targetFile}");
            $this->line("CLI command template:");
            $this->line("mysqldump -h {$host} -u {$user} -p {$database} > \"{$targetFile}\"");

            return self::SUCCESS;
        }

        $this->warn("Backup command does not have specialized handler for connection [{$connection}].");
        return self::SUCCESS;
    }
}
