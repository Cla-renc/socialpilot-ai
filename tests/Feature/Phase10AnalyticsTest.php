<?php

namespace Tests\Feature;

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
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class Phase10AnalyticsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Brand $brand;
    private SocialAccount $account;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->brand = Brand::factory()->create(['user_id' => $this->user->id]);
        $this->account = SocialAccount::factory()->create([
            'brand_id' => $this->brand->id,
            'provider' => 'instagram',
        ]);
    }

    // --- Auth ---
    public function test_analytics_overview_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/analytics/overview');
        $response->assertStatus(401);
    }

    // --- Overview ---
    public function test_analytics_overview_returns_data_for_authenticated_user(): void
    {
        Sanctum::actingAs($this->user);
        $response = $this->getJson('/api/v1/analytics/overview');
        $response->assertStatus(200)->assertJsonStructure(['data', 'period']);
    }

    // --- Brand analytics ---
    public function test_brand_analytics_authorizes_owner(): void
    {
        Sanctum::actingAs($this->user);
        $response = $this->getJson("/api/v1/analytics/brands/{$this->brand->id}");
        $response->assertStatus(200);
    }

    public function test_brand_analytics_denies_other_user(): void
    {
        $other = User::factory()->create();
        Sanctum::actingAs($other);
        $response = $this->getJson("/api/v1/analytics/brands/{$this->brand->id}");
        $response->assertStatus(403);
    }

    // --- Brand comparison ---
    public function test_brand_comparison_requires_ownership(): void
    {
        Sanctum::actingAs($this->user);
        $response = $this->getJson("/api/v1/analytics/brands/{$this->brand->id}/comparison");
        $response->assertStatus(200);
    }

    // --- Snapshots ---
    public function test_analytics_history_returns_snapshots(): void
    {
        Sanctum::actingAs($this->user);
        AnalyticsSnapshot::factory()->create([
            'brand_id' => $this->brand->id,
            'social_account_id' => $this->account->id,
            'platform' => 'instagram',
            'metric_type' => 'account',
            'metric_name' => 'followers',
            'metric_value' => 1000,
            'metric_unit' => 'count',
            'reporting_period' => 'day',
            'period_start' => now()->subDay(),
            'period_end' => now(),
            'collected_at' => now(),
            'source' => 'instagram_graph_api',
        ]);
        $response = $this->getJson('/api/v1/analytics/history');
        $response->assertStatus(200);
    }

    // --- Duplicate snapshot prevention ---
    public function test_snapshot_updateorcreate_prevents_duplicates(): void
    {
        $data = [
            'brand_id' => $this->brand->id,
            'social_account_id' => $this->account->id,
            'platform' => 'instagram',
            'metric_type' => 'account',
            'metric_name' => 'followers',
            'metric_value' => 1000,
            'metric_unit' => 'count',
            'reporting_period' => 'day',
            'period_start' => now()->subDay()->toDateString(),
            'period_end' => now()->toDateString(),
            'collected_at' => now(),
            'source' => 'test',
        ];
        AnalyticsSnapshot::updateOrCreate(
            array_intersect_key($data, array_flip(['brand_id','social_account_id','metric_name','period_start','period_end'])),
            $data
        );
        AnalyticsSnapshot::updateOrCreate(
            array_intersect_key($data, array_flip(['brand_id','social_account_id','metric_name','period_start','period_end'])),
            array_merge($data, ['metric_value' => 1200])
        );
        $this->assertEquals(1, AnalyticsSnapshot::where('brand_id', $this->brand->id)->count());
        $this->assertEquals(1200, AnalyticsSnapshot::where('brand_id', $this->brand->id)->first()->metric_value);
    }

    // --- Post analytics ---
    public function test_posts_endpoint_returns_paginated_results(): void
    {
        Sanctum::actingAs($this->user);
        PostAnalytic::factory()->create([
            'social_account_id' => $this->account->id,
            'platform' => 'instagram',
            'external_post_id' => 'test123',
            'metric_name' => 'likes',
            'metric_value' => 50,
            'collected_at' => now(),
        ]);
        $response = $this->getJson('/api/v1/analytics/posts');
        $response->assertStatus(200);
    }

    public function test_top_performing_returns_posts_by_engagement(): void
    {
        Sanctum::actingAs($this->user);
        PostAnalytic::factory()->create([
            'social_account_id' => $this->account->id,
            'platform' => 'instagram',
            'external_post_id' => 'top1',
            'metric_name' => 'engagement_rate',
            'metric_value' => 8.5,
            'collected_at' => now(),
        ]);
        $response = $this->getJson('/api/v1/analytics/top-performing');
        $response->assertStatus(200)->assertJsonStructure(['data']);
    }

    // --- Collection trigger ---
    public function test_trigger_collection_dispatches_job(): void
    {
        \Illuminate\Support\Facades\Queue::fake();
        Sanctum::actingAs($this->user);
        $response = $this->postJson('/api/v1/analytics/collect', [
            'social_account_id' => $this->account->id,
        ]);
        $response->assertStatus(202);
        \Illuminate\Support\Facades\Queue::assertPushed(\App\Jobs\Analytics\CollectAccountAnalyticsJob::class);
    }

    public function test_trigger_collection_denies_unowned_account(): void
    {
        $other = User::factory()->create();
        Sanctum::actingAs($other);
        $response = $this->postJson('/api/v1/analytics/collect', [
            'social_account_id' => $this->account->id,
        ]);
        $response->assertStatus(403);
    }

    // --- Recommendations ---
    public function test_recommendations_endpoint_returns_paginated(): void
    {
        Sanctum::actingAs($this->user);
        $response = $this->getJson('/api/v1/analytics/recommendations');
        $response->assertStatus(200);
    }

    public function test_generate_recommendations_dispatches_job(): void
    {
        \Illuminate\Support\Facades\Queue::fake();
        Sanctum::actingAs($this->user);
        $response = $this->postJson('/api/v1/analytics/recommendations/generate', [
            'brand_id' => $this->brand->id,
        ]);
        $response->assertStatus(202);
        \Illuminate\Support\Facades\Queue::assertPushed(\App\Jobs\Analytics\GenerateOptimizationRecommendationsJob::class);
    }

    public function test_accept_recommendation_updates_status(): void
    {
        Sanctum::actingAs($this->user);
        $rec = AiOptimizationRecommendation::factory()->create([
            'brand_id' => $this->brand->id,
            'user_id' => $this->user->id,
            'status' => 'pending',
            'recommendation_type' => 'posting_time',
            'title' => 'Post at 9am',
            'description' => 'Test recommendation',
            'confidence' => 'medium',
            'priority' => 'normal',
        ]);
        $response = $this->postJson("/api/v1/analytics/recommendations/{$rec->id}/accept");
        $response->assertStatus(200);
        $this->assertEquals('accepted', $rec->fresh()->status);
    }

    public function test_dismiss_recommendation_updates_status(): void
    {
        Sanctum::actingAs($this->user);
        $rec = AiOptimizationRecommendation::factory()->create([
            'brand_id' => $this->brand->id,
            'user_id' => $this->user->id,
            'status' => 'pending',
            'recommendation_type' => 'content_format',
            'title' => 'Use more video',
            'description' => 'Test',
            'confidence' => 'low',
            'priority' => 'low',
        ]);
        $response = $this->postJson("/api/v1/analytics/recommendations/{$rec->id}/dismiss");
        $response->assertStatus(200);
        $this->assertEquals('dismissed', $rec->fresh()->status);
    }

    // --- Reports ---
    public function test_reports_endpoint_returns_paginated(): void
    {
        Sanctum::actingAs($this->user);
        $response = $this->getJson('/api/v1/analytics/reports');
        $response->assertStatus(200);
    }

    public function test_generate_report_dispatches_job(): void
    {
        \Illuminate\Support\Facades\Queue::fake();
        Sanctum::actingAs($this->user);
        $response = $this->postJson('/api/v1/analytics/reports/generate', [
            'brand_id'    => $this->brand->id,
            'report_type' => 'overview',
            'period_start'=> now()->subMonth()->toDateString(),
            'period_end'  => now()->toDateString(),
            'format'      => 'pdf',
        ]);
        $response->assertStatus(202)->assertJsonStructure(['message', 'report_id']);
        \Illuminate\Support\Facades\Queue::assertPushed(\App\Jobs\Analytics\GenerateAnalyticsReportJob::class);
    }

    // --- Audience ---
    public function test_audience_endpoint_returns_data(): void
    {
        Sanctum::actingAs($this->user);
        $response = $this->getJson('/api/v1/analytics/audience');
        $response->assertStatus(200)->assertJsonStructure(['data']);
    }

    // --- Collection status ---
    public function test_collection_status_endpoint(): void
    {
        Sanctum::actingAs($this->user);
        $response = $this->getJson('/api/v1/analytics/collection-status');
        $response->assertStatus(200)->assertJsonStructure(['data']);
    }

    // --- Platform analytics ---
    public function test_platform_analytics_endpoint(): void
    {
        Sanctum::actingAs($this->user);
        $response = $this->getJson('/api/v1/analytics/platforms');
        $response->assertStatus(200);
    }

    // --- Account analytics ---
    public function test_account_analytics_authorizes_owner(): void
    {
        Sanctum::actingAs($this->user);
        $response = $this->getJson("/api/v1/analytics/accounts/{$this->account->id}");
        $response->assertStatus(200);
    }

    public function test_account_analytics_denies_other_user(): void
    {
        $other = User::factory()->create();
        Sanctum::actingAs($other);
        $response = $this->getJson("/api/v1/analytics/accounts/{$this->account->id}");
        $response->assertStatus(403);
    }
}
?>