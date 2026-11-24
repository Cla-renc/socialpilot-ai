<?php

namespace App\Jobs;

use App\Models\Brand;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class PurgeUserMediaStorageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Protected baseline asset constants.
     */
    public const PROTECTED_USER_IDS = [30001, 344308];
    public const PROTECTED_BRAND_IDS = [30001, 181643];

    /**
     * Number of retry attempts.
     */
    public int $tries = 3;

    /**
     * Retry backoff in seconds (15s, 60s, 300s).
     */
    public array $backoff = [15, 60, 300];

    /**
     * Bounded timeout to ensure execution never exceeds the operational cycle.
     */
    public int $timeout = 25;

    /**
     * Create a new job instance.
     *
     * @param array<int> $brandIds Array of brand IDs whose storage prefixes should be purged.
     * @param array<string> $logoPaths Array of brand logo paths on the public disk.
     * @param int|null $userId Owning user ID for validation against protected constants.
     */
    public function __construct(
        public array $brandIds,
        public array $logoPaths = [],
        public ?int $userId = null
    ) {
        $this->afterCommit();
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // 1. Protected User Circuit Breaker
        if ($this->userId !== null && in_array($this->userId, self::PROTECTED_USER_IDS, true)) {
            Log::warning('PurgeUserMediaStorageJob aborted: User is in protected baseline list.', [
                'user_id' => $this->userId,
            ]);
            return;
        }

        $mediaDisk = config('services.media.storage_disk', 'supabase');
        $publicDisk = 'public';

        // 2. Process Brand Media Directories on Supabase / Media Storage Disk
        foreach ($this->brandIds as $brandId) {
            $brandId = (int) $brandId;

            // Safeguard A: Immutable Protected Brand Blacklist
            if (in_array($brandId, self::PROTECTED_BRAND_IDS, true)) {
                Log::warning('PurgeUserMediaStorageJob skipped: Brand ID is in protected baseline list.', [
                    'brand_id' => $brandId,
                ]);
                continue;
            }

            // Safeguard B: Dynamic Active Brand Check
            // If the brand still exists in the database (e.g. transaction failed or rollback occurred), skip it!
            if (Brand::query()->where('id', $brandId)->exists()) {
                Log::warning('PurgeUserMediaStorageJob skipped: Brand is still active in the database.', [
                    'brand_id' => $brandId,
                ]);
                continue;
            }

            // Target prefix is strictly scoped to media/{brand_id}
            $targetPrefix = "media/{$brandId}";

            try {
                // Flysystem deleteDirectory is idempotent: returns cleanly if directory is empty or missing.
                Storage::disk($mediaDisk)->deleteDirectory($targetPrefix);

                Log::info('Purged media storage directory for deleted brand.', [
                    'brand_id' => $brandId,
                    'disk'     => $mediaDisk,
                ]);
            } catch (Throwable $e) {
                Log::error('Failed to purge media directory from storage. Job will retry.', [
                    'brand_id' => $brandId,
                    'disk'     => $mediaDisk,
                    'error'    => $e->getMessage(),
                ]);

                throw $e;
            }
        }

        // 3. Process Brand Logos on Public Disk
        foreach ($this->logoPaths as $logoPath) {
            if (! is_string($logoPath) || trim($logoPath) === '') {
                continue;
            }

            // Ensure path starts with expected brand-logos/ prefix
            $cleanPath = ltrim($logoPath, '/');
            if (! str_starts_with($cleanPath, 'brand-logos/')) {
                Log::warning('PurgeUserMediaStorageJob skipped logo path outside expected prefix.', [
                    'path_prefix' => substr($cleanPath, 0, 15),
                ]);
                continue;
            }

            try {
                // Idempotent delete on local/public disk
                Storage::disk($publicDisk)->delete($cleanPath);
            } catch (Throwable $e) {
                // Logo deletion on ephemeral disk is non-critical; log warning without breaking queue
                Log::warning('Non-critical: Failed to delete logo on public disk.', [
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
