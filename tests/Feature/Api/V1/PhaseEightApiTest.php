<?php

namespace Tests\Feature\Api\V1;

use App\Models\Brand;
use App\Models\BrandAnalytics;
use App\Models\BrandPost;
use App\Models\Campaign;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PhaseEightApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_fetch_brand_summary_dashboard_metrics(): void
    {
        $user = User::factory()->create();
        $brand = Brand::factory()->create([
            'user_id' => $user->id,
            'name' => 'Summit Studio',
        ]);

        BrandAnalytics::create([
            'brand_id' => $brand->id,
            'metric_date' => '2026-11-09',
            'followers' => 12000,
            'reach' => 18000,
            'impressions' => 17000,
            'engagements' => 600,
            'engagement_rate' => 0.035,
        ]);

        BrandPost::create([
            'user_id' => $user->id,
            'brand_id' => $brand->id,
            'provider' => 'internal',
            'platform' => 'linkedin',
            'status' => 'published',
            'content' => 'Published post for the growth sprint.',
            'published_at' => now(),
        ]);

        BrandPost::create([
            'user_id' => $user->id,
            'brand_id' => $brand->id,
            'provider' => 'internal',
            'platform' => 'x',
            'status' => 'scheduled',
            'content' => 'Scheduled thought leadership content.',
            'scheduled_for' => now()->addDay(),
        ]);

        Campaign::create([
            'user_id' => $user->id,
            'brand_id' => $brand->id,
            'name' => 'Q4 Launch',
            'objective' => 'Awareness',
            'status' => 'active',
            'start_date' => '2026-11-01',
            'end_date' => '2026-11-30',
            'timezone' => 'UTC',
        ]);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/v1/brands/'.$brand->id.'/summary');

        $response->assertOk()
            ->assertJsonPath('data.brand_id', $brand->id)
            ->assertJsonPath('data.total_posts', 2)
            ->assertJsonPath('data.published_posts', 1)
            ->assertJsonPath('data.active_campaigns', 1)
            ->assertJsonPath('data.followers', 12000)
            ->assertJsonPath('data.engagement_rate', 0.035);
    }
}
