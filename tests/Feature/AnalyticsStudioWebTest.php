<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnalyticsStudioWebTest extends TestCase
{
    use RefreshDatabase;

    public function test_analytics_dashboard_requires_auth(): void
    {
        $response = $this->get('/analytics');
        $response->assertRedirect('/login');
    }

    public function test_analytics_dashboard_renders_for_authenticated_user(): void
    {
        $user = User::factory()->create();
        $response = $this->actingAs($user)->get('/analytics');
        $response->assertStatus(200);
    }

    public function test_analytics_brand_page_authorizes_owner(): void
    {
        $user = User::factory()->create();
        $brand = Brand::factory()->create(['user_id' => $user->id]);
        $response = $this->actingAs($user)->get("/analytics/brands/{$brand->id}");
        $response->assertStatus(200);
    }

    public function test_analytics_brand_page_denies_other_user(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $brand = Brand::factory()->create(['user_id' => $owner->id]);
        $response = $this->actingAs($other)->get("/analytics/brands/{$brand->id}");
        $response->assertStatus(403);
    }

    public function test_analytics_platforms_page_renders(): void
    {
        $user = User::factory()->create();
        $response = $this->actingAs($user)->get('/analytics/platforms');
        $response->assertStatus(200);
    }

    public function test_analytics_content_page_renders(): void
    {
        $user = User::factory()->create();
        $response = $this->actingAs($user)->get('/analytics/content');
        $response->assertStatus(200);
    }

    public function test_analytics_audience_page_renders(): void
    {
        $user = User::factory()->create();
        $response = $this->actingAs($user)->get('/analytics/audience');
        $response->assertStatus(200);
    }

    public function test_analytics_recommendations_page_renders(): void
    {
        $user = User::factory()->create();
        $response = $this->actingAs($user)->get('/analytics/recommendations');
        $response->assertStatus(200);
    }

    public function test_analytics_reports_page_renders(): void
    {
        $user = User::factory()->create();
        $response = $this->actingAs($user)->get('/analytics/reports');
        $response->assertStatus(200);
    }
}
?>