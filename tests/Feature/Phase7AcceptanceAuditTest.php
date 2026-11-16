<?php

namespace Tests\Feature;

use App\Jobs\ProcessMediaGenerationJob;
use App\Models\Brand;
use App\Models\BrandContentDraft;
use App\Models\MediaAsset;
use App\Models\MediaGenerationJob;
use App\Models\MediaStoryboard;
use App\Models\MediaStoryboardScene;
use App\Models\MediaVisualSetting;
use App\Models\User;
use App\Services\MediaVisualSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class Phase7AcceptanceAuditTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Requirement 1: Brand visual intelligence returns structured profile.
     */
    public function test_brand_visual_intelligence_returns_structured_profile(): void
    {
        $user = User::factory()->create();
        $brand = Brand::factory()->for($user)->create([
            'name'        => 'Neon Apex',
            'industry'    => 'Fitness & Athletics',
            'niche'       => 'High Intensity Training',
            'category'    => 'fitness',
            'description' => 'Unrelenting physical conditioning.',
        ]);

        $service = app(MediaVisualSettingsService::class);
        $settings = $service->getSettings($brand);

        $this->assertInstanceOf(MediaVisualSetting::class, $settings);
        $this->assertEquals($brand->id, $settings->brand_id);
        $this->assertNotEmpty($settings->visual_identity);
        $this->assertNotEmpty($settings->primary_color);
        $this->assertNotEmpty($settings->secondary_color);
        $this->assertNotEmpty($settings->accent_color);
        $this->assertNotEmpty($settings->primary_font);
        $this->assertNotEmpty($settings->secondary_font);
        $this->assertIsArray($settings->preferred_platforms);
        $this->assertIsArray($settings->preferred_aspect_ratios);

        // Verify API access returns the structured visual profile
        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/brands/{$brand->id}/visual-settings");

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                'id',
                'brand_id',
                'visual_identity',
                'primary_color',
                'secondary_color',
                'accent_color',
                'primary_font',
                'secondary_font',
                'preferred_platforms',
                'preferred_aspect_ratios',
            ],
            'settings',
        ]);
    }

    /**
     * Requirement 2: Image generation endpoint creates asset record and queued job.
     */
    public function test_image_generation_endpoint_creates_asset_record_and_queued_job(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $brand = Brand::factory()->for($user)->create([
            'name' => 'Zenith Tech',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/brands/{$brand->id}/media/images/generate", [
                'objective'    => 'social_post',
                'visual_style' => 'minimalist',
                'platform'     => 'linkedin',
                'aspect_ratio' => '1:1',
                'title'        => 'Zenith Core Launch',
                'instructions' => 'Sleek dark gradient with glowing neon circuits.',
            ]);

        $response->assertStatus(202);
        $response->assertJsonStructure([
            'message',
            'job'   => ['id', 'status', 'job_type'],
            'asset' => ['id', 'generation_status', 'asset_type'],
        ]);

        $this->assertDatabaseHas('media_assets', [
            'brand_id'          => $brand->id,
            'user_id'           => $user->id,
            'asset_type'        => 'image',
            'title'             => 'Zenith Core Launch',
            'generation_status' => 'queued',
            'platform'          => 'linkedin',
            'aspect_ratio'      => '1:1',
        ]);

        $this->assertDatabaseHas('media_generation_jobs', [
            'brand_id' => $brand->id,
            'user_id'  => $user->id,
            'job_type' => 'image_generation',
            'status'   => 'queued',
            'progress' => 0,
        ]);

        Queue::assertPushed(ProcessMediaGenerationJob::class);
    }

    /**
     * Requirement 3: Storyboard generation creates storyboard and scene records.
     */
    public function test_storyboard_generation_creates_storyboard_and_scene_records(): void
    {
        $user = User::factory()->create();
        $brand = Brand::factory()->for($user)->create([
            'name' => 'Solace Coffee',
        ]);

        Http::fake([
            '*/internal/v1/media/storyboards/generate' => Http::response([
                'title'                       => 'The Morning Ritual',
                'concept'                     => 'Artisanal pour-over sequence',
                'platform'                    => 'instagram',
                'aspect_ratio'                => '9:16',
                'estimated_duration'          => 20,
                'background_music_suggestion' => 'Lo-fi chill beats',
                'scenes'                      => [
                    [
                        'scene_number'      => 1,
                        'description'       => 'Slow motion coffee beans falling into grinder',
                        'visual_suggestion' => 'Macro close-up, warm ambient light',
                        'narration_text'    => 'Every morning begins with intention.',
                        'on_screen_text'    => 'Crafted with Care',
                        'duration_seconds'  => 5,
                        'transition'        => 'fade',
                    ],
                    [
                        'scene_number'      => 2,
                        'description'       => 'Steaming water poured over fresh grounds',
                        'visual_suggestion' => 'Top-down bloom shot',
                        'narration_text'    => 'Rich aroma that awakens the mind.',
                        'on_screen_text'    => 'Single Origin Colombian',
                        'duration_seconds'  => 5,
                        'transition'        => 'cut',
                    ],
                    [
                        'scene_number'      => 3,
                        'description'       => 'Cup served on minimalist wooden table',
                        'visual_suggestion' => 'Eye-level wide cinematic framing',
                        'narration_text'    => 'Find your stillness.',
                        'on_screen_text'    => 'Solace Coffee Co.',
                        'duration_seconds'  => 10,
                        'transition'        => 'fade',
                    ],
                ],
            ], 200),
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/brands/{$brand->id}/media/storyboards/generate", [
                'concept'      => 'Artisanal pour-over sequence',
                'platform'     => 'instagram',
                'aspect_ratio' => '9:16',
                'scene_count'  => 3,
            ]);

        $response->assertStatus(201);
        $storyboardId = $response->json('storyboard.id');

        $storyboard = MediaStoryboard::with('scenes')->findOrFail($storyboardId);
        $this->assertEquals('The Morning Ritual', $storyboard->title);
        $this->assertEquals('Solace Coffee', $storyboard->brand->name);
        $this->assertCount(3, $storyboard->scenes);

        $this->assertEquals(1, $storyboard->scenes[0]->scene_number);
        $this->assertEquals('The Morning Ritual', $storyboard->title);
        $this->assertEquals('Crafted with Care', $storyboard->scenes[0]->on_screen_text);
        $this->assertEquals('Find your stillness.', $storyboard->scenes[2]->narration_text);
    }

    /**
     * Requirement 4: Media library is owner-scoped.
     */
    public function test_media_library_is_owner_scoped(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $brandA = Brand::factory()->for($userA)->create(['name' => 'Brand Alpha']);
        $brandB = Brand::factory()->for($userB)->create(['name' => 'Brand Beta']);

        $assetA = MediaAsset::create([
            'user_id'           => $userA->id,
            'brand_id'          => $brandA->id,
            'asset_type'        => 'image',
            'title'             => 'Alpha Exclusive Graphic',
            'generation_status' => 'completed',
        ]);

        $assetB = MediaAsset::create([
            'user_id'           => $userB->id,
            'brand_id'          => $brandB->id,
            'asset_type'        => 'image',
            'title'             => 'Beta Secret Visual',
            'generation_status' => 'completed',
        ]);

        // User A views Brand A library -> sees Asset A, does NOT see Asset B
        $responseA = $this->actingAs($userA, 'sanctum')
            ->getJson("/api/v1/brands/{$brandA->id}/media");

        $responseA->assertOk();
        $titlesA = collect($responseA->json('data'))->pluck('title')->all();
        $this->assertContains('Alpha Exclusive Graphic', $titlesA);
        $this->assertNotContains('Beta Secret Visual', $titlesA);

        // User A attempts to view Brand B library -> 403 Forbidden
        $this->actingAs($userA, 'sanctum')
            ->getJson("/api/v1/brands/{$brandB->id}/media")
            ->assertForbidden();

        // User A attempts to view Asset B directly -> 403 Forbidden
        $this->actingAs($userA, 'sanctum')
            ->getJson("/api/v1/media/{$assetB->id}")
            ->assertForbidden();
    }

    /**
     * Requirement 5: Visual settings save and persist.
     */
    public function test_visual_settings_save_and_persist(): void
    {
        $user = User::factory()->create();
        $brand = Brand::factory()->for($user)->create();

        $payload = [
            'visual_identity'                  => 'corporate',
            'primary_color'                    => '#003366',
            'secondary_color'                  => '#336699',
            'accent_color'                     => '#FF6600',
            'primary_font'                     => 'Helvetica Neue',
            'secondary_font'                   => 'Arial',
            'logo_placement'                   => 'bottom_right',
            'preferred_platforms'              => ['linkedin', 'x'],
            'preferred_aspect_ratios'          => ['16:9', '1:1'],
            'image_generation_style_prompt'    => 'Clean professional boardroom atmosphere',
            'image_generation_negative_prompt' => 'No clutter, no low-resolution elements',
        ];

        $updateResponse = $this->actingAs($user, 'sanctum')
            ->putJson("/api/v1/brands/{$brand->id}/visual-settings", $payload);

        $updateResponse->assertOk();

        // Fetch again to verify persistence
        $getResponse = $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/brands/{$brand->id}/visual-settings");

        $getResponse->assertOk();
        $data = $getResponse->json('settings');

        $this->assertEquals('corporate', $data['visual_identity']);
        $this->assertEquals('#003366', $data['primary_color']);
        $this->assertEquals('#FF6600', $data['accent_color']);
        $this->assertEquals('Helvetica Neue', $data['primary_font']);
        $this->assertEquals('bottom_right', $data['logo_placement']);
        $this->assertEquals(['linkedin', 'x'], $data['preferred_platforms']);
        $this->assertEquals(['16:9', '1:1'], $data['preferred_aspect_ratios']);
    }

    /**
     * Requirement 6: Asset deletion soft-deletes and does not affect content drafts.
     */
    public function test_asset_deletion_soft_deletes_and_does_not_affect_content_drafts(): void
    {
        $user = User::factory()->create();
        $brand = Brand::factory()->for($user)->create();

        $draft = BrandContentDraft::create([
            'user_id'   => $user->id,
            'brand_id'  => $brand->id,
            'title'     => 'Crucial Campaign Draft',
            'platform'  => 'instagram',
            'objective' => 'engagement',
            'prompt'    => 'Campaign draft prompt for testing',
            'body'      => 'Sample content body for testing Phase 7 asset link.',
            'status'    => 'draft',
        ]);

        $asset = MediaAsset::create([
            'user_id'           => $user->id,
            'brand_id'          => $brand->id,
            'content_draft_id'  => $draft->id,
            'asset_type'        => 'image',
            'title'             => 'Attached Hero Image',
            'generation_status' => 'completed',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/v1/media/{$asset->id}");

        $response->assertOk();

        // Assert asset is soft deleted
        $this->assertSoftDeleted('media_assets', ['id' => $asset->id]);

        // Assert the linked draft is completely unaffected
        $this->assertDatabaseHas('brand_content_drafts', [
            'id'    => $draft->id,
            'title' => 'Crucial Campaign Draft',
        ]);
        $this->assertNotNull(BrandContentDraft::find($draft->id));
    }

    /**
     * Requirement 7: Upload validation rejects wrong types.
     */
    public function test_upload_validation_rejects_wrong_types(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $brand = Brand::factory()->for($user)->create();

        $invalidExtensions = [
            ['filename' => 'malicious.exe', 'mime' => 'application/x-msdownload'],
            ['filename' => 'script.js', 'mime' => 'application/javascript'],
            ['filename' => 'document.pdf', 'mime' => 'application/pdf'],
            ['filename' => 'markup.html', 'mime' => 'text/html'],
        ];

        foreach ($invalidExtensions as $item) {
            $file = UploadedFile::fake()->create($item['filename'], 50, $item['mime']);

            $response = $this->actingAs($user, 'sanctum')
                ->postJson('/api/v1/media/upload', [
                    'brand_id' => $brand->id,
                    'file'     => $file,
                ]);

            $response->assertStatus(422);
        }

        // Valid image upload succeeds
        $validImage = UploadedFile::fake()->create('acceptable.jpg', 200, 'image/jpeg');
        $validResponse = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/media/upload', [
                'brand_id' => $brand->id,
                'file'     => $validImage,
                'title'    => 'Valid Uploaded Image',
            ]);

        $validResponse->assertCreated();
        $this->assertDatabaseHas('media_assets', [
            'brand_id'   => $brand->id,
            'title'      => 'Valid Uploaded Image',
            'asset_type' => 'image',
        ]);
    }

    /**
     * Requirement 8: Brand ownership enforced on all media routes.
     */
    public function test_brand_ownership_enforced_on_all_media_routes(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $brand = Brand::factory()->for($owner)->create();

        $asset = MediaAsset::create([
            'user_id'           => $owner->id,
            'brand_id'          => $brand->id,
            'asset_type'        => 'image',
            'title'             => 'Protected Asset',
            'generation_status' => 'completed',
        ]);

        $job = MediaGenerationJob::create([
            'user_id'        => $owner->id,
            'brand_id'       => $brand->id,
            'media_asset_id' => $asset->id,
            'job_type'       => 'image_generation',
            'status'         => 'failed',
            'retry_count'    => 0,
            'max_retries'    => 3,
        ]);

        $storyboard = MediaStoryboard::create([
            'user_id'  => $owner->id,
            'brand_id' => $brand->id,
            'title'    => 'Protected Storyboard',
        ]);

        $scene = MediaStoryboardScene::create([
            'storyboard_id'     => $storyboard->id,
            'scene_number'      => 1,
            'description'       => 'Protected Scene',
            'duration_seconds'  => 5,
        ]);

        // Foreign user blocked on brand routes
        $this->actingAs($stranger, 'sanctum')
            ->postJson("/api/v1/brands/{$brand->id}/media/images/generate", ['objective' => 'social_post'])
            ->assertForbidden();

        $this->actingAs($stranger, 'sanctum')
            ->postJson("/api/v1/brands/{$brand->id}/media/videos/generate", ['objective' => 'product_ad'])
            ->assertForbidden();

        $this->actingAs($stranger, 'sanctum')
            ->postJson("/api/v1/brands/{$brand->id}/media/storyboards/generate", ['concept' => 'Video'])
            ->assertForbidden();

        $this->actingAs($stranger, 'sanctum')
            ->getJson("/api/v1/brands/{$brand->id}/media")
            ->assertForbidden();

        $this->actingAs($stranger, 'sanctum')
            ->getJson("/api/v1/brands/{$brand->id}/visual-settings")
            ->assertForbidden();

        $this->actingAs($stranger, 'sanctum')
            ->putJson("/api/v1/brands/{$brand->id}/visual-settings", ['primary_color' => '#fff'])
            ->assertForbidden();

        // Foreign user blocked on individual media asset routes
        $this->actingAs($stranger, 'sanctum')
            ->getJson("/api/v1/media/{$asset->id}")
            ->assertForbidden();

        $this->actingAs($stranger, 'sanctum')
            ->putJson("/api/v1/media/{$asset->id}", ['title' => 'Injected'])
            ->assertForbidden();

        $this->actingAs($stranger, 'sanctum')
            ->deleteJson("/api/v1/media/{$asset->id}")
            ->assertForbidden();

        // Foreign user blocked on storyboard and scene routes
        $this->actingAs($stranger, 'sanctum')
            ->getJson("/api/v1/media/storyboards/{$storyboard->id}")
            ->assertForbidden();

        $this->actingAs($stranger, 'sanctum')
            ->putJson("/api/v1/media/storyboards/{$storyboard->id}", ['title' => 'Injected'])
            ->assertForbidden();

        $this->actingAs($stranger, 'sanctum')
            ->deleteJson("/api/v1/media/storyboards/{$storyboard->id}")
            ->assertForbidden();

        $this->actingAs($stranger, 'sanctum')
            ->putJson("/api/v1/media/storyboard-scenes/{$scene->id}", ['narration_text' => 'Injected'])
            ->assertForbidden();

        // Foreign user blocked on job retry
        $this->actingAs($stranger, 'sanctum')
            ->postJson("/api/v1/media/jobs/{$job->id}/retry")
            ->assertForbidden();
    }

    /**
     * Requirement 9: Cross-platform sync: asset created via POST appears in library GET.
     */
    public function test_cross_platform_sync_asset_created_via_post_appears_in_library_get(): void
    {
        Queue::fake();
        Storage::fake('local');

        $user = User::factory()->create();
        $brand = Brand::factory()->for($user)->create([
            'name' => 'SyncBrand Global',
        ]);

        // Step 1: Generate asset via POST
        $createImageResponse = $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/brands/{$brand->id}/media/images/generate", [
                'objective' => 'social_post',
                'title'     => 'Generated Synced Asset',
                'platform'  => 'instagram',
            ]);

        $createImageResponse->assertStatus(202);
        $genAssetId = $createImageResponse->json('asset.id');

        // Step 2: Upload asset via POST
        $uploadFile = UploadedFile::fake()->create('uploaded_asset.jpg', 120, 'image/jpeg');
        $uploadResponse = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/media/upload', [
                'brand_id' => $brand->id,
                'file'     => $uploadFile,
                'title'    => 'Uploaded Synced Asset',
                'platform' => 'linkedin',
            ]);

        $uploadResponse->assertCreated();
        $uploadedAssetId = $uploadResponse->json('asset.id');

        // Step 3: Fetch library and verify both assets are visible and synchronised
        $libraryResponse = $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/brands/{$brand->id}/media");

        $libraryResponse->assertOk();
        $items = collect($libraryResponse->json('data'));

        $this->assertTrue($items->contains('id', $genAssetId));
        $this->assertTrue($items->contains('id', $uploadedAssetId));

        // Filter library by platform instagram
        $filterInstaResponse = $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/brands/{$brand->id}/media?platform=instagram");

        $filterInstaResponse->assertOk();
        $instaItems = collect($filterInstaResponse->json('data'));
        $this->assertTrue($instaItems->contains('id', $genAssetId));
        $this->assertFalse($instaItems->contains('id', $uploadedAssetId));
    }
}
