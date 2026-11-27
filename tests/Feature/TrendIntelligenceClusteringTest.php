<?php

namespace Tests\Feature;

use App\Jobs\RunTrendResearch;
use App\Models\Brand;
use App\Models\TrendResearchJob;
use App\Models\TrendingTopic;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TrendIntelligenceClusteringTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_persists_cross_platform_cluster_metadata_and_api_exposes_it(): void
    {
        $user = User::factory()->create();
        $brand = Brand::factory()->for($user)->create();

        $jobRecord = TrendResearchJob::query()->create([
            'user_id' => $user->id,
            'brand_id' => $brand->id,
            'status' => 'queued',
            'criteria_hash' => hash('sha256', 'cluster-test'),
            'filters' => ['sources' => ['youtube', 'reddit']],
        ]);

        $mockPayload = [
            'topics' => [
                [
                    'title' => 'Sustainable Trail Running Gear 2026',
                    'description' => 'Eco trail running gear discussion and video reviews.',
                    'summary' => 'Eco trail running gear discussion and video reviews.',
                    'category' => 'sports_fitness',
                    'relevance_score' => 88,
                    'relevance_reason' => 'Strong match for outdoor trail gear audience.',
                    'momentum' => 'growing',
                    'momentum_evidence' => 'Rapidly growing cross-platform discussions.',
                    'geographic_market' => 'US',
                    'opportunity_score' => 92,
                    'cluster_id' => 'cl_sustainable_trail_running',
                    'participating_platforms' => ['reddit', 'youtube'],
                    'is_cross_platform' => true,
                    'cluster_confidence' => 0.94,
                    'sources' => [
                        [
                            'source_key' => 'youtube',
                            'source_name' => 'YouTube Data API',
                            'title' => 'Sustainable Trail Running Gear 2026 Review',
                            'url' => 'https://youtube.com/watch?v=gear2026',
                            'published_at' => now()->toIso8601String(),
                            'evidence' => 'Top performing video review.',
                            'metrics' => ['views' => 45000, 'likes' => 1800],
                        ],
                        [
                            'source_key' => 'reddit',
                            'source_name' => 'Reddit API (r/trailrunning)',
                            'title' => 'Eco Trail Gear Experiences',
                            'url' => 'https://reddit.com/r/trailrunning/comments/gear',
                            'published_at' => now()->toIso8601String(),
                            'evidence' => 'Active community thread.',
                            'metrics' => ['score' => 310, 'comments' => 64],
                        ],
                    ],
                ],
            ],
            'sources' => [
                ['source_key' => 'youtube', 'source_name' => 'YouTube Data API', 'status' => 'success', 'result_count' => 1],
                ['source_key' => 'reddit', 'source_name' => 'Reddit API', 'status' => 'success', 'result_count' => 1],
            ],
            'clusters' => [
                [
                    'cluster_id' => 'cl_sustainable_trail_running',
                    'cluster_title' => 'Sustainable Trail Running Gear 2026',
                    'participating_platforms' => ['reddit', 'youtube'],
                    'is_cross_platform' => true,
                    'cluster_confidence' => 0.94,
                ],
            ],
            'cross_platform_count' => 1,
            'result_state' => 'success',
            'filtered_out_count' => 0,
            'min_relevance_score' => 25,
        ];

        Http::fake([
            '*/internal/v1/research' => Http::response($mockPayload, 200),
        ]);

        $job = new RunTrendResearch($jobRecord->id);
        $job->handle();

        $this->assertDatabaseHas('trending_topics', [
            'brand_id' => $brand->id,
            'cluster_id' => 'cl_sustainable_trail_running',
            'is_cross_platform' => true,
            'cluster_confidence' => 94,
        ]);

        $response = $this->actingAs($user)
            ->getJson("/api/v1/brands/{$brand->id}/trends");

        $response->assertOk()
            ->assertJsonPath('data.0.cluster_id', 'cl_sustainable_trail_running')
            ->assertJsonPath('data.0.is_cross_platform', true)
            ->assertJsonPath('data.0.cluster_confidence', 94)
            ->assertJsonPath('data.0.participating_platforms', ['reddit', 'youtube']);

        // Test filtering by cross_platform_only
        $filteredResponse = $this->actingAs($user)
            ->getJson("/api/v1/brands/{$brand->id}/trends?cross_platform_only=1");
        $filteredResponse->assertOk()
            ->assertJsonCount(1, 'data');

        // Test filtering by cluster_id
        $clusterFiltered = $this->actingAs($user)
            ->getJson("/api/v1/brands/{$brand->id}/trends?cluster_id=cl_sustainable_trail_running");
        $clusterFiltered->assertOk()
            ->assertJsonCount(1, 'data');

        $emptyCluster = $this->actingAs($user)
            ->getJson("/api/v1/brands/{$brand->id}/trends?cluster_id=cl_non_existent");
        $emptyCluster->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_clustered_topics_enforce_tenant_isolation(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $brandA = Brand::factory()->for($userA)->create();
        $brandB = Brand::factory()->for($userB)->create();

        $jobA = TrendResearchJob::query()->create([
            'user_id' => $userA->id,
            'brand_id' => $brandA->id,
            'status' => 'completed',
            'criteria_hash' => hash('sha256', 'tenant-a'),
            'filters' => [],
        ]);

        TrendingTopic::query()->create([
            'brand_id' => $brandA->id,
            'research_job_id' => $jobA->id,
            'title' => 'Tenant A Secret Innovation',
            'normalized_title' => 'tenant a secret innovation',
            'relevance_score' => 90,
            'relevance_reason' => 'Relevant to brand A',
            'opportunity_score' => 85,
            'cluster_id' => 'cl_secret_innovation',
            'is_cross_platform' => true,
            'participating_platforms' => ['youtube', 'reddit'],
            'researched_at' => now(),
        ]);

        // User B cannot access Brand A's clustered trends (returns 404 to avoid tenant enumeration)
        $this->actingAs($userB)
            ->getJson("/api/v1/brands/{$brandA->id}/trends")
            ->assertNotFound();

        // User B's own query returns empty
        $responseB = $this->actingAs($userB)
            ->getJson("/api/v1/brands/{$brandB->id}/trends?cluster_id=cl_secret_innovation");
        $responseB->assertOk()
            ->assertJsonCount(0, 'data');
    }
}
