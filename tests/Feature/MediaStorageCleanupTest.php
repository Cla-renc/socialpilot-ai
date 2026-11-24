<?php

namespace Tests\Feature;

use App\Jobs\PurgeUserMediaStorageJob;
use App\Models\Brand;
use App\Models\MediaAsset;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MediaStorageCleanupTest extends TestCase
{
    use RefreshDatabase;

    protected string $mediaDisk = 'supabase';

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.media.storage_disk' => $this->mediaDisk]);
        Storage::fake($this->mediaDisk);
        Storage::fake('public');
        Storage::fake('local');
    }

    /**
     * 1. Account deletion collects only the authenticated user's media.
     */
    public function test_account_deletion_collects_only_authenticated_users_media(): void
    {
        Queue::fake();

        $user1 = User::factory()->create();
        $brand1 = Brand::create([
            'user_id' => $user1->id,
            'name'    => 'Brand One',
            'status'  => 'active',
            'logo_path' => 'brand-logos/logo1.png',
        ]);

        $user2 = User::factory()->create();
        $brand2 = Brand::create([
            'user_id' => $user2->id,
            'name'    => 'Brand Two',
            'status'  => 'active',
            'logo_path' => 'brand-logos/logo2.png',
        ]);

        $response = $this->actingAs($user1)->delete('/profile', [
            'password' => 'password',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect('/');
        $this->assertNull($user1->fresh());
        $this->assertNotNull($user2->fresh());

        Queue::assertPushed(PurgeUserMediaStorageJob::class, function ($job) use ($brand1, $brand2) {
            return in_array($brand1->id, $job->brandIds, true)
                && ! in_array($brand2->id, $job->brandIds, true)
                && in_array('brand-logos/logo1.png', $job->logoPaths, true)
                && ! in_array('brand-logos/logo2.png', $job->logoPaths, true);
        });
    }

    /**
     * 2. Protected users and brands cannot be targeted.
     */
    public function test_protected_users_cannot_be_deleted(): void
    {
        $protectedUser = User::factory()->create([
            'id' => 30001,
        ]);

        $response = $this->actingAs($protectedUser)->delete('/profile', [
            'password' => 'password',
        ]);

        $response->assertStatus(403);
        $this->assertNotNull($protectedUser->fresh());
    }

    /**
     * 3. Active brands are skipped by the cleanup job.
     */
    public function test_active_brands_are_skipped_by_cleanup_job(): void
    {
        $user = User::factory()->create();
        $activeBrand = Brand::create([
            'user_id' => $user->id,
            'name'    => 'Still Active Brand',
            'status'  => 'active',
        ]);

        // Put a file in storage
        Storage::disk($this->mediaDisk)->put("media/{$activeBrand->id}/photo.jpg", 'binary data');

        // Dispatch job while brand is still in database
        $job = new PurgeUserMediaStorageJob([$activeBrand->id]);
        $job->handle();

        // Active brand directory must NOT be deleted
        Storage::disk($this->mediaDisk)->assertExists("media/{$activeBrand->id}/photo.jpg");
    }

    /**
     * 4. Deleted brand directories are cleaned up.
     */
    public function test_deleted_brand_directories_are_cleaned_up_by_job(): void
    {
        $brandId = 77777; // Does not exist in database
        Storage::disk($this->mediaDisk)->put("media/{$brandId}/photo.jpg", 'image content');
        Storage::disk($this->mediaDisk)->put("media/{$brandId}/video.mp4", 'video content');

        $job = new PurgeUserMediaStorageJob([$brandId]);
        $job->handle();

        Storage::disk($this->mediaDisk)->assertMissing("media/{$brandId}/photo.jpg");
        Storage::disk($this->mediaDisk)->assertMissing("media/{$brandId}/video.mp4");
    }

    /**
     * 5. Brand logos are handled on the verified public disk.
     */
    public function test_brand_logos_are_deleted_from_public_disk(): void
    {
        $logoPath = 'brand-logos/test_logo.png';
        Storage::disk('public')->put($logoPath, 'png bytes');

        $job = new PurgeUserMediaStorageJob([], [$logoPath]);
        $job->handle();

        Storage::disk('public')->assertMissing($logoPath);
    }

    /**
     * 6. Missing storage objects and already-deleted directories are handled safely.
     */
    public function test_missing_storage_objects_do_not_throw_errors(): void
    {
        $job = new PurgeUserMediaStorageJob([88888], ['brand-logos/non_existent.png']);

        // Should complete cleanly without exception
        $job->handle();
        $this->assertTrue(true);
    }

    /**
     * 7. Duplicate cleanup jobs are idempotent.
     */
    public function test_duplicate_cleanup_jobs_are_idempotent(): void
    {
        $brandId = 99999;
        Storage::disk($this->mediaDisk)->put("media/{$brandId}/asset.jpg", 'bytes');

        $job1 = new PurgeUserMediaStorageJob([$brandId]);
        $job1->handle();

        $job2 = new PurgeUserMediaStorageJob([$brandId]);
        $job2->handle();

        Storage::disk($this->mediaDisk)->assertMissing("media/{$brandId}/asset.jpg");
    }

    /**
     * 8. Storage failures trigger retry behavior.
     */
    public function test_job_declares_bounded_retries_and_backoff(): void
    {
        $job = new PurgeUserMediaStorageJob([123]);

        $this->assertSame(3, $job->tries);
        $this->assertSame([15, 60, 300], $job->backoff);
        $this->assertTrue($job->afterCommit);
    }

    /**
     * 9. Account deletion rolls back when queue insertion fails.
     */
    public function test_account_deletion_rolls_back_if_queue_or_db_fails(): void
    {
        $user = User::factory()->create();
        $brand = Brand::create([
            'user_id' => $user->id,
            'name'    => 'Test Rollback Brand',
            'status'  => 'active',
        ]);

        // Mock DB transaction failure
        DB::shouldReceive('transaction')
            ->once()
            ->andThrow(new \RuntimeException('Simulated queue or database failure'));

        try {
            $this->actingAs($user)->delete('/profile', [
                'password' => 'password',
            ]);
        } catch (\RuntimeException $e) {
            $this->assertSame('Simulated queue or database failure', $e->getMessage());
        }

        // Account must NOT be deleted
        $this->assertNotNull($user->fresh());
        $this->assertNotNull($brand->fresh());
    }

    /**
     * 10. Reconciliation defaults to dry-run and generates a candidate manifest.
     */
    public function test_reconciliation_defaults_to_dry_run_without_deletions(): void
    {
        $orphanBrandId = 55555;
        Storage::disk($this->mediaDisk)->put("media/{$orphanBrandId}/old.jpg", 'bytes');

        // Set lastModified to 3 days ago (> 48h)
        $threeDaysAgo = Carbon::now()->subDays(3)->timestamp;
        touch(Storage::disk($this->mediaDisk)->path("media/{$orphanBrandId}/old.jpg"), $threeDaysAgo);

        $exitCode = Artisan::call('media:reconcile-orphans');

        $this->assertSame(0, $exitCode);
        // File must still exist because dry-run never deletes
        Storage::disk($this->mediaDisk)->assertExists("media/{$orphanBrandId}/old.jpg");

        // Verify manifest was generated in storage/app/reconciliation/
        $manifestFiles = Storage::disk('local')->files('reconciliation');
        $this->assertNotEmpty($manifestFiles);
    }

    /**
     * 11. Reconciliation respects the 48-hour age grace period.
     */
    public function test_reconciliation_respects_grace_period(): void
    {
        $orphanBrandId = 44444;
        Storage::disk($this->mediaDisk)->put("media/{$orphanBrandId}/recent.jpg", 'bytes');

        // Set lastModified to 2 hours ago (< 48h)
        $twoHoursAgo = Carbon::now()->subHours(2)->timestamp;
        touch(Storage::disk($this->mediaDisk)->path("media/{$orphanBrandId}/recent.jpg"), $twoHoursAgo);

        Artisan::call('media:reconcile-orphans');

        $manifestFiles = Storage::disk('local')->files('reconciliation');
        $latestManifest = end($manifestFiles);
        $manifestData = json_decode(Storage::disk('local')->get($latestManifest), true);

        // Recent directory must be marked in grace period and NOT added to candidate deletion list
        $candidateBrandIds = array_column($manifestData['candidates'], 'brand_id');
        $this->assertNotContains($orphanBrandId, $candidateBrandIds);
    }

    /**
     * 12. Reconciliation aborts when database is unavailable.
     */
    public function test_reconciliation_aborts_on_database_connection_failure(): void
    {
        DB::shouldReceive('connection->getPdo')
            ->once()
            ->andThrow(new \PDOException('Connection lost'));

        $exitCode = Artisan::call('media:reconcile-orphans');

        $this->assertSame(1, $exitCode);
    }

    /**
     * 13. Destructive execution requires a valid manifest and explicit --force flag.
     */
    public function test_stage_b_destructive_execution_requires_force_flag(): void
    {
        $exitCode = Artisan::call('media:reconcile-orphans', [
            '--manifest' => 'reconciliation/dummy.json',
        ]);

        $this->assertSame(1, $exitCode);
    }

    /**
     * 14. Stage B executes deletions only for verified unexpired candidates.
     */
    public function test_stage_b_executes_deletion_with_valid_manifest_and_force(): void
    {
        $orphanBrandId = 66666;
        $dir = "media/{$orphanBrandId}";
        Storage::disk($this->mediaDisk)->put("{$dir}/file.jpg", 'bytes');

        $manifestPath = 'reconciliation/test_manifest.json';
        $manifestData = [
            'schema_version' => '1.0',
            'generated_at'   => now()->toIso8601String(),
            'candidates'     => [
                [
                    'brand_id'    => $orphanBrandId,
                    'directory'   => $dir,
                    'total_bytes' => 1024,
                ],
            ],
        ];

        Storage::disk('local')->put($manifestPath, json_encode($manifestData));

        $exitCode = Artisan::call('media:reconcile-orphans', [
            '--manifest' => $manifestPath,
            '--force'    => true,
        ]);

        $this->assertSame(0, $exitCode);
        Storage::disk($this->mediaDisk)->assertMissing("{$dir}/file.jpg");

        // Verify audit file created
        $auditFiles = array_filter(
            Storage::disk('local')->files('reconciliation'),
            fn ($f) => str_contains($f, 'audit_')
        );
        $this->assertNotEmpty($auditFiles);
    }

    /**
     * 15. Active and protected brand directories are never deleted during Stage B.
     */
    public function test_active_and_protected_brands_are_immune_during_stage_b(): void
    {
        // 1. Create an active brand
        $user = User::factory()->create();
        $activeBrand = Brand::create([
            'user_id' => $user->id,
            'name'    => 'Active Protected Brand',
            'status'  => 'active',
        ]);

        Storage::disk($this->mediaDisk)->put("media/{$activeBrand->id}/important.jpg", 'bytes');
        Storage::disk($this->mediaDisk)->put('media/30001/protected_standing.jpg', 'bytes');

        // Manifest maliciously or accidentally includes the active brand and protected brand 30001
        $manifestPath = 'reconciliation/test_immunity_manifest.json';
        $manifestData = [
            'schema_version' => '1.0',
            'generated_at'   => now()->toIso8601String(),
            'candidates'     => [
                [
                    'brand_id'    => $activeBrand->id,
                    'directory'   => "media/{$activeBrand->id}",
                    'total_bytes' => 1024,
                ],
                [
                    'brand_id'    => 30001,
                    'directory'   => 'media/30001',
                    'total_bytes' => 1024,
                ],
            ],
        ];

        Storage::disk('local')->put($manifestPath, json_encode($manifestData));

        $exitCode = Artisan::call('media:reconcile-orphans', [
            '--manifest' => $manifestPath,
            '--force'    => true,
        ]);

        $this->assertSame(0, $exitCode);

        // Neither the active brand nor the protected brand 30001 should be deleted!
        Storage::disk($this->mediaDisk)->assertExists("media/{$activeBrand->id}/important.jpg");
        Storage::disk($this->mediaDisk)->assertExists('media/30001/protected_standing.jpg');
    }

    /**
     * 16. Jobs are dispatched with afterCommit enabled.
     */
    public function test_purge_job_enforces_after_commit(): void
    {
        $job = new PurgeUserMediaStorageJob([100]);
        $this->assertTrue($job->afterCommit);
    }

    /**
     * 17. Incomplete storage enumeration aborts reconciliation immediately.
     */
    public function test_incomplete_storage_enumeration_aborts_reconciliation(): void
    {
        Storage::shouldReceive('disk')
            ->with($this->mediaDisk)
            ->andThrow(new \RuntimeException('Supabase S3 connection timeout'));

        $exitCode = Artisan::call('media:reconcile-orphans');

        $this->assertSame(1, $exitCode);
    }
}
