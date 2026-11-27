<?php

namespace Tests\Feature;

use App\Jobs\RunTrendResearch;
use App\Models\Brand;
use App\Models\TrendRecommendation;
use App\Models\TrendResearchJob;
use App\Models\TrendingTopic;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class TrendIntelligenceWebTest extends TestCase
{
    use RefreshDatabase;

    public function test_brand_trend_page_filters_and_shows_only_owned_topic_evidence(): void
    {
        $user = User::factory()->create();
        $brand = Brand::factory()->for($user)->create();
        $foreign = Brand::factory()->create();
        $job = $this->researchJob($brand, $user);
        $topic = $this->topic($brand, $job, 'A sourced hiking topic');
        $foreignJob = $this->researchJob($foreign, $foreign->user);
        $foreignTopic = $this->topic($foreign, $foreignJob, 'Private topic');

        $this->actingAs($user)
            ->get(route('brands.trends.index', $brand))
            ->assertOk()
            ->assertSee('Trend intelligence')
            ->assertSee('A sourced hiking topic')
            ->assertDontSee('Private topic');
        $this->get(route('brands.trends.show', [$brand, $foreignTopic]))->assertNotFound();
        $this->get(route('brands.trends.show', [$brand, $topic]))
            ->assertOk()
            ->assertSee('Suggested content angles');
    }

    public function test_web_can_queue_research_and_save_manual_by_default_schedule_preferences(): void
    {
        $user = User::factory()->create();
        $brand = Brand::factory()->for($user)->create();
        Queue::fake();

        $this->actingAs($user)
            ->from(route('brands.trends.index', $brand))
            ->post(route('brands.trends.research', $brand), [
                'days' => 30,
                'sources' => ['rss'],
                'geographic_market' => 'Kenya',
            ])
            ->assertRedirect(route('brands.trends.index', $brand));
        Queue::assertPushed(RunTrendResearch::class);
        $this->assertDatabaseHas('trend_research_jobs', [
            'brand_id' => $brand->id,
            'status' => 'queued',
        ]);

        $this->put(route('brands.trends.preferences', $brand), [
            'research_frequency' => 'manual',
            'timezone' => 'UTC',
            'preferred_sources' => ['rss'],
        ])->assertRedirect(route('brands.trends.index', $brand));
        $this->assertDatabaseHas('brand_trend_preferences', [
            'brand_id' => $brand->id,
            'research_frequency' => 'manual',
        ]);
    }

    public function test_web_displays_google_trends_limitation_notice_when_reported(): void
    {
        $user = User::factory()->create();
        $brand = Brand::factory()->for($user)->create();

        $limitationMsg = 'Google Trends RSS reflects current search interest only; historical window 7 days cannot be queried directly via RSS.';

        $job = TrendResearchJob::query()->create([
            'brand_id' => $brand->id,
            'user_id' => $user->id,
            'status' => 'completed',
            'criteria_hash' => hash('sha256', (string) $brand->id),
            'filters' => ['days' => 7],
            'started_at' => now(),
            'completed_at' => now(),
            'source_status' => [
                [
                    'source_key' => 'google_trends',
                    'source_name' => 'Google Trends',
                    'status' => 'completed',
                    'candidate_count' => 5,
                    'message' => $limitationMsg,
                ],
                [
                    'source_key' => 'rss',
                    'source_name' => 'RSS Feeds',
                    'status' => 'completed',
                    'candidate_count' => 10,
                ],
            ],
        ]);

        $this->topic($brand, $job, 'AI Marketing Trends');

        $this->actingAs($user)
            ->get(route('brands.trends.index', $brand))
            ->assertOk()
            ->assertSee('Notice:')
            ->assertSee($limitationMsg);
    }

    public function test_web_does_not_display_limitation_notice_when_none_reported(): void
    {
        $user = User::factory()->create();
        $brand = Brand::factory()->for($user)->create();

        $job = TrendResearchJob::query()->create([
            'brand_id' => $brand->id,
            'user_id' => $user->id,
            'status' => 'completed',
            'criteria_hash' => hash('sha256', (string) $brand->id),
            'filters' => ['days' => 1],
            'started_at' => now(),
            'completed_at' => now(),
            'source_status' => [
                [
                    'source_key' => 'google_trends',
                    'source_name' => 'Google Trends',
                    'status' => 'completed',
                    'candidate_count' => 5,
                ],
            ],
        ]);

        $this->topic($brand, $job, 'Current Trends');

        $this->actingAs($user)
            ->get(route('brands.trends.index', $brand))
            ->assertOk()
            ->assertDontSee('Notice:');
    }

    public function test_web_displays_brand_content_recommendations_and_allows_status_updates(): void
    {
        $user = User::factory()->create();
        $brand = Brand::factory()->for($user)->create(['name' => 'Acme DevOps']);
        $job = $this->researchJob($brand, $user);
        $topic = $this->topic($brand, $job, 'Automated Cloud Scalability');

        $rec = TrendRecommendation::create([
            'brand_id' => $brand->id,
            'trend_topic_id' => $topic->id,
            'cluster_id' => 'cl_scalability_01',
            'score' => 91,
            'relevance_classification' => 'direct',
            'explanation' => [
                'why_it_matters' => 'Core DevOps capability shift.',
                'audience_interest' => 'SREs looking to improve uptime.',
                'timeliness_and_evidence' => 'Documented across 3 sources.',
            ],
            'content_package' => [
                'recommended_angle' => 'Why automated scalability is essential for 2026.',
                'alternative_hooks' => [
                    'Hook 1: The scaling secret',
                    'Hook 2: Stop manual provisioning',
                    'Hook 3: 3 ways to automate',
                ],
                'draft_caption' => 'Full draft caption for Acme DevOps.',
                'suggested_format' => 'short_video',
                'call_to_action' => 'Save this post.',
                'hashtags' => ['#devops', '#cloud'],
            ],
            'target_platform' => 'tiktok',
            'suggested_format' => 'short_video',
            'status' => 'generated',
        ]);

        // 1. Web index displays recommendation section
        $this->actingAs($user)
            ->get(route('brands.trends.index', $brand))
            ->assertOk()
            ->assertSee('AI Brand Content Recommendations')
            ->assertSee('Automated Cloud Scalability')
            ->assertSee('Why automated scalability is essential for 2026.')
            ->assertSee('3 Alternative Hooks')
            ->assertSee('Hook 1: The scaling secret');

        // 2. Web status update action (Save recommendation)
        $this->actingAs($user)
            ->from(route('brands.trends.index', $brand))
            ->post(route('brands.trends.recommendations.status', [$brand, $rec]), [
                'status' => 'saved',
            ])
            ->assertRedirect(route('brands.trends.index', $brand));

        $this->assertDatabaseHas('trend_recommendations', [
            'id' => $rec->id,
            'status' => 'saved',
        ]);

        // 3. Web recommendation feedback action
        $this->actingAs($user)
            ->from(route('brands.trends.index', $brand))
            ->post(route('brands.trends.recommendations.feedback', [$brand, $rec]), [
                'feedback' => 'relevant',
            ])
            ->assertRedirect(route('brands.trends.index', $brand));

        $this->assertDatabaseHas('trend_recommendations', [
            'id' => $rec->id,
            'feedback_status' => 'relevant',
        ]);
    }

    private function researchJob(Brand $brand, User $user): TrendResearchJob
    {
        return TrendResearchJob::query()->create([
            'brand_id' => $brand->id,
            'user_id' => $user->id,
            'status' => 'completed',
            'criteria_hash' => hash('sha256', (string) $brand->id),
            'filters' => ['days' => 30],
            'started_at' => now(),
            'completed_at' => now(),
        ]);
    }

    private function topic(Brand $brand, TrendResearchJob $job, string $title): TrendingTopic
    {
        return TrendingTopic::query()->create([
            'brand_id' => $brand->id,
            'research_job_id' => $job->id,
            'title' => $title,
            'normalized_title' => str($title)->lower()->toString(),
            'category' => 'educational',
            'relevance_score' => 80,
            'relevance_reason' => 'Matches brand context.',
            'opportunity_score' => 70,
            'researched_at' => now(),
        ]);
    }
}
