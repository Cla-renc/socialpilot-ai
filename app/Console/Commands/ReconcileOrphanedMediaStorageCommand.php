<?php

namespace App\Console\Commands;

use App\Jobs\PurgeUserMediaStorageJob;
use App\Models\Brand;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ReconcileOrphanedMediaStorageCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'media:reconcile-orphans
                            {--manifest= : Path to an approved candidate manifest for Stage B execution}
                            {--force : Explicit authorization flag required for Stage B destructive execution}
                            {--grace-hours=48 : Age grace period in hours for candidate detection (default 48h)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Audit and safely reconcile orphaned media directories in Supabase S3 storage (Dry-run by default)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Starting media storage orphan reconciliation process...');

        // 1. Database Health & Connectivity Verification
        if (! $this->verifyDatabaseHealth()) {
            return self::FAILURE;
        }

        $manifestOption = $this->option('manifest');
        $forceOption = (bool) $this->option('force');

        // Route to Stage B if manifest is provided, otherwise default to Stage A (Dry-run inventory)
        if ($manifestOption) {
            return $this->executeStageB($manifestOption, $forceOption);
        }

        return $this->executeStageA();
    }

    /**
     * Verify database connection and baseline production anchor records.
     */
    protected function verifyDatabaseHealth(): bool
    {
        try {
            DB::connection()->getPdo();
            DB::select('SELECT 1');
        } catch (Throwable $e) {
            $this->error('[CRITICAL] Database connection failed or unavailable: ' . $e->getMessage());
            return false;
        }

        // In production environment, enforce standing anchor record verification
        if (app()->environment('production')) {
            $userAnchorCount = User::query()->whereIn('id', PurgeUserMediaStorageJob::PROTECTED_USER_IDS)->count();
            $brandAnchorCount = Brand::query()->whereIn('id', PurgeUserMediaStorageJob::PROTECTED_BRAND_IDS)->count();

            if ($userAnchorCount < 1 || $brandAnchorCount < 1) {
                $this->error('[CRITICAL] Baseline production anchor records are missing. Database state is degraded or unseeded. Aborting.');
                return false;
            }
        }

        return true;
    }

    /**
     * Stage A: Dry-run inventory, candidate detection, 48h grace check, and manifest generation.
     */
    protected function executeStageA(): int
    {
        $this->info('[STAGE A] Running dry-run storage inventory and orphan detection...');

        $mediaDisk = config('services.media.storage_disk', 'supabase');
        $graceHours = max(1, (int) $this->option('grace-hours'));
        $cutoffTimestamp = Carbon::now()->subHours($graceHours)->timestamp;

        // 1. Enumerate media directories
        try {
            $directories = Storage::disk($mediaDisk)->directories('media');
        } catch (Throwable $e) {
            $this->error('[CRITICAL] Storage directory enumeration failed: ' . $e->getMessage());
            return self::FAILURE;
        }

        // 2. Snapshot existing database brand IDs
        try {
            $existingBrandIds = Brand::query()->pluck('id')->all();
            $existingBrandIdMap = array_flip($existingBrandIds);
        } catch (Throwable $e) {
            $this->error('[CRITICAL] Failed to query brands from database: ' . $e->getMessage());
            return self::FAILURE;
        }

        $scannedCount = 0;
        $activeCount = 0;
        $graceCount = 0;
        $protectedCount = 0;
        $candidates = [];

        foreach ($directories as $directory) {
            $scannedCount++;
            $cleanDir = trim($directory, '/');
            $parts = explode('/', $cleanDir);
            $folderName = end($parts);

            if (! ctype_digit($folderName)) {
                continue;
            }

            $brandId = (int) $folderName;

            // Check A: Immutable Protected Brand Blacklist
            if (in_array($brandId, PurgeUserMediaStorageJob::PROTECTED_BRAND_IDS, true)) {
                $protectedCount++;
                continue;
            }

            // Check B: Active Brand Check
            if (isset($existingBrandIdMap[$brandId])) {
                $activeCount++;
                continue;
            }

            // Check C: 48-Hour Age Grace Period Check
            try {
                $files = Storage::disk($mediaDisk)->allFiles($directory);
            } catch (Throwable $e) {
                $this->error("[ERROR] Failed to list files in {$directory}: " . $e->getMessage());
                return self::FAILURE;
            }

            $dirBytes = 0;
            $newestTimestamp = 0;

            foreach ($files as $file) {
                try {
                    $mtime = Storage::disk($mediaDisk)->lastModified($file);
                    $size = Storage::disk($mediaDisk)->size($file);
                } catch (Throwable) {
                    $mtime = time();
                    $size = 0;
                }

                $dirBytes += $size;
                if ($mtime > $newestTimestamp) {
                    $newestTimestamp = $mtime;
                }
            }

            // If newest file was modified within the grace period, do NOT mark as candidate
            if ($newestTimestamp > $cutoffTimestamp && ! empty($files)) {
                $graceCount++;
                $this->line("  [IN GRACE PERIOD] {$directory} (last modified " . Carbon::createFromTimestamp($newestTimestamp)->diffForHumans() . ')');
                continue;
            }

            $candidates[] = [
                'brand_id'                    => $brandId,
                'directory'                   => $directory,
                'file_count'                  => count($files),
                'total_bytes'                 => $dirBytes,
                'newest_object_last_modified' => $newestTimestamp > 0 ? Carbon::createFromTimestamp($newestTimestamp)->toIso8601String() : null,
            ];
        }

        // 3. Generate Manifest
        $manifestData = [
            'schema_version'             => '1.0',
            'generated_at'               => now()->toIso8601String(),
            'environment'                => app()->environment(),
            'grace_period_hours'         => $graceHours,
            'total_directories_scanned'  => $scannedCount,
            'active_directories_skipped' => $activeCount,
            'grace_directories_skipped'  => $graceCount,
            'protected_skipped'          => $protectedCount,
            'candidate_count'            => count($candidates),
            'candidates'                 => $candidates,
        ];

        $manifestJson = json_encode($manifestData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $manifestHash = substr(hash('sha256', $manifestJson), 0, 12);
        $manifestFileName = 'reconciliation/manifest_' . now()->format('Ymd_His') . "_{$manifestHash}.json";

        Storage::disk('local')->put($manifestFileName, $manifestJson);

        // 4. Output Summary Table
        $this->newLine();
        $this->info('=== RECONCILIATION DRY-RUN INVENTORY SUMMARY ===');
        $this->table(
            ['Metric', 'Count'],
            [
                ['Total Directories Scanned', $scannedCount],
                ['Active Brand Directories (Preserved)', $activeCount],
                ['Protected Baseline Brands (Preserved)', $protectedCount],
                ['In Grace Period (< ' . $graceHours . 'h, Preserved)', $graceCount],
                ['Candidate Orphan Directories', count($candidates)],
            ]
        );

        if (! empty($candidates)) {
            $this->newLine();
            $this->warn('Candidate Orphan Directories (ZERO DELETIONS PERFORMED):');
            $rows = array_map(fn ($c) => [$c['brand_id'], $c['directory'], $c['file_count'], number_format($c['total_bytes'] / 1024, 2) . ' KB', $c['newest_object_last_modified'] ?? 'N/A'], $candidates);
            $this->table(['Brand ID', 'Storage Prefix', 'Files', 'Size', 'Last Modified'], $rows);
        }

        $this->info("Manifest generated: storage/app/{$manifestFileName}");
        $this->line("To execute Stage B deletion (separately authorized), use: php artisan media:reconcile-orphans --manifest={$manifestFileName} --force");

        return self::SUCCESS;
    }

    /**
     * Stage B: Separately authorized destructive execution from validated manifest.
     */
    protected function executeStageB(string $manifestPath, bool $force): int
    {
        $this->warn('[STAGE B] Manifest-based reconciliation execution requested.');

        if (! $force) {
            $this->error('[ABORT] Destructive Stage B execution requires explicit --force flag.');
            return self::FAILURE;
        }

        // 1. Read manifest from local disk
        $cleanPath = ltrim($manifestPath, '/');
        if (! Storage::disk('local')->exists($cleanPath)) {
            $this->error("[ABORT] Manifest file not found: {$cleanPath}");
            return self::FAILURE;
        }

        $manifestContent = Storage::disk('local')->get($cleanPath);
        $manifest = json_decode($manifestContent, true);

        if (! is_array($manifest) || ! isset($manifest['candidates'], $manifest['generated_at'])) {
            $this->error('[ABORT] Invalid or corrupted manifest schema.');
            return self::FAILURE;
        }

        // 2. Validate manifest age (must be < 24 hours old)
        $generatedAt = Carbon::parse($manifest['generated_at']);
        if ($generatedAt->diffInHours(now()) >= 24) {
            $this->error('[ABORT] Manifest has expired (> 24 hours old). Run Stage A dry-run to generate a fresh manifest.');
            return self::FAILURE;
        }

        $candidates = $manifest['candidates'];
        if (empty($candidates)) {
            $this->info('Manifest contains zero candidate orphans. Nothing to delete.');
            return self::SUCCESS;
        }

        $mediaDisk = config('services.media.storage_disk', 'supabase');
        $deletedCount = 0;
        $totalBytesReclaimed = 0;
        $auditLog = [];

        // 3. Process Candidates with Fresh Database Revalidation
        foreach ($candidates as $candidate) {
            $brandId = (int) ($candidate['brand_id'] ?? 0);
            $directory = $candidate['directory'] ?? '';

            // Safeguard A: Immutable Protected Brand Blacklist
            if (in_array($brandId, PurgeUserMediaStorageJob::PROTECTED_BRAND_IDS, true)) {
                $this->warn("Skipping protected brand {$brandId}");
                continue;
            }

            // Safeguard B: Fresh Database Revalidation
            // If the brand exists in the database NOW, do NOT delete it!
            if (Brand::query()->where('id', $brandId)->exists()) {
                $this->warn("Skipping candidate brand {$brandId}: Brand currently exists in database.");
                continue;
            }

            // Safeguard C: Validate directory format matches media/{brandId}
            if ($directory !== "media/{$brandId}") {
                $this->error("Skipping malformed directory prefix: {$directory}");
                continue;
            }

            try {
                Storage::disk($mediaDisk)->deleteDirectory($directory);
                $deletedCount++;
                $bytes = (int) ($candidate['total_bytes'] ?? 0);
                $totalBytesReclaimed += $bytes;

                $auditLog[] = [
                    'brand_id'       => $brandId,
                    'directory'      => $directory,
                    'deleted_at'     => now()->toIso8601String(),
                    'bytes_reclaimed'=> $bytes,
                ];

                $this->info("Deleted orphan directory: {$directory}");
            } catch (Throwable $e) {
                $this->error("Failed to delete directory {$directory}: " . $e->getMessage());
            }
        }

        // 4. Save Audit Record
        $auditData = [
            'manifest_executed'      => $cleanPath,
            'executed_at'            => now()->toIso8601String(),
            'directories_deleted'    => $deletedCount,
            'total_bytes_reclaimed'  => $totalBytesReclaimed,
            'audit_log'              => $auditLog,
        ];

        $auditFileName = 'reconciliation/audit_' . now()->format('Ymd_His') . '.json';
        Storage::disk('local')->put($auditFileName, json_encode($auditData, JSON_PRETTY_PRINT));

        $this->info("Stage B execution complete. Deleted {$deletedCount} orphan directories. Reclaimed " . number_format($totalBytesReclaimed / 1024, 2) . ' KB.');
        $this->info("Audit log written to: storage/app/{$auditFileName}");

        return self::SUCCESS;
    }
}
