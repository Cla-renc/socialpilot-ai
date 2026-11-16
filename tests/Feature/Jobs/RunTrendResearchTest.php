<?php

namespace Tests\Feature\Jobs;

use App\Jobs\RunTrendResearch;
use App\Models\Brand;
use App\Models\TrendFeedback;
use App\Models\TrendingTopic;
use App\Models\TrendResearchJob;
use App\Models\TrendTopicSource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class RunTrendResearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_engine_response_persists_only_source_backed_topics_and_partial_status(): void
    {
        config([
            'services.trends.engine_url' => 'http://trend-engine:8080',
            'services.trends.internal_token' => 'test-internal-token',
        ]);
        Http::fake([
            'http://trend-engine:8080/internal/v1/research' => Http::response([
                'topics' => [[
                    'title' => 'Local trail safety guidance',
                    'description' => 'An evidence-backed summary.',
                    'summary' => 'An evidence-backed summary.',
                    'content_angles' => ['A practical safety checklist'],
                    'content_angle_status' => 'completed',
                    'category' => 'educational',
                    'relevance_score' => 82,
                    'relevance_reason' => 'Matches saved brand keywords.',
                    'momentum' => null,
                    'momentum_evidence' => null,
                    'geographic_market' => 'Kenya',
                    'opportunity_score' => 74,
                    'sources' => [[
                        'source_key' => 'rss',
                        'source_name' => 'Trail News',
                        'title' => 'Trail safety guidance',
                        'url' => 'https://news.example.test/trail-safety',
                        'published_at' => now()->toIso8601String(),
                        'summary' => 'A published source summary.',
                        'evidence' => 'Observed source text.',
                        'metrics' => [],
                    ]],
                ]],
                'sources' => [[
                    'source_key' => 'rss',
                    'source_name' => 'Configured RSS feeds',
                    'status' => 'partial',
                    'message' => 'Some configured RSS feeds could not be read.',
                    'result_count' => 1,
                ], [
                    'source_key' => 'news_api',
                    'source_name' => 'News API',
                    'status' => 'not_implemented',
                    'message' => 'News API discovery is not implemented.',
                    'result_count' => 0,
                ]],
                'momentum_available' => false,
            ], 200),
        ]);

        $research = $this->researchJob();
        $this->addPriorFeedback($research);
        (new RunTrendResearch($research->id))->handle();

        $this->assertDatabaseHas('trend_research_jobs', [
            'id' => $research->id,
            'status' => 'partial',
        ]);
        $topic = TrendingTopic::query()->where('research_job_id', $research->id)->firstOrFail();
        $this->assertSame(['A practical safety checklist'], $topic->content_angles);
        $this->assertSame('completed', $topic->content_angle_status);
        $this->assertNull($topic->momentum);
        $this->assertDatabaseHas('trend_topic_sources', [
            'trend_topic_id' => $topic->id,
            'source_key' => 'rss',
            'url' => 'https://news.example.test/trail-safety',
        ]);
        $this->assertDatabaseHas('trend_sources', [
            'source_key' => 'rss',
            'connection_status' => 'ready',
        ]);
        Http::assertSent(fn (Request $request): bool => $request->hasHeader('Authorization', 'Bearer test-internal-token')
            && $request['brand']['id'] === $research->brand_id
            && $request['brand']['trend_feedback'][0]['feedback'] === 'relevant'
            && $request['brand']['trend_feedback'][0]['title'] === 'Trail safety topic'
        );
    }

    public function test_job_fails_explicitly_when_no_research_source_succeeds(): void
    {
        config([
            'services.trends.engine_url' => 'http://trend-engine:8080',
            'services.trends.internal_token' => 'test-internal-token',
        ]);
        Http::fake([
            'http://trend-engine:8080/internal/v1/research' => Http::response([
                'topics' => [],
                'sources' => [[
                    'source_key' => 'rss',
                    'source_name' => 'Configured RSS feeds',
                    'status' => 'configuration_required',
                    'message' => 'Configure permitted RSS feed URLs.',
                    'result_count' => 0,
                ]],
            ], 200),
        ]);

        $research = $this->researchJob();
        $job = new RunTrendResearch($research->id);

        try {
            $job->handle();
            $this->fail('The job should reject a response with no successful sources.');
        } catch (RuntimeException $exception) {
            $job->failed($exception);
        }

        $this->assertDatabaseHas('trend_research_jobs', [
            'id' => $research->id,
            'status' => 'failed',
            'error_message' => 'Configure permitted RSS feed URLs.',
        ]);
        $this->assertSame(0, TrendingTopic::query()->count());
        $this->assertSame(0, TrendTopicSource::query()->count());
    }

    public function test_engine_persists_multi_source_citations_with_metrics_and_momentum(): void
    {
        config([
            'services.trends.engine_url' => 'http://trend-engine:8080',
            'services.trends.internal_token' => 'test-internal-token',
        ]);
        Http::fake([
            'http://trend-engine:8080/internal/v1/research' => Http::response([
                'topics' => [[
                    'title' => 'Advanced wilderness navigation equipment',
                    'description' => 'Multi-source synthesized topic.',
                    'summary' => 'Evidence from YouTube, Reddit, and News.',
                    'content_angles' => ['Navigation tutorial', 'Gear breakdown'],
                    'content_angle_status' => 'completed',
                    'category' => 'educational',
                    'relevance_score' => 88,
                    'relevance_reason' => 'Direct fit for hiking niche.',
                    'momentum' => 'growing',
                    'momentum_evidence' => 'Observed 2 times over 4 days; citations increased by 50%.',
                    'geographic_market' => 'Kenya',
                    'opportunity_score' => 84,
                    'sources' => [
                        [
                            'source_key' => 'news_api',
                            'source_name' => 'News API (BBC News)',
                            'title' => 'GPS Tech Advances in National Parks',
                            'url' => 'https://www.bbc.com/news/gps-tech-parks',
                            'published_at' => now()->subDay()->toIso8601String(),
                            'summary' => 'News summary.',
                            'evidence' => 'News evidence.',
                            'metrics' => [],
                        ],
                        [
                            'source_key' => 'reddit',
                            'source_name' => 'Reddit API (r/hiking)',
                            'title' => 'Best offline satellite communicators 2026',
                            'url' => 'https://www.reddit.com/r/hiking/comments/gps_gear',
                            'published_at' => now()->subHours(12)->toIso8601String(),
                            'summary' => 'Reddit selftext.',
                            'evidence' => 'Reddit evidence.',
                            'metrics' => ['likes' => 340, 'comments' => 52],
                        ],
                        [
                            'source_key' => 'youtube',
                            'source_name' => 'YouTube Data API (Outdoor Channel)',
                            'title' => 'Field Testing GPS Units in Kenya',
                            'url' => 'https://www.youtube.com/watch?v=fieldgps123',
                            'published_at' => now()->subHours(6)->toIso8601String(),
                            'summary' => 'Video description.',
                            'evidence' => 'Field test footage with 45,000 views.',
                            'metrics' => ['views' => 45000, 'likes' => 2800, 'comments' => 190],
                        ],
                        [
                            'source_key' => 'google_trends',
                            'source_name' => 'Google Trends (KE)',
                            'title' => 'GPS Navigation Kenya: Daily Search Volume',
                            'url' => 'https://trends.google.com/trends/explore?geo=KE&q=GPS',
                            'published_at' => now()->subHours(2)->toIso8601String(),
                            'summary' => 'Search interest trending.',
                            'evidence' => 'Search volume: 10,000+',
                            'metrics' => ['interest' => 10000],
                        ],
                    ],
                ]],
                'sources' => [
                    ['source_key' => 'news_api', 'source_name' => 'News API', 'status' => 'success', 'result_count' => 1],
                    ['source_key' => 'reddit', 'source_name' => 'Reddit API', 'status' => 'success', 'result_count' => 1],
                    ['source_key' => 'youtube', 'source_name' => 'YouTube Data API', 'status' => 'success', 'result_count' => 1],
                    ['source_key' => 'google_trends', 'source_name' => 'Google Trends', 'status' => 'success', 'result_count' => 1],
                ],
                'momentum_available' => true,
            ], 200),
        ]);

        $research = $this->researchJob();
        $this->addPriorFeedback($research);
        (new RunTrendResearch($research->id))->handle();

        $this->assertDatabaseHas('trend_research_jobs', [
            'id' => $research->id,
            'status' => 'completed',
        ]);
        $topic = TrendingTopic::query()->where('research_job_id', $research->id)->firstOrFail();
        $this->assertSame('growing', $topic->momentum);
        $this->assertStringContainsString('citations increased', $topic->momentum_evidence);
        $this->assertSame(4, $topic->sources()->count());

        $ytSource = $topic->sources()->where('source_key', 'youtube')->firstOrFail();
        $this->assertSame(45000, $ytSource->metrics['views']);
        $this->assertSame(2800, $ytSource->metrics['likes']);

        $redditSource = $topic->sources()->where('source_key', 'reddit')->firstOrFail();
        $this->assertSame(340, $redditSource->metrics['likes']);
        $this->assertSame(52, $redditSource->metrics['comments']);

        $gtSource = $topic->sources()->where('source_key', 'google_trends')->firstOrFail();
        $this->assertSame(10000, $gtSource->metrics['interest']);

        Http::assertSent(function (Request $request): bool {
            return isset($request['brand']['historical_topics'])
                && is_array($request['brand']['historical_topics'])
                && count($request['brand']['historical_topics']) === 1
                && $request['brand']['historical_topics'][0]['title'] === 'Trail safety topic';
        });
    }

    private function researchJob(): TrendResearchJob
    {
        $user = User::factory()->create();
        $brand = Brand::factory()->for($user)->create();

        return TrendResearchJob::query()->create([
            'user_id' => $user->id,
            'brand_id' => $brand->id,
            'status' => 'queued',
            'criteria_hash' => hash('sha256', (string) $brand->id),
            'filters' => [
                'days' => 30,
                'categories' => [],
                'geographic_market' => 'Kenya',
                'sources' => ['rss'],
            ],
        ]);
    }

    private function addPriorFeedback(TrendResearchJob $research): void
    {
        $previousJob = TrendResearchJob::query()->create([
            'user_id' => $research->user_id,
            'brand_id' => $research->brand_id,
            'status' => 'completed',
            'criteria_hash' => hash('sha256', 'previous-'.$research->brand_id),
            'filters' => ['days' => 30, 'sources' => ['rss']],
        ]);
        $topic = TrendingTopic::query()->create([
            'brand_id' => $research->brand_id,
            'research_job_id' => $previousJob->id,
            'title' => 'Trail safety topic',
            'normalized_title' => 'trail safety topic',
            'category' => 'educational',
            'relevance_score' => 80,
            'relevance_reason' => 'Prior test feedback.',
            'opportunity_score' => 70,
            'researched_at' => now(),
        ]);
        TrendFeedback::query()->create([
            'trend_topic_id' => $topic->id,
            'user_id' => $research->user_id,
            'feedback_type' => 'relevant',
        ]);
    }
}
