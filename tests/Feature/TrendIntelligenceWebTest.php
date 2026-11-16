<?php

namespace Tests\Feature;

use App\Jobs\RunTrendResearch;
use App\Models\Brand;
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
