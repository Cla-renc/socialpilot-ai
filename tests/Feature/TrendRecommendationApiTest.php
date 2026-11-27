<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\TrendFeedback;
use App\Models\TrendingTopic;
use App\Models\TrendRecommendation;
use App\Models\TrendResearchJob;
use App\Models\User;
use App\Jobs\RunTrendResearch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TrendRecommendationApiTest extends TestCase
{
    use RefreshDatabase;

    private function createFixtures(User $user, Brand $brand, string $topicTitle = 'AI Operations'): array
    {
        $job = TrendResearchJob::query()->create([
            'user_id' => $user->id,
            'brand_id' => $brand->id,
            'status' => 'completed',
            'criteria_hash' => hash('sha256', 'rec-test-' . uniqid()),
            'filters' => ['sources' => ['youtube', 'reddit']],
        ]);

        $topic = TrendingTopic::query()->create([
            'brand_id' => $brand->id,
            'research_job_id' => $job->id,
            'title' => $topicTitle,
            'normalized_title' => strtolower($topicTitle),
            'category' => 'technology',
            'relevance_score' => 85,
            'relevance_reason' => 'Direct match for tech brand.',
            'opportunity_score' => 90,
            'momentum' => 'growing',
            'researched_at' => now(),
        ]);

        return [$job, $topic];
    }

    public function test_recommendations_endpoint_returns_brand_recommendations(): void
    {
        $user = User::factory()->create();
        $brand = Brand::factory()->create(['user_id' => $user->id]);
        [$job, $topic] = $this->createFixtures($user, $brand, 'AI Automated Kubernetes Operations');

        $recommendation = TrendRecommendation::create([
            'brand_id' => $brand->id,
            'trend_topic_id' => $topic->id,
            'cluster_id' => 'cluster_k8s_01',
            'score' => 88,
            'relevance_classification' => 'direct',
            'explanation' => [
                'why_it_matters' => 'Directly affects cloud deployment and automation practices.',
                'audience_interest' => 'DevOps engineers seeking workflow acceleration.',
                'timeliness_and_evidence' => 'Documented across 4 verified sources.',
            ],
            'content_package' => [
                'recommended_angle' => 'How Kubernetes AI simplifies operations.',
                'alternative_hooks' => [
                    'Hook 1: Did you see this?',
                    'Hook 2: Stop manual scaling.',
                    'Hook 3: 3 ways to automate.',
                ],
                'draft_caption' => 'Full draft copy here.',
                'suggested_format' => 'short_video',
                'call_to_action' => 'Save this post for your sprint.',
                'hashtags' => ['#devops', '#kubernetes', '#automation', '#cloud', '#ai'],
                'platform_concepts' => [
                    'tiktok' => ['hook' => 'Quick tip for DevOps'],
                    'x' => ['tweet_1' => 'Kubernetes automation is moving fast.'],
                ],
            ],
            'target_platform' => 'cross_platform',
            'suggested_format' => 'short_video',
            'status' => 'generated',
        ]);

        $response = $this->actingAs($user)->getJson("/api/v1/brands/{$brand->id}/recommendations");

        $response->assertOk()
            ->assertJsonPath('data.0.id', $recommendation->id)
            ->assertJsonPath('data.0.score', 88)
            ->assertJsonPath('data.0.relevance_classification', 'direct')
            ->assertJsonPath('data.0.target_platform', 'cross_platform')
            ->assertJsonPath('data.0.suggested_format', 'short_video')
            ->assertJsonPath('data.0.status', 'generated')
            ->assertJsonPath('data.0.content_package.alternative_hooks.0', 'Hook 1: Did you see this?');
    }

    public function test_tenant_isolation_prevents_other_users_from_viewing_recommendations(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $brand = Brand::factory()->create(['user_id' => $owner->id]);
        [$job, $topic] = $this->createFixtures($owner, $brand);

        $recommendation = TrendRecommendation::create([
            'brand_id' => $brand->id,
            'trend_topic_id' => $topic->id,
            'score' => 75,
            'relevance_classification' => 'direct',
            'explanation' => ['why_it_matters' => 'Relevant topic.'],
            'content_package' => ['recommended_angle' => 'Angle.'],
            'status' => 'generated',
        ]);

        // Intruder accessing brand recommendations index gets 404
        $this->actingAs($intruder)
            ->getJson("/api/v1/brands/{$brand->id}/recommendations")
            ->assertNotFound();

        // Intruder accessing single recommendation gets 404
        $this->actingAs($intruder)
            ->getJson("/api/v1/brands/{$brand->id}/recommendations/{$recommendation->id}")
            ->assertNotFound();
    }

    public function test_recommendation_status_transition(): void
    {
        $user = User::factory()->create();
        $brand = Brand::factory()->create(['user_id' => $user->id]);
        [$job, $topic] = $this->createFixtures($user, $brand);

        $recommendation = TrendRecommendation::create([
            'brand_id' => $brand->id,
            'trend_topic_id' => $topic->id,
            'score' => 82,
            'relevance_classification' => 'direct',
            'explanation' => ['why_it_matters' => 'Topic matters.'],
            'content_package' => ['recommended_angle' => 'Angle.'],
            'status' => 'generated',
        ]);

        $response = $this->actingAs($user)->postJson(
            "/api/v1/brands/{$brand->id}/recommendations/{$recommendation->id}/status",
            ['status' => 'saved'],
        );

        $response->assertOk()
            ->assertJsonPath('data.status', 'saved');

        $this->assertDatabaseHas('trend_recommendations', [
            'id' => $recommendation->id,
            'status' => 'saved',
        ]);
    }

    public function test_recommendation_feedback_records_and_syncs_to_topic(): void
    {
        $user = User::factory()->create();
        $brand = Brand::factory()->create(['user_id' => $user->id]);
        [$job, $topic] = $this->createFixtures($user, $brand);

        $recommendation = TrendRecommendation::create([
            'brand_id' => $brand->id,
            'trend_topic_id' => $topic->id,
            'score' => 90,
            'relevance_classification' => 'direct',
            'explanation' => ['why_it_matters' => 'High relevance.'],
            'content_package' => ['recommended_angle' => 'Angle.'],
            'status' => 'generated',
        ]);

        $response = $this->actingAs($user)->postJson(
            "/api/v1/brands/{$brand->id}/recommendations/{$recommendation->id}/feedback",
            ['feedback' => 'relevant'],
        );

        $response->assertOk()
            ->assertJsonPath('data.feedback_status', 'relevant');

        $this->assertDatabaseHas('trend_recommendations', [
            'id' => $recommendation->id,
            'feedback_status' => 'relevant',
        ]);

        // Feedback must sync to TrendFeedback on the underlying topic
        $this->assertDatabaseHas('trend_feedback', [
            'trend_topic_id' => $topic->id,
            'user_id' => $user->id,
            'feedback_type' => 'relevant',
        ]);
    }

    public function test_filtering_recommendations(): void
    {
        $user = User::factory()->create();
        $brand = Brand::factory()->create(['user_id' => $user->id]);
        [$job1, $topic1] = $this->createFixtures($user, $brand, 'Topic 1');
        [$job2, $topic2] = $this->createFixtures($user, $brand, 'Topic 2');

        TrendRecommendation::create([
            'brand_id' => $brand->id,
            'trend_topic_id' => $topic1->id,
            'score' => 85,
            'relevance_classification' => 'direct',
            'explanation' => ['why_it_matters' => 'Direct.'],
            'content_package' => ['recommended_angle' => 'Angle 1.'],
            'target_platform' => 'tiktok',
            'suggested_format' => 'short_video',
            'status' => 'generated',
        ]);

        TrendRecommendation::create([
            'brand_id' => $brand->id,
            'trend_topic_id' => $topic2->id,
            'score' => 50,
            'relevance_classification' => 'adjacent',
            'explanation' => ['why_it_matters' => 'Adjacent.'],
            'content_package' => ['recommended_angle' => 'Angle 2.'],
            'target_platform' => 'cross_platform',
            'suggested_format' => 'carousel',
            'status' => 'saved',
        ]);

        // Filter by classification=adjacent
        $resAdj = $this->actingAs($user)->getJson("/api/v1/brands/{$brand->id}/recommendations?classification=adjacent");
        $resAdj->assertOk()->assertJsonCount(1, 'data');
        $this->assertEquals(50, $resAdj->json('data.0.score'));

        // Filter by format=short_video
        $resFmt = $this->actingAs($user)->getJson("/api/v1/brands/{$brand->id}/recommendations?format=short_video");
        $resFmt->assertOk()->assertJsonCount(1, 'data');
        $this->assertEquals(85, $resFmt->json('data.0.score'));
    }

    public function test_run_trend_research_job_persists_ai_recommendations(): void
    {
        $user = User::factory()->create();
        $brand = Brand::factory()->for($user)->create(['name' => 'Acme Cloud AI']);

        $jobRecord = TrendResearchJob::query()->create([
            'user_id' => $user->id,
            'brand_id' => $brand->id,
            'status' => 'queued',
            'criteria_hash' => hash('sha256', 'rec-job-test'),
            'filters' => ['sources' => ['youtube']],
        ]);

        $mockPayload = [
            'topics' => [
                [
                    'title' => 'Autonomous Cloud Deployment Workflows 2026',
                    'description' => 'How DevOps teams deploy across clouds with zero downtime.',
                    'summary' => 'Autonomous cloud deployment patterns with zero downtime.',
                    'category' => 'technology',
                    'relevance_score' => 92,
                    'relevance_reason' => 'Direct match for Acme Cloud AI.',
                    'momentum' => 'growing',
                    'momentum_evidence' => 'Expanding adoption in enterprise dev teams.',
                    'geographic_market' => 'US',
                    'opportunity_score' => 95,
                    'cluster_id' => 'cl_autonomous_cloud',
                    'participating_platforms' => ['youtube'],
                    'is_cross_platform' => false,
                    'recommendation' => [
                        'score' => 92,
                        'relevance_classification' => 'direct',
                        'explanation' => [
                            'why_it_matters' => 'Core capability match for Acme Cloud AI.',
                            'audience_interest' => 'Engineers seeking zero downtime deployments.',
                            'timeliness_and_evidence' => 'Documented across recent technical reviews.',
                        ],
                        'content_package' => [
                            'recommended_angle' => 'Why autonomous deployments will replace manual pipelines.',
                            'alternative_hooks' => [
                                'Hook A: 3 steps to zero downtime',
                                'Hook B: Stop manual pushes',
                                'Hook C: The deployment shift',
                            ],
                            'draft_caption' => 'The shift toward autonomous deployments is here.',
                            'suggested_format' => 'short_video',
                            'call_to_action' => 'Read our guide on zero downtime.',
                            'hashtags' => ['#devops', '#cloud', '#automation', '#deployment', '#ai'],
                            'platform_concepts' => [
                                'tiktok' => ['hook' => 'Stop manual deployments'],
                            ],
                        ],
                        'target_platform' => 'youtube',
                        'suggested_format' => 'short_video',
                    ],
                    'sources' => [
                        [
                            'source_key' => 'youtube',
                            'source_name' => 'YouTube Data API',
                            'title' => 'Autonomous Cloud Deployment Workflows',
                            'url' => 'https://youtube.com/watch?v=cloud2026',
                            'published_at' => now()->toIso8601String(),
                            'evidence' => 'High view velocity.',
                            'metrics' => ['views' => 12000],
                        ],
                    ],
                ],
            ],
            'sources' => [
                ['source_key' => 'youtube', 'source_name' => 'YouTube Data API', 'status' => 'success', 'result_count' => 1],
            ],
            'clusters' => [],
            'cross_platform_count' => 0,
            'result_state' => 'success',
            'filtered_out_count' => 0,
            'min_relevance_score' => 25,
            'recommendations' => [],
        ];

        Http::fake([
            '*/internal/v1/research' => Http::response($mockPayload, 200),
        ]);

        $job = new RunTrendResearch($jobRecord->id);
        $job->handle();

        // Verify recommendation persisted in DB
        $this->assertDatabaseHas('trend_recommendations', [
            'brand_id' => $brand->id,
            'score' => 92,
            'relevance_classification' => 'direct',
            'target_platform' => 'youtube',
            'suggested_format' => 'short_video',
            'status' => 'generated',
        ]);

        // Verify API endpoint returns it
        $response = $this->actingAs($user)->getJson("/api/v1/brands/{$brand->id}/recommendations");
        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.score', 92)
            ->assertJsonPath('data.0.relevance_classification', 'direct')
            ->assertJsonPath('data.0.content_package.alternative_hooks.0', 'Hook A: 3 steps to zero downtime');
    }

    public function test_cross_run_reconciliation_preserves_user_saved_status(): void
    {
        $user = User::factory()->create();
        $brand = Brand::factory()->for($user)->create();

        // Run 1: User receives and saves a recommendation
        [$job1, $topic1] = $this->createFixtures($user, $brand, 'Cloud Cost AI 2026');
        $rec1 = TrendRecommendation::create([
            'brand_id' => $brand->id,
            'trend_topic_id' => $topic1->id,
            'cluster_id' => 'cl_cloud_cost_ai',
            'score' => 89,
            'relevance_classification' => 'direct',
            'explanation' => ['why_it_matters' => 'Core topic.'],
            'content_package' => ['recommended_angle' => 'Angle 1.'],
            'status' => 'saved',
        ]);

        // Run 2: Research discovers the same cluster in a later run
        $jobRecord2 = TrendResearchJob::query()->create([
            'user_id' => $user->id,
            'brand_id' => $brand->id,
            'status' => 'queued',
            'criteria_hash' => hash('sha256', 'run2'),
            'filters' => ['sources' => ['youtube']],
        ]);

        $mockPayload = [
            'topics' => [
                [
                    'title' => 'Cloud Cost AI Trends Discussion',
                    'description' => 'Discussion on cloud cost optimization.',
                    'summary' => 'Discussion on cloud cost optimization.',
                    'category' => 'technology',
                    'relevance_score' => 89,
                    'relevance_reason' => 'Direct match.',
                    'momentum' => 'growing',
                    'opportunity_score' => 90,
                    'cluster_id' => 'cl_cloud_cost_ai',
                    'participating_platforms' => ['youtube'],
                    'is_cross_platform' => false,
                    'recommendation' => [
                        'score' => 89,
                        'relevance_classification' => 'direct',
                        'explanation' => ['why_it_matters' => 'Core topic.'],
                        'content_package' => ['recommended_angle' => 'Angle 2.'],
                    ],
                    'sources' => [
                        [
                            'source_key' => 'youtube',
                            'source_name' => 'YouTube',
                            'title' => 'Cloud Cost AI Video',
                            'url' => 'https://youtube.com/watch?v=cloud_ai_2',
                            'published_at' => now()->toIso8601String(),
                        ],
                    ],
                ],
            ],
            'sources' => [
                ['source_key' => 'youtube', 'source_name' => 'YouTube', 'status' => 'success', 'result_count' => 1],
            ],
            'result_state' => 'success',
        ];

        Http::fake([
            '*/internal/v1/research' => Http::response($mockPayload, 200),
        ]);

        $job = new RunTrendResearch($jobRecord2->id);
        $job->handle();

        // The second run's recommendation for the same cluster inherits the user's 'saved' status!
        $newTopic = TrendingTopic::query()->where('research_job_id', $jobRecord2->id)->firstOrFail();
        $this->assertDatabaseHas('trend_recommendations', [
            'brand_id' => $brand->id,
            'trend_topic_id' => $newTopic->id,
            'cluster_id' => 'cl_cloud_cost_ai',
            'status' => 'saved',
        ]);
    }
}
