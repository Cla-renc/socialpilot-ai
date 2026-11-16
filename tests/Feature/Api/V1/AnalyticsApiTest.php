<?php

namespace Tests\Feature\Api\V1;

use App\Jobs\Analytics\CollectAccountAnalyticsJob;
use App\Jobs\Analytics\GenerateAnalyticsReportJob;
use App\Jobs\Analytics\GenerateOptimizationRecommendationsJob;
use App\Models\AiOptimizationRecommendation;
use App\Models\AnalyticsCollectionLog;
use App\Models\AnalyticsReport;
use App\Models\AnalyticsSnapshot;
use App\Models\AudienceInsight;
use App\Models\Brand;
use App\Models\PostAnalytic;
use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AnalyticsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_requests_are_rejected(): void
    {
        $this->getJson('/api/v1/analytics/overview')->assertUnauthorized();
        $this->getJson('/api/v1/analytics/summary')->assertUnauthorized();
        $this->getJson('/api/v1/analytics/reports')->assertUnauthorized();
    }

    public function test_authenticated_user_can_view_overview_and_summary(): void
    {
        $user = User::factory()->create();
        $brand = Brand::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/v1/analytics/overview');
        $response->assertOk()
            ->assertJsonStructure([
                'data',
                'period',
                'brand_count',
            ]);

        $summary = $this->actingAs($user, 'sanctum')->getJson('/api/v1/analytics/summary?brand_id=' . $brand->id);
        $summary->assertOk()
            ->assertJsonStructure(['data']);
    }

    public function test_user_cannot_access_unowned_brand_analytics(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $unownedBrand = Brand::factory()->create(['user_id' => $user2->id]);

        $response = $this->actingAs($user1, 'sanctum')->getJson('/api/v1/analytics/brands/' . $unownedBrand->id);
        $response->assertForbidden();

        $comp = $this->actingAs($user1, 'sanctum')->getJson('/api/v1/analytics/brands/' . $unownedBrand->id . '/comparison');
        $comp->assertForbidden();
    }

    public function test_user_can_view_brand_analytics_and_comparison(): void
    {
        $user = User::factory()->create();
        $brand = Brand::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/v1/analytics/brands/' . $brand->id);
        $response->assertOk()
            ->assertJsonStructure(['data', 'brand']);

        $comp = $this->actingAs($user, 'sanctum')->getJson('/api/v1/analytics/brands/' . $brand->id . '/comparison');
        $comp->assertOk()
            ->assertJsonStructure(['data']);
    }

    public function test_user_can_view_platform_analytics(): void
    {
        $user = User::factory()->create();
        $brand = Brand::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/v1/analytics/platforms?platform=instagram&brand_id=' . $brand->id);
        $response->assertOk()
            ->assertJsonStructure(['data']);
    }

    public function test_user_can_view_account_analytics(): void
    {
        $user = User::factory()->create();
        $brand = Brand::factory()->create(['user_id' => $user->id]);
        $account = SocialAccount::create([
            'user_id'             => $user->id,
            'brand_id'            => $brand->id,
            'provider'            => 'instagram',
            'provider_account_id' => 'ig_123',
            'display_name'        => 'My IG Account',
            'status'              => 'connected',
        ]);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/v1/analytics/accounts/' . $account->id);
        $response->assertOk()
            ->assertJsonStructure(['data', 'account']);
    }

    public function test_user_can_trigger_collection(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $brand = Brand::factory()->create(['user_id' => $user->id]);
        $account = SocialAccount::create([
            'user_id'             => $user->id,
            'brand_id'            => $brand->id,
            'provider'            => 'facebook',
            'provider_account_id' => 'fb_12345',
            'display_name'        => 'My FB Page',
            'status'              => 'connected',
        ]);

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/v1/analytics/collect', [
            'social_account_id' => $account->id,
        ]);

        $response->assertStatus(202)
            ->assertJsonPath('social_account_id', $account->id);

        Queue::assertPushed(CollectAccountAnalyticsJob::class);
    }

    public function test_user_can_view_collection_status(): void
    {
        $user = User::factory()->create();
        $brand = Brand::factory()->create(['user_id' => $user->id]);
        $account = SocialAccount::create([
            'user_id'             => $user->id,
            'brand_id'            => $brand->id,
            'provider'            => 'facebook',
            'provider_account_id' => 'fb_12345',
            'display_name'        => 'My FB Page',
            'status'              => 'connected',
        ]);

        AnalyticsCollectionLog::create([
            'brand_id'          => $brand->id,
            'social_account_id' => $account->id,
            'platform'          => 'facebook',
            'collection_type'   => 'full',
            'status'            => 'success',
            'started_at'        => now(),
            'completed_at'      => now(),
        ]);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/v1/analytics/collection-status?social_account_id=' . $account->id);
        $response->assertOk()
            ->assertJsonStructure(['data']);
    }

    public function test_user_can_view_posts_and_top_performing(): void
    {
        $user = User::factory()->create();
        $brand = Brand::factory()->create(['user_id' => $user->id]);
        $account = SocialAccount::create([
            'user_id'             => $user->id,
            'brand_id'            => $brand->id,
            'provider'            => 'facebook',
            'provider_account_id' => 'fb_12345',
            'display_name'        => 'My FB Page',
            'status'              => 'connected',
        ]);

        PostAnalytic::create([
            'brand_id'          => $brand->id,
            'social_account_id' => $account->id,
            'platform'          => 'facebook',
            'external_post_id'  => 'post_999',
            'metric_name'       => 'engagement_rate',
            'metric_value'      => 4.5,
            'reporting_period'  => 'lifetime',
            'collected_at'      => now(),
        ]);

        $postsResponse = $this->actingAs($user, 'sanctum')->getJson('/api/v1/analytics/posts');
        $postsResponse->assertOk();

        $showResponse = $this->actingAs($user, 'sanctum')->getJson('/api/v1/analytics/posts/post_999');
        $showResponse->assertOk()
            ->assertJsonStructure(['data']);

        $topResponse = $this->actingAs($user, 'sanctum')->getJson('/api/v1/analytics/top-performing');
        $topResponse->assertOk()
            ->assertJsonStructure(['data']);

        $underResponse = $this->actingAs($user, 'sanctum')->getJson('/api/v1/analytics/underperforming');
        $underResponse->assertOk()
            ->assertJsonStructure(['data']);
    }

    public function test_user_can_view_audience_and_history(): void
    {
        $user = User::factory()->create();
        $brand = Brand::factory()->create(['user_id' => $user->id]);
        $account = SocialAccount::create([
            'user_id'             => $user->id,
            'brand_id'            => $brand->id,
            'provider'            => 'facebook',
            'provider_account_id' => 'fb_12345',
            'display_name'        => 'My FB Page',
            'status'              => 'connected',
        ]);

        AudienceInsight::create([
            'brand_id'          => $brand->id,
            'social_account_id' => $account->id,
            'platform'          => 'facebook',
            'insight_type'      => 'demographics',
            'dimension'         => 'gender',
            'dimension_value'   => 'F',
            'metric_value'      => 60,
            'reporting_period'  => 'lifetime',
            'collected_at'      => now(),
        ]);

        AnalyticsSnapshot::create([
            'brand_id'          => $brand->id,
            'social_account_id' => $account->id,
            'platform'          => 'facebook',
            'metric_name'       => 'followers',
            'metric_value'      => 1200,
            'collected_at'      => now(),
        ]);

        $aud = $this->actingAs($user, 'sanctum')->getJson('/api/v1/analytics/audience');
        $aud->assertOk()
            ->assertJsonStructure(['data']);

        $hist = $this->actingAs($user, 'sanctum')->getJson('/api/v1/analytics/history');
        $hist->assertOk();
    }

    public function test_user_can_generate_and_manage_recommendations(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $brand = Brand::factory()->create(['user_id' => $user->id]);

        $genResponse = $this->actingAs($user, 'sanctum')->postJson('/api/v1/analytics/recommendations/generate', [
            'brand_id' => $brand->id,
        ]);

        $genResponse->assertStatus(202);
        Queue::assertPushed(GenerateOptimizationRecommendationsJob::class);

        $rec = AiOptimizationRecommendation::create([
            'user_id'               => $user->id,
            'brand_id'              => $brand->id,
            'platform'              => 'instagram',
            'recommendation_type'   => 'caption_style',
            'title'                 => 'Use shorter captions',
            'description'           => 'Posts with shorter captions had 25% higher engagement.',
            'confidence_level'      => 'high',
            'recommendation_status' => 'active',
            'generated_at'          => now(),
        ]);

        $list = $this->actingAs($user, 'sanctum')->getJson('/api/v1/analytics/recommendations');
        $list->assertOk();

        $accept = $this->actingAs($user, 'sanctum')->postJson("/api/v1/analytics/recommendations/{$rec->id}/accept");
        $accept->assertOk();
        $this->assertEquals('accepted', $rec->fresh()->recommendation_status);

        $dismiss = $this->actingAs($user, 'sanctum')->postJson("/api/v1/analytics/recommendations/{$rec->id}/dismiss");
        $dismiss->assertOk();
        $this->assertEquals('dismissed', $rec->fresh()->recommendation_status);
    }

    public function test_user_can_generate_and_manage_reports(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $brand = Brand::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/v1/analytics/reports/generate', [
            'brand_id'     => $brand->id,
            'report_type'  => 'brand_summary',
            'period_start' => '2026-01-01',
            'period_end'   => '2026-01-31',
            'format'       => 'pdf',
        ]);

        $response->assertStatus(202)
            ->assertJsonStructure(['message', 'report_id']);

        $reportId = $response->json('report_id');
        Queue::assertPushed(GenerateAnalyticsReportJob::class);

        $list = $this->actingAs($user, 'sanctum')->getJson('/api/v1/analytics/reports');
        $list->assertOk()
            ->assertJsonPath('total', 1);

        $show = $this->actingAs($user, 'sanctum')->getJson("/api/v1/analytics/reports/{$reportId}");
        $show->assertOk()
            ->assertJsonStructure(['data']);
    }
}
