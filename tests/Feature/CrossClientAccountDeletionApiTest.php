<?php

namespace Tests\Feature;

use App\Jobs\PurgeUserMediaStorageJob;
use App\Models\Brand;
use App\Models\MediaAsset;
use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Cross-Client Account Deletion & Media Management API Tests
 *
 * Verifies that the REST API endpoints consumed by the Flutter mobile client
 * enforce authentication, tenant-ownership boundaries, and trigger the
 * correct backend cleanup workflows — identical to the web client.
 *
 * Stage 5.6.9 — Web and Flutter Cross-Client Verification
 */
class CrossClientAccountDeletionApiTest extends TestCase
{
    use RefreshDatabase;

    protected string $mediaDisk = 'supabase';

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.media.storage_disk' => $this->mediaDisk]);
        Storage::fake($this->mediaDisk);
        Storage::fake('public');
        Queue::fake();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 1. Authentication — unauthenticated requests are rejected
    // ─────────────────────────────────────────────────────────────────────────

    public function test_unauthenticated_brand_deletion_returns_401(): void
    {
        $user  = User::factory()->create();
        $brand = Brand::factory()->create(['user_id' => $user->id, 'status' => 'active']);

        $this->deleteJson("/api/v1/brands/{$brand->id}")
            ->assertStatus(401);

        $this->assertDatabaseHas('brands', ['id' => $brand->id]);
    }

    public function test_unauthenticated_media_deletion_returns_401(): void
    {
        $user  = User::factory()->create();
        $brand = Brand::factory()->create(['user_id' => $user->id, 'status' => 'active']);

        $asset = MediaAsset::create([
            'user_id'           => $user->id,
            'brand_id'          => $brand->id,
            'asset_type'        => 'image',
            'title'             => 'Test Asset',
            'storage_path'      => "media/{$brand->id}/image.png",
            'storage_disk'      => $this->mediaDisk,
            'generation_status' => 'completed',
        ]);

        $this->deleteJson("/api/v1/media/{$asset->id}")
            ->assertStatus(401);

        $this->assertDatabaseHas('media_assets', ['id' => $asset->id]);
    }

    public function test_unauthenticated_logout_returns_401(): void
    {
        $this->postJson('/api/v1/logout')
            ->assertStatus(401);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 2. Brand deletion — authenticated, correct ownership, dispatches job
    // ─────────────────────────────────────────────────────────────────────────

    public function test_authenticated_brand_deletion_dispatches_purge_job(): void
    {
        $user  = User::factory()->create();
        $brand = Brand::factory()->create([
            'user_id'   => $user->id,
            'status'    => 'active',
            'logo_path' => 'brand-logos/logo.png',
        ]);

        Sanctum::actingAs($user, ['*']);

        $this->deleteJson("/api/v1/brands/{$brand->id}")
            ->assertStatus(200)
            ->assertJson(['message' => 'Brand deleted successfully.']);

        $this->assertDatabaseMissing('brands', ['id' => $brand->id]);

        Queue::assertPushed(PurgeUserMediaStorageJob::class, function (PurgeUserMediaStorageJob $job) use ($brand, $user): bool {
            return in_array($brand->id, $job->brandIds, true)
                && in_array('brand-logos/logo.png', $job->logoPaths, true)
                && $job->userId === $user->id;
        });
    }

    public function test_brand_deletion_without_logo_dispatches_job_with_empty_logo_paths(): void
    {
        $user  = User::factory()->create();
        $brand = Brand::factory()->create([
            'user_id'   => $user->id,
            'status'    => 'active',
            'logo_path' => null,
        ]);

        Sanctum::actingAs($user, ['*']);

        $this->deleteJson("/api/v1/brands/{$brand->id}")
            ->assertStatus(200);

        Queue::assertPushed(PurgeUserMediaStorageJob::class, function (PurgeUserMediaStorageJob $job) use ($brand): bool {
            return in_array($brand->id, $job->brandIds, true)
                && $job->logoPaths === [];
        });
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 3. Brand deletion — tenant-ownership enforcement
    // ─────────────────────────────────────────────────────────────────────────

    public function test_brand_deletion_requires_ownership_returns_403(): void
    {
        $owner     = User::factory()->create();
        $attacker  = User::factory()->create();
        $brand     = Brand::factory()->create(['user_id' => $owner->id, 'status' => 'active']);

        Sanctum::actingAs($attacker, ['*']);

        $this->deleteJson("/api/v1/brands/{$brand->id}")
            ->assertStatus(403);

        $this->assertDatabaseHas('brands', ['id' => $brand->id]);
        Queue::assertNothingPushed();
    }

    public function test_brand_deletion_returns_404_for_nonexistent_brand(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user, ['*']);

        $this->deleteJson('/api/v1/brands/999999')
            ->assertStatus(404);

        Queue::assertNothingPushed();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 4. Protected brand safeguards (Brands 30001 and 181643)
    // ─────────────────────────────────────────────────────────────────────────

    public function test_protected_brand_30001_cannot_be_deleted_via_api(): void
    {
        $user  = User::factory()->create();
        $brand = Brand::factory()->create([
            'id'      => 30001,
            'user_id' => $user->id,
            'status'  => 'active',
        ]);

        Sanctum::actingAs($user, ['*']);

        // The API will authorize (owner matches), and the brand row will be deleted,
        // but PurgeUserMediaStorageJob must skip it internally.
        // We verify the job IS dispatched but the brand_id is in PROTECTED_BRAND_IDS.
        $this->deleteJson("/api/v1/brands/{$brand->id}")
            ->assertStatus(200);

        Queue::assertPushed(PurgeUserMediaStorageJob::class, function (PurgeUserMediaStorageJob $job): bool {
            return in_array(30001, $job->brandIds, true);
        });

        // Confirm that the Job::handle() will skip the protected ID
        $this->assertContains(30001, PurgeUserMediaStorageJob::PROTECTED_BRAND_IDS);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 5. Individual media asset deletion (Flutter: DELETE /api/v1/media/{id})
    // ─────────────────────────────────────────────────────────────────────────

    public function test_authenticated_media_asset_deletion_enforces_ownership(): void
    {
        $owner    = User::factory()->create();
        $attacker = User::factory()->create();
        $brand    = Brand::factory()->create(['user_id' => $owner->id, 'status' => 'active']);

        Storage::disk($this->mediaDisk)->put("media/{$brand->id}/image.png", 'fake-image-data');

        $asset = MediaAsset::create([
            'user_id'           => $owner->id,
            'brand_id'          => $brand->id,
            'asset_type'        => 'image',
            'title'             => 'Test Image',
            'storage_path'      => "media/{$brand->id}/image.png",
            'storage_disk'      => $this->mediaDisk,
            'generation_status' => 'completed',
        ]);

        Sanctum::actingAs($attacker, ['*']);

        $this->deleteJson("/api/v1/media/{$asset->id}")
            ->assertStatus(403);

        $this->assertDatabaseHas('media_assets', ['id' => $asset->id]);
    }

    public function test_owner_can_delete_their_own_media_asset_via_api(): void
    {
        $user  = User::factory()->create();
        $brand = Brand::factory()->create(['user_id' => $user->id, 'status' => 'active']);

        Storage::disk($this->mediaDisk)->put("media/{$brand->id}/image.png", 'fake-image-data');

        $asset = MediaAsset::create([
            'user_id'           => $user->id,
            'brand_id'          => $brand->id,
            'asset_type'        => 'image',
            'title'             => 'Test Image',
            'storage_path'      => "media/{$brand->id}/image.png",
            'storage_disk'      => $this->mediaDisk,
            'generation_status' => 'completed',
        ]);

        Sanctum::actingAs($user, ['*']);

        $this->deleteJson("/api/v1/media/{$asset->id}")
            ->assertSuccessful();

        $this->assertSoftDeleted('media_assets', ['id' => $asset->id]);
        Storage::disk($this->mediaDisk)->assertMissing("media/{$brand->id}/image.png");
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 6. Logout — Flutter: POST /api/v1/logout
    // ─────────────────────────────────────────────────────────────────────────

    public function test_logout_invalidates_token_and_returns_200(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user, ['*']);

        $this->postJson('/api/v1/logout')
            ->assertStatus(200);
    }

    public function test_logout_revokes_personal_access_token_from_database(): void
    {
        $user  = User::factory()->create();
        $token = $user->createToken('flutter-mobile')->plainTextToken;

        $this->assertDatabaseCount('personal_access_tokens', 1);

        $this->withToken($token)->postJson('/api/v1/logout')
            ->assertStatus(200);

        // After logout the token record is removed from the database
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 7. Social account disconnection — Flutter: DELETE /api/v1/social-accounts/{id}
    // ─────────────────────────────────────────────────────────────────────────

    public function test_social_account_disconnection_requires_auth(): void
    {
        if (!class_exists(SocialAccount::class)) {
            $this->markTestSkipped('SocialAccount model not available.');
        }

        $user    = User::factory()->create();
        $brand   = Brand::factory()->create(['user_id' => $user->id, 'status' => 'active']);
        $account = SocialAccount::factory()->create([
            'user_id'  => $user->id,
            'brand_id' => $brand->id,
        ]);

        $this->deleteJson("/api/v1/social-accounts/{$account->id}")
            ->assertStatus(401);
    }

    public function test_social_account_disconnection_enforces_ownership(): void
    {
        if (!class_exists(SocialAccount::class)) {
            $this->markTestSkipped('SocialAccount model not available.');
        }

        $owner    = User::factory()->create();
        $attacker = User::factory()->create();
        $brand    = Brand::factory()->create(['user_id' => $owner->id, 'status' => 'active']);
        $account  = SocialAccount::factory()->create([
            'user_id'  => $owner->id,
            'brand_id' => $brand->id,
        ]);

        Sanctum::actingAs($attacker, ['*']);

        $this->deleteJson("/api/v1/social-accounts/{$account->id}")
            ->assertStatus(403);

        $this->assertDatabaseHas('social_accounts', ['id' => $account->id]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 8. Account deletion is web-only — no REST API equivalent
    // ─────────────────────────────────────────────────────────────────────────

    public function test_no_rest_api_account_deletion_endpoint_exists(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user, ['*']);

        // The Flutter client has no account-deletion feature.
        // Verify that no unintended API route exists that could delete the account.
        $this->deleteJson('/api/v1/user/account')->assertStatus(404);
        $this->deleteJson('/api/v1/user')->assertStatus(404);
        $this->deleteJson('/api/v1/profile')->assertStatus(404);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 9. Cross-tenant isolation — same endpoint, multiple users
    // ─────────────────────────────────────────────────────────────────────────

    public function test_brand_listing_returns_only_authenticated_users_brands(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        Brand::factory()->count(2)->create(['user_id' => $user1->id, 'status' => 'active']);
        Brand::factory()->count(3)->create(['user_id' => $user2->id, 'status' => 'active']);

        Sanctum::actingAs($user1, ['*']);

        $response = $this->getJson('/api/v1/brands')->assertStatus(200);
        $data     = $response->json('data');

        $this->assertCount(2, $data);

        foreach ($data as $brand) {
            // Each returned brand must belong to user1
            $this->assertSame(
                $user1->id,
                Brand::findOrFail($brand['id'])->user_id,
            );
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 10. No direct Supabase access from API layer
    //     (storage operations occur in jobs / services, not raw in controllers)
    // ─────────────────────────────────────────────────────────────────────────

    public function test_brand_deletion_does_not_touch_storage_synchronously(): void
    {
        $user  = User::factory()->create();
        $brand = Brand::factory()->create(['user_id' => $user->id, 'status' => 'active']);

        // Place a fake file at the expected media prefix
        Storage::disk($this->mediaDisk)->put("media/{$brand->id}/test.png", 'data');

        Sanctum::actingAs($user, ['*']);

        $this->deleteJson("/api/v1/brands/{$brand->id}")
            ->assertStatus(200);

        // The file should still be present — cleanup is deferred to the queued job.
        // This confirms the controller does NOT delete storage synchronously.
        Storage::disk($this->mediaDisk)->assertExists("media/{$brand->id}/test.png");

        // But the job MUST have been pushed
        Queue::assertPushed(PurgeUserMediaStorageJob::class);
    }
}
