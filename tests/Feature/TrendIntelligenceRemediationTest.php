<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\TrendResearchJob;
use App\Models\TrendSource;
use App\Models\TrendingTopic;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TrendIntelligenceRemediationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Helper to mock successful FastAPI research engine response.
     */
    protected function mockEngineResponse(array $topics = [], array $sourceStatuses = []): void
    {
        if (empty($sourceStatuses)) {
            $sourceStatuses = [
                ['source_key' => 'google_trends', 'source_name' => 'Google Trends', 'status' => 'ready', 'result_count' => 2],
                ['source_key' => 'rss', 'source_name' => 'Configured RSS feeds', 'status' => 'ready', 'result_count' => 3],
            ];
        }

        if (empty($topics)) {
            $topics = [
                [
                    'title' => 'Productivity Sprints in Tech',
                    'description' => 'Fast-growing workplace productivity trend.',
                    'summary' => 'Knowledge workers adopt shorter high-intensity cycles.',
                    'content_angles' => ['Why sprints beat marathons', 'Managing tech burnout'],
                    'content_angle_status' => 'completed',
                    'category' => 'general',
                    'relevance_score' => 88,
                    'relevance_reason' => 'Strong alignment with brand productivity niche.',
                    'momentum' => 'growing',
                    'opportunity_score' => 82,
                    'sources' => [
                        [
                            'source_key' => 'google_trends',
                            'source_name' => 'Google Trends',
                            'title' => 'Productivity Sprints Search Spike',
                            'url' => 'https://trends.google.com/trends/explore?q=productivity+sprints',
                        ],
                    ],
                ],
            ];
        }

        Http::fake([
            '*/internal/v1/research' => Http::response([
                'topics' => $topics,
                'sources' => $sourceStatuses,
            ], 200),
        ]);
    }

    /**
     * 1. Synchronous research execution succeeds, persists topics, and returns 200.
     */
    public function test_synchronous_trend_research_succeeds_and_returns_topics(): void
    {
        $this->mockEngineResponse();

        $user = User::factory()->create();
        $brand = Brand::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user, 'web')->postJson("/api/v1/brands/{$brand->id}/trends/research", [
            'days' => 30,
            'sources' => ['google_trends', 'rss'],
            'sync' => true,
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.status', 'completed');
        $response->assertJsonPath('message', 'Trend research completed successfully.');
        $response->assertJsonCount(1, 'data.topics');

        $this->assertDatabaseHas('trend_research_jobs', [
            'brand_id' => $brand->id,
            'user_id' => $user->id,
            'status' => 'completed',
        ]);

        $this->assertDatabaseHas('trending_topics', [
            'brand_id' => $brand->id,
            'title' => 'Productivity Sprints in Tech',
            'relevance_score' => 88,
        ]);
    }

    /**
     * 2. Asynchronous research request remains backward compatible (returns 202).
     */
    public function test_asynchronous_trend_research_remains_backward_compatible(): void
    {
        $user = User::factory()->create();
        $brand = Brand::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user, 'web')->postJson("/api/v1/brands/{$brand->id}/trends/research", [
            'days' => 30,
            'sync' => false,
        ]);

        $response->assertStatus(202);
        $response->assertJsonPath('data.status', 'queued');
        $response->assertJsonPath('message', 'Trend research queued.');
    }

    /**
     * 3. Default sources populate zero-cost google_trends and rss.
     */
    public function test_default_sources_use_zero_cost_providers(): void
    {
        $this->mockEngineResponse();

        $user = User::factory()->create();
        $brand = Brand::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user, 'web')->postJson("/api/v1/brands/{$brand->id}/trends/research", [
            'days' => 30,
            'sync' => true,
        ]);

        $response->assertOk();
        $job = TrendResearchJob::where('brand_id', $brand->id)->firstOrFail();
        $this->assertContains('google_trends', $job->filters['sources']);
    }

    /**
     * 4. Partial source failures preserve successful topics with partial status.
     */
    public function test_partial_source_failure_preserves_successful_topics(): void
    {
        $this->mockEngineResponse(
            topics: [
                [
                    'title' => 'Remote Work Ergonomics',
                    'category' => 'general',
                    'relevance_score' => 75,
                    'relevance_reason' => 'Keyword match',
                    'opportunity_score' => 70,
                    'sources' => [
                        [
                            'source_key' => 'google_trends',
                            'source_name' => 'Google Trends',
                            'title' => 'Ergonomics Search Trend',
                            'url' => 'https://trends.google.com/trends/explore?q=ergonomics',
                        ],
                    ],
                ],
            ],
            sourceStatuses: [
                ['source_key' => 'google_trends', 'source_name' => 'Google Trends', 'status' => 'ready', 'result_count' => 1],
                ['source_key' => 'news_api', 'source_name' => 'News API', 'status' => 'configuration_required', 'message' => 'API key missing', 'result_count' => 0],
            ]
        );

        $user = User::factory()->create();
        $brand = Brand::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user, 'web')->postJson("/api/v1/brands/{$brand->id}/trends/research", [
            'days' => 30,
            'sync' => true,
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.status', 'partial');
        $this->assertDatabaseHas('trending_topics', ['title' => 'Remote Work Ergonomics']);
    }

    /**
     * 5. When all sources fail, clear error is returned and job is marked failed.
     */
    public function test_all_sources_failing_marks_job_failed_and_returns_error(): void
    {
        $this->mockEngineResponse(
            topics: [],
            sourceStatuses: [
                ['source_key' => 'reddit', 'source_name' => 'Reddit', 'status' => 'configuration_required', 'message' => 'Reddit credentials missing', 'result_count' => 0],
                ['source_key' => 'youtube', 'source_name' => 'YouTube', 'status' => 'error', 'message' => 'Quota exceeded', 'result_count' => 0],
            ]
        );

        $user = User::factory()->create();
        $brand = Brand::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user, 'web')->postJson("/api/v1/brands/{$brand->id}/trends/research", [
            'days' => 30,
            'sources' => ['reddit', 'youtube'],
            'sync' => true,
        ]);

        $response->assertStatus(502);
        $response->assertJsonPath('data.status', 'failed');
        $response->assertJsonPath('message', 'Trend research failed.');
    }

    /**
     * 6. Duplicate in-flight research request returns 200 without creating a new job.
     */
    public function test_duplicate_research_request_returns_existing_job(): void
    {
        $user = User::factory()->create();
        $brand = Brand::factory()->create(['user_id' => $user->id]);

        // First async request
        $res1 = $this->actingAs($user, 'web')->postJson("/api/v1/brands/{$brand->id}/trends/research", [
            'days' => 30,
            'sync' => false,
        ]);
        $res1->assertStatus(202);

        // Second identical request while first is queued
        $res2 = $this->actingAs($user, 'web')->postJson("/api/v1/brands/{$brand->id}/trends/research", [
            'days' => 30,
            'sync' => false,
        ]);
        $res2->assertStatus(200);
        $res2->assertJsonPath('message', 'Matching trend research is already active.');
        $this->assertEquals(1, TrendResearchJob::where('brand_id', $brand->id)->count());
    }

    /**
     * 7. Cross-tenant research request is rejected with 404 (Brand not found).
     */
    public function test_cross_tenant_research_is_forbidden(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $brandA = Brand::factory()->create(['user_id' => $userA->id]);

        $response = $this->actingAs($userB, 'web')->postJson("/api/v1/brands/{$brandA->id}/trends/research", [
            'days' => 30,
            'sync' => true,
        ]);

        $response->assertNotFound();
    }

    /**
     * 8. Flutter Bearer Token authentication contract works seamlessly with sync option.
     */
    public function test_flutter_bearer_token_can_execute_sync_research(): void
    {
        $this->mockEngineResponse();

        $user = User::factory()->create();
        $brand = Brand::factory()->create(['user_id' => $user->id]);
        $token = $user->createToken('flutter-test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/brands/{$brand->id}/trends/research", [
                'days' => 30,
                'sync' => true,
            ]);

        $response->assertOk();
        $response->assertJsonPath('data.status', 'completed');
    }

    /**
     * 9. Web startResearch action supports sync execution and redirects to index with success.
     */
    public function test_web_interface_supports_sync_execution(): void
    {
        $this->mockEngineResponse();

        $user = User::factory()->create();
        $brand = Brand::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user, 'web')->post(route('brands.trends.research', $brand), [
            'days' => 30,
            'sync' => 1,
        ]);

        $response->assertRedirect(route('brands.trends.index', $brand));
        $response->assertSessionHas('success', 'Trend research completed.');
    }

    // =========================================================================
    // STAGE 5.6.24 — NEW TESTS
    // =========================================================================

    /**
     * 10. RSS feeds from Laravel config are propagated in the internal research payload.
     */
    public function test_rss_feeds_propagated_in_internal_research_payload(): void
    {
        $capturedPayload = null;

        Http::fake([
            '*/internal/v1/research' => function ($request) use (&$capturedPayload) {
                $capturedPayload = $request->data();
                return Http::response([
                    'topics' => [],
                    'sources' => [
                        ['source_key' => 'rss', 'source_name' => 'Configured RSS feeds', 'status' => 'configuration_required', 'result_count' => 0],
                    ],
                    'result_state' => 'configuration_required',
                    'filtered_out_count' => 0,
                    'min_relevance_score' => 25,
                ], 200);
            },
        ]);

        $user = User::factory()->create();
        $brand = Brand::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user, 'web')->postJson("/api/v1/brands/{$brand->id}/trends/research", [
            'days' => 30,
            'sources' => ['rss'],
            'sync' => true,
        ]);

        $this->assertNotNull($capturedPayload, 'Internal research endpoint was not called');
        $this->assertArrayHasKey('filters', $capturedPayload);
        $this->assertArrayHasKey('rss_feeds', $capturedPayload['filters']);
        $configuredFeeds = config('services.trends.rss_feeds', []);
        $this->assertIsArray($capturedPayload['filters']['rss_feeds']);
        foreach ($configuredFeeds as $feed) {
            $this->assertContains($feed, $capturedPayload['filters']['rss_feeds']);
        }
    }

    /**
     * 11. result_state = 'success' is persisted when topics are returned.
     */
    public function test_result_state_success_persisted_when_topics_returned(): void
    {
        Http::fake([
            '*/internal/v1/research' => Http::response([
                'topics' => [
                    [
                        'title' => 'AI Startup Raises $500M',
                        'category' => 'general',
                        'relevance_score' => 85,
                        'relevance_reason' => 'Direct keyword match.',
                        'opportunity_score' => 80,
                        'sources' => [['source_key' => 'google_trends', 'source_name' => 'Google Trends', 'title' => 'AI Startup', 'url' => 'https://trends.google.com']],
                    ],
                ],
                'sources' => [['source_key' => 'google_trends', 'source_name' => 'Google Trends', 'status' => 'success', 'result_count' => 1]],
                'result_state' => 'success',
                'filtered_out_count' => 0,
                'min_relevance_score' => 25,
            ], 200),
        ]);

        $user = User::factory()->create();
        $brand = Brand::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user, 'web')->postJson("/api/v1/brands/{$brand->id}/trends/research", [
            'days' => 30,
            'sync' => true,
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.result_state', 'success');

        $this->assertDatabaseHas('trend_research_jobs', [
            'brand_id' => $brand->id,
            'status' => 'completed',
            'result_state' => 'success',
        ]);
    }

    /**
     * 12. result_state = 'no_relevant_topics' persisted when engine returns empty topics with success source.
     */
    public function test_result_state_no_relevant_topics_when_engine_returns_empty(): void
    {
        Http::fake([
            '*/internal/v1/research' => Http::response([
                'topics' => [],
                'sources' => [['source_key' => 'google_trends', 'source_name' => 'Google Trends', 'status' => 'success', 'result_count' => 10]],
                'result_state' => 'no_relevant_topics',
                'filtered_out_count' => 10,
                'min_relevance_score' => 25,
            ], 200),
        ]);

        $user = User::factory()->create();
        $brand = Brand::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user, 'web')->postJson("/api/v1/brands/{$brand->id}/trends/research", [
            'days' => 30,
            'sync' => true,
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.status', 'completed');
        $response->assertJsonPath('data.result_state', 'no_relevant_topics');
        $response->assertJsonCount(0, 'data.topics');

        $this->assertDatabaseHas('trend_research_jobs', [
            'brand_id' => $brand->id,
            'result_state' => 'no_relevant_topics',
        ]);
    }

    /**
     * 13. result_state = 'configuration_required' persisted correctly.
     */
    public function test_result_state_configuration_required(): void
    {
        Http::fake([
            '*/internal/v1/research' => Http::response([
                'topics' => [],
                'sources' => [
                    ['source_key' => 'rss', 'source_name' => 'Configured RSS feeds', 'status' => 'configuration_required', 'result_count' => 0],
                ],
                'result_state' => 'configuration_required',
                'filtered_out_count' => 0,
                'min_relevance_score' => 25,
            ], 200),
        ]);

        $user = User::factory()->create();
        $brand = Brand::factory()->create(['user_id' => $user->id]);

        // configuration_required with no topics → job status is 'partial' (not all error), result_state stored
        $this->actingAs($user, 'web')->postJson("/api/v1/brands/{$brand->id}/trends/research", [
            'days' => 30,
            'sources' => ['rss'],
            'sync' => true,
        ]);

        $this->assertDatabaseHas('trend_research_jobs', [
            'brand_id' => $brand->id,
            'result_state' => 'configuration_required',
        ]);
    }

    /**
     * 14. result_state = 'all_sources_unavailable' stored when all sources error.
     */
    public function test_result_state_all_sources_unavailable(): void
    {
        Http::fake([
            '*/internal/v1/research' => Http::response([
                'topics' => [],
                'sources' => [
                    ['source_key' => 'rss', 'source_name' => 'Configured RSS feeds', 'status' => 'error', 'message' => 'Connection timeout', 'result_count' => 0],
                ],
                'result_state' => 'all_sources_unavailable',
                'filtered_out_count' => 0,
                'min_relevance_score' => 25,
            ], 200),
        ]);

        $user = User::factory()->create();
        $brand = Brand::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user, 'web')->postJson("/api/v1/brands/{$brand->id}/trends/research", [
            'days' => 30,
            'sources' => ['rss'],
            'sync' => true,
        ]);

        $response->assertStatus(502);
        $this->assertDatabaseHas('trend_research_jobs', [
            'brand_id' => $brand->id,
            'status' => 'failed',
        ]);
    }

    /**
     * 15. Deduplication window returns existing completed job without new research.
     */
    public function test_dedup_window_returns_recent_completed_job_without_new_research(): void
    {
        $user = User::factory()->create();
        $brand = Brand::factory()->create(['user_id' => $user->id]);

        $criteria = [
            'categories' => [],
            'geographic_market' => $brand->region,
            'days' => 30,
            'sources' => ['google_trends', 'rss'],
        ];
        sort($criteria['categories']);
        sort($criteria['sources']);
        $criteriaHash = hash('sha256', json_encode($criteria, JSON_THROW_ON_ERROR));

        TrendResearchJob::create([
            'brand_id' => $brand->id,
            'user_id' => $user->id,
            'status' => 'completed',
            'criteria_hash' => $criteriaHash,
            'completed_at' => now()->subMinutes(2), // within 5-min window
            'filters' => $criteria,
        ]);

        Http::fake(); // Should NOT be called

        $response = $this->actingAs($user, 'web')->postJson("/api/v1/brands/{$brand->id}/trends/research", [
            'days' => 30,
            'sources' => ['google_trends', 'rss'],
            'sync' => false,
        ]);

        Http::assertNothingSent();
        $response->assertStatus(200);
        $response->assertJsonPath('message', 'Matching trend research is already active.');
        $this->assertEquals(1, TrendResearchJob::where('brand_id', $brand->id)->count());
    }

    /**
     * 16. Dedup window does NOT match a job older than the window.
     */
    public function test_dedup_window_does_not_match_outside_window(): void
    {
        $user = User::factory()->create();
        $brand = Brand::factory()->create(['user_id' => $user->id]);

        $criteria = [
            'categories' => [],
            'geographic_market' => $brand->region,
            'days' => 30,
            'sources' => ['google_trends', 'rss'],
        ];
        sort($criteria['categories']);
        sort($criteria['sources']);
        $criteriaHash = hash('sha256', json_encode($criteria, JSON_THROW_ON_ERROR));

        TrendResearchJob::create([
            'brand_id' => $brand->id,
            'user_id' => $user->id,
            'status' => 'completed',
            'criteria_hash' => $criteriaHash,
            'completed_at' => now()->subMinutes(10), // outside 5-min window
            'filters' => $criteria,
        ]);

        $this->mockEngineResponse();

        $response = $this->actingAs($user, 'web')->postJson("/api/v1/brands/{$brand->id}/trends/research", [
            'days' => 30,
            'sources' => ['google_trends', 'rss'],
            'sync' => true,
        ]);

        Http::assertSent(fn ($req) => str_contains($req->url(), '/internal/v1/research'));
        $response->assertStatus(200);
    }

    /**
     * 17. force=true bypasses the dedup window and triggers new research.
     */
    public function test_force_true_bypasses_dedup_window(): void
    {
        $user = User::factory()->create();
        $brand = Brand::factory()->create(['user_id' => $user->id]);

        $criteria = [
            'categories' => [],
            'geographic_market' => $brand->region,
            'days' => 30,
            'sources' => ['google_trends', 'rss'],
        ];
        sort($criteria['categories']);
        sort($criteria['sources']);
        $criteriaHash = hash('sha256', json_encode($criteria, JSON_THROW_ON_ERROR));

        TrendResearchJob::create([
            'brand_id' => $brand->id,
            'user_id' => $user->id,
            'status' => 'completed',
            'criteria_hash' => $criteriaHash,
            'completed_at' => now()->subMinutes(1),
            'filters' => $criteria,
        ]);

        $this->mockEngineResponse();

        $response = $this->actingAs($user, 'web')->postJson("/api/v1/brands/{$brand->id}/trends/research", [
            'days' => 30,
            'sources' => ['google_trends', 'rss'],
            'sync' => true,
            'force' => true,
        ]);

        Http::assertSent(fn ($req) => str_contains($req->url(), '/internal/v1/research'));
        $response->assertStatus(200);
    }

    /**
     * 18. brand.category and brand.description are sent in the internal research payload.
     */
    public function test_brand_category_and_description_sent_in_research_payload(): void
    {
        $capturedPayload = null;

        Http::fake([
            '*/internal/v1/research' => function ($request) use (&$capturedPayload) {
                $capturedPayload = $request->data();
                return Http::response([
                    'topics' => [],
                    'sources' => [['source_key' => 'google_trends', 'source_name' => 'Google Trends', 'status' => 'success', 'result_count' => 0]],
                    'result_state' => 'no_relevant_topics',
                    'filtered_out_count' => 0,
                    'min_relevance_score' => 25,
                ], 200);
            },
        ]);

        $user = User::factory()->create();
        $brand = Brand::factory()->create([
            'user_id' => $user->id,
            'category' => 'SaaS',
            'description' => 'A platform for social media scheduling.',
        ]);

        $this->actingAs($user, 'web')->postJson("/api/v1/brands/{$brand->id}/trends/research", [
            'days' => 30,
            'sync' => true,
        ]);

        $this->assertNotNull($capturedPayload, 'Internal research endpoint was not called');
        $this->assertEquals('SaaS', $capturedPayload['brand']['category'] ?? null);
        $this->assertEquals('A platform for social media scheduling.', $capturedPayload['brand']['description'] ?? null);
    }

    /**
     * 19. min_relevance_score is sent in the internal research payload.
     */
    public function test_min_relevance_score_sent_in_research_payload(): void
    {
        $capturedPayload = null;

        Http::fake([
            '*/internal/v1/research' => function ($request) use (&$capturedPayload) {
                $capturedPayload = $request->data();
                return Http::response([
                    'topics' => [],
                    'sources' => [['source_key' => 'google_trends', 'source_name' => 'Google Trends', 'status' => 'success', 'result_count' => 0]],
                    'result_state' => 'no_relevant_topics',
                    'filtered_out_count' => 0,
                    'min_relevance_score' => 25,
                ], 200);
            },
        ]);

        $user = User::factory()->create();
        $brand = Brand::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user, 'web')->postJson("/api/v1/brands/{$brand->id}/trends/research", [
            'days' => 30,
            'sync' => true,
        ]);

        $this->assertNotNull($capturedPayload);
        $this->assertEquals(
            config('services.trends.min_relevance_score', 25),
            $capturedPayload['filters']['min_relevance_score'] ?? null
        );
    }

    /**
     * 20. result_state field is present in the API response payload.
     */
    public function test_result_state_present_in_api_response(): void
    {
        $this->mockEngineResponse();

        $user = User::factory()->create();
        $brand = Brand::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user, 'web')->postJson("/api/v1/brands/{$brand->id}/trends/research", [
            'days' => 30,
            'sync' => true,
        ]);

        $response->assertOk();
        $response->assertJsonStructure(['data' => ['result_state']]);
    }

    /**
     * 21. Flutter Bearer Token response includes result_state field.
     */
    public function test_flutter_bearer_token_receives_result_state_in_response(): void
    {
        $this->mockEngineResponse();

        $user = User::factory()->create();
        $brand = Brand::factory()->create(['user_id' => $user->id]);
        $token = $user->createToken('flutter-test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/brands/{$brand->id}/trends/research", [
                'days' => 30,
                'sync' => true,
            ]);

        $response->assertOk();
        $response->assertJsonStructure(['data' => ['result_state']]);
        $response->assertJsonPath('data.status', 'completed');
    }
}
