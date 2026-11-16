<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\MediaAsset;
use App\Models\MediaStoryboard;
use App\Models\MediaVisualSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MediaStudioWebTest extends TestCase
{
    use RefreshDatabase;

    public function test_media_studio_routes_require_authentication(): void
    {
        $this->get(route('media.dashboard'))->assertRedirect(route('login'));
        $this->get(route('media.generator'))->assertRedirect(route('login'));
        $this->get(route('media.library'))->assertRedirect(route('login'));
        $this->get(route('media.video-studio'))->assertRedirect(route('login'));
    }

    public function test_media_dashboard_renders_for_authenticated_user(): void
    {
        $user = User::factory()->create();
        $brand = Brand::factory()->for($user)->create(['name' => 'Nova Horizon', 'status' => 'active']);

        MediaAsset::create([
            'user_id'           => $user->id,
            'brand_id'          => $brand->id,
            'asset_type'        => 'image',
            'title'             => 'Nova Product Showcase',
            'generation_status' => 'completed',
            'aspect_ratio'      => '1:1',
        ]);

        $response = $this->actingAs($user)->get(route('media.dashboard'));

        $response->assertOk()
            ->assertSee('Media Studio')
            ->assertSee('AI-powered visual content generation')
            ->assertSee('Nova Horizon')
            ->assertSee('Nova Product Showcase');
    }

    public function test_media_generator_renders_with_active_brands(): void
    {
        $user = User::factory()->create();
        $brand = Brand::factory()->for($user)->create(['name' => 'Aether Studios', 'status' => 'active']);

        $response = $this->actingAs($user)->get(route('media.generator'));

        $response->assertOk()
            ->assertSee('AI Image Generator')
            ->assertSee('Aether Studios')
            ->assertSee('Generation Settings');
    }

    public function test_media_library_renders_and_supports_filters(): void
    {
        $user = User::factory()->create();
        $brand = Brand::factory()->for($user)->create(['name' => 'Solaris Brand', 'status' => 'active']);

        $asset = MediaAsset::create([
            'user_id'           => $user->id,
            'brand_id'          => $brand->id,
            'asset_type'        => 'image',
            'title'             => 'Solaris Banner 2026',
            'generation_status' => 'completed',
            'platform'          => 'instagram',
            'aspect_ratio'      => '1:1',
        ]);

        $response = $this->actingAs($user)->get(route('media.library', ['search' => 'Solaris Banner']));

        $response->assertOk()
            ->assertSee('Media Library')
            ->assertSee('Solaris Banner 2026');
    }

    public function test_video_studio_renders_for_user(): void
    {
        $user = User::factory()->create();
        Brand::factory()->for($user)->create(['name' => 'Cinema Brand', 'status' => 'active']);

        $response = $this->actingAs($user)->get(route('media.video-studio'));

        $response->assertOk()
            ->assertSee('Video Studio')
            ->assertSee('Storyboard Generator')
            ->assertSee('Video Generation');
    }

    public function test_visual_settings_requires_brand_ownership(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $brand = Brand::factory()->for($owner)->create(['name' => 'Exclusive Brand', 'status' => 'active']);

        // Unauthenticated
        $this->get(route('brands.visual-settings', $brand))->assertRedirect(route('login'));

        // Stranger forbidden
        $this->actingAs($stranger)->get(route('brands.visual-settings', $brand))->assertForbidden();

        // Owner OK
        $response = $this->actingAs($owner)->get(route('brands.visual-settings', $brand));
        $response->assertOk()
            ->assertSee('Brand Visual Settings')
            ->assertSee('Exclusive Brand');
    }

    public function test_visual_settings_can_be_saved(): void
    {
        $owner = User::factory()->create();
        $brand = Brand::factory()->for($owner)->create(['name' => 'Design Brand', 'status' => 'active']);

        $payload = [
            'visual_identity'               => 'luxury',
            'primary_color'                 => '#ff5500',
            'secondary_color'               => '#112233',
            'accent_color'                  => '#00ffcc',
            'primary_font'                  => 'Poppins',
            'logo_placement'                => 'top_right',
            'image_generation_style_prompt' => 'Ultra high end luxury minimalist aesthetic',
        ];

        $response = $this->actingAs($owner)->post(route('brands.visual-settings.save', $brand), $payload);

        $response->assertRedirect(route('brands.visual-settings', $brand));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('media_visual_settings', [
            'brand_id'        => $brand->id,
            'visual_identity' => 'luxury',
            'primary_color'   => '#ff5500',
            'primary_font'    => 'Poppins',
        ]);
    }
}
