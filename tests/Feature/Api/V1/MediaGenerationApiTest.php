<?php

namespace Tests\Feature\Api\V1;

use App\Jobs\ProcessMediaGenerationJob;
use App\Models\Brand;
use App\Models\MediaAsset;
use App\Models\MediaGenerationJob;
use App\Models\MediaStoryboard;
use App\Models\MediaStoryboardScene;
use App\Models\User;
use App\Services\MediaGenerationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Throwable;

class MediaGenerationApiTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test 1: Image generation requires auth.
     */
    public function test_image_generation_requires_authentication(): void
    {
        $brand = Brand::factory()->create();

        $response = $this->postJson("/api/v1/brands/{$brand->id}/media/images/generate", [
            'objective' => 'social_post',
        ]);

        $response->assertUnauthorized();
    }

    /**
     * Test 2: Image generation enforces brand ownership.
     */
    public function test_image_generation_enforces_brand_ownership(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $brand = Brand::factory()->create(['user_id' => $owner->id]);

        $response = $this->actingAs($stranger, 'sanctum')
            ->postJson("/api/v1/brands/{$brand->id}/media/images/generate", [
                'objective' => 'social_post',
            ]);

        $response->assertForbidden();
    }

    /**
     * Test 3: Image generation creates asset and queued job.
     */
    public function test_image_generation_creates_asset_and_queued_job(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $brand = Brand::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/brands/{$brand->id}/media/images/generate", [
                'objective'    => 'social_post',
                'visual_style' => 'minimalist',
                'platform'     => 'instagram',
                'aspect_ratio' => '1:1',
                'instructions' => 'Clean product backdrop',
                'title'        => 'Minimalist Backdrop',
            ]);

        $response->assertStatus(202);
        $response->assertJsonStructure([
            'message',
            'data' => [
                'job'   => ['id', 'status', 'job_type'],
                'asset' => ['id', 'generation_status', 'asset_type'],
            ],
            'job'   => ['id', 'status'],
            'asset' => ['id', 'generation_status'],
        ]);

        $this->assertDatabaseHas('media_assets', [
            'brand_id'          => $brand->id,
            'user_id'           => $user->id,
            'asset_type'        => 'image',
            'title'             => 'Minimalist Backdrop',
            'platform'          => 'instagram',
            'aspect_ratio'      => '1:1',
            'generation_status' => 'queued',
        ]);

        $this->assertDatabaseHas('media_generation_jobs', [
            'brand_id' => $brand->id,
            'user_id'  => $user->id,
            'job_type' => 'image_generation',
            'status'   => 'queued',
        ]);

        Queue::assertPushed(ProcessMediaGenerationJob::class);
    }

    /**
     * Test 4: Image status returns asset details.
     */
    public function test_image_status_returns_asset_data(): void
    {
        $user = User::factory()->create();
        $brand = Brand::factory()->create(['user_id' => $user->id]);

        $asset = MediaAsset::create([
            'user_id'           => $user->id,
            'brand_id'          => $brand->id,
            'asset_type'        => 'image',
            'title'             => 'Hero Graphic',
            'generation_status' => 'completed',
            'public_url'        => 'https://example.com/storage/media/test.png',
        ]);

        $job = MediaGenerationJob::create([
            'user_id'        => $user->id,
            'brand_id'       => $brand->id,
            'media_asset_id' => $asset->id,
            'job_type'       => 'image_generation',
            'status'         => 'completed',
            'progress'       => 100,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/media/images/{$asset->id}/status");

        $response->assertOk();
        $response->assertJson([
            'status'     => 'completed',
            'public_url' => 'https://example.com/storage/media/test.png',
            'data' => [
                'status'     => 'completed',
                'public_url' => 'https://example.com/storage/media/test.png',
            ],
        ]);
    }

    /**
     * Test 5: Media library is brand-scoped.
     */
    public function test_media_library_is_brand_scoped(): void
    {
        $user = User::factory()->create();
        $brand1 = Brand::factory()->create(['user_id' => $user->id]);
        $brand2 = Brand::factory()->create(['user_id' => $user->id]);

        $asset1 = MediaAsset::create([
            'user_id'           => $user->id,
            'brand_id'          => $brand1->id,
            'asset_type'        => 'image',
            'title'             => 'Asset for Brand 1',
            'generation_status' => 'completed',
        ]);

        $asset2 = MediaAsset::create([
            'user_id'           => $user->id,
            'brand_id'          => $brand2->id,
            'asset_type'        => 'image',
            'title'             => 'Asset for Brand 2',
            'generation_status' => 'completed',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/brands/{$brand1->id}/media");

        $response->assertOk();
        $titles = collect($response->json('data'))->pluck('title')->all();

        $this->assertContains('Asset for Brand 1', $titles);
        $this->assertNotContains('Asset for Brand 2', $titles);
    }

    /**
     * Test 6: Media asset update validates ownership.
     */
    public function test_media_asset_update_requires_ownership(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $brand = Brand::factory()->create(['user_id' => $owner->id]);

        $asset = MediaAsset::create([
            'user_id'           => $owner->id,
            'brand_id'          => $brand->id,
            'asset_type'        => 'image',
            'title'             => 'Original Title',
            'generation_status' => 'completed',
        ]);

        // Unauthorized user attempts update
        $this->actingAs($stranger, 'sanctum')
            ->putJson("/api/v1/media/{$asset->id}", ['title' => 'Tampered Title'])
            ->assertForbidden();

        // Authorized owner updates
        $this->actingAs($owner, 'sanctum')
            ->putJson("/api/v1/media/{$asset->id}", ['title' => 'Updated Title'])
            ->assertOk();

        $this->assertDatabaseHas('media_assets', [
            'id'    => $asset->id,
            'title' => 'Updated Title',
        ]);
    }

    /**
     * Test 7: Media asset delete soft-deletes.
     */
    public function test_media_asset_delete_soft_deletes(): void
    {
        $user = User::factory()->create();
        $brand = Brand::factory()->create(['user_id' => $user->id]);

        $asset = MediaAsset::create([
            'user_id'           => $user->id,
            'brand_id'          => $brand->id,
            'asset_type'        => 'image',
            'title'             => 'Deletable Asset',
            'generation_status' => 'completed',
        ]);

        $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/v1/media/{$asset->id}")
            ->assertOk();

        $this->assertSoftDeleted('media_assets', ['id' => $asset->id]);
    }

    /**
     * Test 8: Storyboard generation creates storyboard and scenes.
     */
    public function test_storyboard_generation_creates_storyboard_and_scenes(): void
    {
        $user = User::factory()->create();
        $brand = Brand::factory()->create(['user_id' => $user->id, 'name' => 'Apex Athletics']);

        Http::fake([
            '*/internal/v1/media/storyboards/generate' => Http::response([
                'title'                       => 'Apex Athletics Launch',
                'concept'                     => 'High-intensity workout motivation',
                'platform'                    => 'tiktok',
                'aspect_ratio'                => '9:16',
                'estimated_duration'          => 30,
                'background_music_suggestion' => 'Upbeat EDM',
                'scenes'                      => [
                    [
                        'scene_number'      => 1,
                        'description'       => 'Athlete lacing shoes at dawn',
                        'visual_suggestion' => 'Close up low-angle shot',
                        'narration_text'    => 'Champions rise before dawn.',
                        'on_screen_text'    => 'Rise & Grind',
                        'duration_seconds'  => 5,
                        'transition'        => 'fade',
                    ],
                    [
                        'scene_number'      => 2,
                        'description'       => 'Sprinting uphill under heavy rain',
                        'visual_suggestion' => 'Tracking action side shot',
                        'narration_text'    => 'Every rep builds greatness.',
                        'on_screen_text'    => 'Unstoppable Momentum',
                        'duration_seconds'  => 7,
                        'transition'        => 'cut',
                    ],
                ],
            ], 200),
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/brands/{$brand->id}/media/storyboards/generate", [
                'concept'      => 'High-intensity workout motivation',
                'platform'     => 'tiktok',
                'aspect_ratio' => '9:16',
                'scene_count'  => 2,
            ]);

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'message',
            'storyboard' => [
                'id',
                'title',
                'concept',
                'platform',
                'scenes',
            ],
        ]);

        $this->assertDatabaseHas('media_storyboards', [
            'brand_id' => $brand->id,
            'user_id'  => $user->id,
            'title'    => 'Apex Athletics Launch',
            'platform' => 'tiktok',
        ]);

        $this->assertDatabaseHas('media_storyboard_scenes', [
            'scene_number'   => 1,
            'narration_text' => 'Champions rise before dawn.',
            'on_screen_text' => 'Rise & Grind',
        ]);

        $this->assertDatabaseHas('media_storyboard_scenes', [
            'scene_number'   => 2,
            'narration_text' => 'Every rep builds greatness.',
            'on_screen_text' => 'Unstoppable Momentum',
        ]);
    }

    /**
     * Test 9: Storyboard is brand-scoped.
     */
    public function test_storyboard_requires_brand_ownership(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $brand = Brand::factory()->create(['user_id' => $owner->id]);

        // Stranger cannot generate storyboard for brand
        $this->actingAs($stranger, 'sanctum')
            ->postJson("/api/v1/brands/{$brand->id}/media/storyboards/generate", [
                'concept' => 'Unauthorized Storyboard',
            ])
            ->assertForbidden();

        $storyboard = MediaStoryboard::create([
            'user_id'  => $owner->id,
            'brand_id' => $brand->id,
            'title'    => 'Owner Storyboard',
        ]);

        // Stranger cannot view owner's storyboard
        $this->actingAs($stranger, 'sanctum')
            ->getJson("/api/v1/media/storyboards/{$storyboard->id}")
            ->assertForbidden();

        // Stranger cannot delete owner's storyboard
        $this->actingAs($stranger, 'sanctum')
            ->deleteJson("/api/v1/media/storyboards/{$storyboard->id}")
            ->assertForbidden();
    }

    /**
     * Test 10: Visual settings get/update.
     */
    public function test_visual_settings_get_and_update(): void
    {
        $user = User::factory()->create();
        $brand = Brand::factory()->create(['user_id' => $user->id]);

        // Initial GET returns defaults
        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/brands/{$brand->id}/visual-settings");

        $response->assertOk();
        $response->assertJson([
            'settings' => [
                'brand_id'        => $brand->id,
                'visual_identity' => 'minimalist',
            ],
        ]);

        // Update settings
        $updateResponse = $this->actingAs($user, 'sanctum')
            ->putJson("/api/v1/brands/{$brand->id}/visual-settings", [
                'visual_identity' => 'dark_cinematic',
                'primary_color'   => '#0f172a',
                'secondary_color' => '#1e293b',
                'accent_color'    => '#38bdf8',
                'primary_font'    => 'Montserrat',
                'secondary_font'  => 'Open Sans',
            ]);

        $updateResponse->assertOk();
        $this->assertDatabaseHas('media_visual_settings', [
            'brand_id'        => $brand->id,
            'visual_identity' => 'dark_cinematic',
            'primary_color'   => '#0f172a',
            'accent_color'    => '#38bdf8',
        ]);
    }

    /**
     * Test 11: Upload validates file type and size.
     */
    public function test_media_upload_validates_file(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $brand = Brand::factory()->create(['user_id' => $user->id]);

        // Invalid file format: PDF
        $invalidFile = UploadedFile::fake()->create('contract.pdf', 200, 'application/pdf');
        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/media/upload', [
                'brand_id' => $brand->id,
                'file'     => $invalidFile,
            ])
            ->assertStatus(422);

        // Valid file format: JPEG image
        $validFile = UploadedFile::fake()->create('campaign_banner.jpg', 150, 'image/jpeg');
        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/media/upload', [
                'brand_id' => $brand->id,
                'file'     => $validFile,
                'title'    => 'Campaign Banner',
                'platform' => 'instagram',
            ]);

        $response->assertCreated();
        $response->assertJsonStructure([
            'message',
            'asset' => ['id', 'file_name', 'file_format', 'asset_type', 'public_url'],
        ]);

        $this->assertDatabaseHas('media_assets', [
            'brand_id'   => $brand->id,
            'user_id'    => $user->id,
            'title'      => 'Campaign Banner',
            'asset_type' => 'image',
        ]);
    }

    /**
     * Test 12: Cross-platform: asset created via API appears in library.
     */
    public function test_cross_platform_asset_in_library(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $brand = Brand::factory()->create(['user_id' => $user->id]);

        $createResponse = $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/brands/{$brand->id}/media/images/generate", [
                'objective' => 'promotional_poster',
                'title'     => 'Cross Platform Asset',
                'platform'  => 'linkedin',
            ]);

        $createResponse->assertStatus(202);
        $assetId = $createResponse->json('asset.id');

        $libraryResponse = $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/brands/{$brand->id}/media");

        $libraryResponse->assertOk();
        $ids = collect($libraryResponse->json('data'))->pluck('id')->all();
        $this->assertContains($assetId, $ids);
    }

    /**
     * Test 13: Job retry enforces ownership and failed status.
     */
    public function test_job_retry_enforces_ownership_and_failed_status(): void
    {
        Queue::fake();

        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $brand = Brand::factory()->create(['user_id' => $owner->id]);

        $asset = MediaAsset::create([
            'user_id'           => $owner->id,
            'brand_id'          => $brand->id,
            'asset_type'        => 'image',
            'generation_status' => 'processing',
        ]);

        $job = MediaGenerationJob::create([
            'user_id'        => $owner->id,
            'brand_id'       => $brand->id,
            'media_asset_id' => $asset->id,
            'job_type'       => 'image_generation',
            'status'         => 'processing',
            'retry_count'    => 0,
            'max_retries'    => 3,
        ]);

        // Stranger cannot retry owner's job
        $this->actingAs($stranger, 'sanctum')
            ->postJson("/api/v1/media/jobs/{$job->id}/retry")
            ->assertForbidden();

        // Non-failed job cannot be retried
        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v1/media/jobs/{$job->id}/retry")
            ->assertStatus(422);

        // Transition job and asset to failed
        $job->update(['status' => 'failed', 'error_message' => 'API Timeout']);
        $asset->update(['generation_status' => 'failed']);

        // Owner retries failed job
        $retryResponse = $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v1/media/jobs/{$job->id}/retry");

        $retryResponse->assertStatus(202);

        $freshJob = $job->fresh();
        $this->assertEquals('queued', $freshJob->status);
        $this->assertEquals(1, $freshJob->retry_count);
        $this->assertNull($freshJob->error_message);
    }

    /**
     * Test 14: Provider configuration_required response (mock Python engine).
     */
    public function test_image_generation_reports_configuration_required_when_no_provider(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $brand = Brand::factory()->create(['user_id' => $user->id]);

        Http::fake([
            '*/internal/v1/media/images/generate' => Http::response([
                'success' => false,
                'status'  => 'configuration_required',
                'message' => 'No image generation provider configured. Set OPENAI_API_KEY, STABILITY_API_KEY, or GEMINI_API_KEY.',
            ], 200),
        ]);

        $service = app(MediaGenerationService::class);
        $job = $service->createImageGenerationJob($user, $brand, [
            'objective' => 'social_post',
            'title'     => 'Unconfigured Provider Test',
        ]);

        try {
            $service->processImageGeneration($job);
            $this->fail('Expected exception when image generation fails due to missing provider');
        } catch (Throwable $e) {
            $this->assertStringContainsString('No image generation provider configured', $e->getMessage());
        }

        $freshJob = $job->fresh();
        $freshAsset = MediaAsset::find($job->media_asset_id);

        $this->assertEquals('failed', $freshJob->status);
        $this->assertStringContainsString('No image generation provider configured', $freshJob->error_message);
        $this->assertEquals('failed', $freshAsset->generation_status);
    }

    /**
     * Test 15: Foreign user cannot access asset.
     */
    public function test_foreign_user_cannot_access_asset(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $brand = Brand::factory()->create(['user_id' => $owner->id]);

        $asset = MediaAsset::create([
            'user_id'           => $owner->id,
            'brand_id'          => $brand->id,
            'asset_type'        => 'image',
            'title'             => 'Private Asset',
            'generation_status' => 'completed',
        ]);

        // Stranger cannot view asset
        $this->actingAs($stranger, 'sanctum')
            ->getJson("/api/v1/media/{$asset->id}")
            ->assertForbidden();

        // Stranger cannot update asset
        $this->actingAs($stranger, 'sanctum')
            ->putJson("/api/v1/media/{$asset->id}", ['title' => 'Stolen'])
            ->assertForbidden();

        // Stranger cannot delete asset
        $this->actingAs($stranger, 'sanctum')
            ->deleteJson("/api/v1/media/{$asset->id}")
            ->assertForbidden();
    }

    /**
     * Test 16: Video generation creates asset and queued job.
     */
    public function test_video_generation_creates_asset_and_queued_job(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $brand = Brand::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/brands/{$brand->id}/media/videos/generate", [
                'objective'    => 'product_ad',
                'visual_style' => 'cinematic',
                'platform'     => 'tiktok',
                'aspect_ratio' => '9:16',
                'duration'     => 15,
                'title'        => 'TikTok Promo Clip',
            ]);

        $response->assertStatus(202);
        $this->assertDatabaseHas('media_assets', [
            'brand_id'          => $brand->id,
            'user_id'           => $user->id,
            'asset_type'        => 'video',
            'title'             => 'TikTok Promo Clip',
            'generation_status' => 'queued',
        ]);

        $this->assertDatabaseHas('media_generation_jobs', [
            'brand_id' => $brand->id,
            'user_id'  => $user->id,
            'job_type' => 'video_generation',
            'status'   => 'queued',
        ]);

        Queue::assertPushed(ProcessMediaGenerationJob::class);
    }

    /**
     * Test 17: Storyboard scene update requires ownership.
     */
    public function test_storyboard_scene_update_requires_ownership(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $brand = Brand::factory()->create(['user_id' => $owner->id]);

        $storyboard = MediaStoryboard::create([
            'user_id'  => $owner->id,
            'brand_id' => $brand->id,
            'title'    => 'Scene Storyboard',
        ]);

        $scene = MediaStoryboardScene::create([
            'storyboard_id'     => $storyboard->id,
            'scene_number'      => 1,
            'description'       => 'Opening scene',
            'visual_suggestion' => 'Wide angle view',
            'duration_seconds'  => 5,
        ]);

        // Stranger cannot update scene
        $this->actingAs($stranger, 'sanctum')
            ->putJson("/api/v1/media/storyboard-scenes/{$scene->id}", [
                'narration_text' => 'Unauthorized script change',
            ])
            ->assertForbidden();

        // Owner can update scene
        $this->actingAs($owner, 'sanctum')
            ->putJson("/api/v1/media/storyboard-scenes/{$scene->id}", [
                'narration_text'   => 'Authorized narrative voiceover',
                'duration_seconds' => 7,
            ])
            ->assertOk();

        $this->assertDatabaseHas('media_storyboard_scenes', [
            'id'               => $scene->id,
            'narration_text'   => 'Authorized narrative voiceover',
            'duration_seconds' => 7,
        ]);
    }

    /**
     * Test 18: Poster and graphic composition creates a real persisted asset with brand styling.
     */
    public function test_poster_and_quote_composition_creates_real_asset(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $stranger = User::factory()->create();
        $brand = Brand::factory()->create(['user_id' => $user->id, 'name' => 'Bruce Lee Discipline']);

        Http::fake([
            '*/internal/v1/media/compose' => Http::response([
                'success'      => true,
                'provider'     => 'internal_composer',
                'model'        => 'svg-vector-engine-2.0',
                'template'     => 'quote_card',
                'image_format' => 'svg',
                'mime_type'    => 'image/svg+xml',
                'width'        => 1080,
                'height'       => 1080,
                'prompt_used'  => 'Composed quote card for Bruce Lee Discipline',
                'image_base64' => base64_encode('<svg><text>Knowing is not enough; we must apply.</text></svg>'),
            ], 200),
        ]);

        // Stranger forbidden
        $this->actingAs($stranger, 'sanctum')
            ->postJson("/api/v1/brands/{$brand->id}/media/compose", [
                'template' => 'quote_card',
                'quote'    => 'Unauthorized',
            ])
            ->assertForbidden();

        // Owner succeeds
        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/brands/{$brand->id}/media/compose", [
                'template'     => 'quote_card',
                'quote'        => 'Knowing is not enough; we must apply.',
                'author'       => 'Bruce Lee',
                'aspect_ratio' => '1:1',
                'headline'     => 'Execution Over Theory',
            ]);

        $response->assertCreated();
        $response->assertJsonPath('asset.generation_status', 'completed');
        $response->assertJsonPath('asset.file_format', 'svg');

        $assetId = $response->json('asset.id');
        $asset = MediaAsset::findOrFail($assetId);

        $this->assertEquals('completed', $asset->generation_status);
        $this->assertEquals('internal_composer', $asset->provider);
        $this->assertTrue(Storage::disk('local')->exists($asset->storage_path));
    }
}
