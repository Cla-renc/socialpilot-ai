<?php

namespace Tests\Feature\Api\V1;

use App\Jobs\RunTrendResearch;
use App\Models\Brand;
use App\Models\BrandTrendPreference;
use App\Models\TrendingTopic;
use App\Models\TrendResearchJob;
use App\Models\TrendSource;
use App\Models\TrendTopicSource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class TrendIntelligenceApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_research_is_authenticated_owner_scoped_queued_and_deduplicated(): void
    {
        $this->postJson('/api/v1/brands/1/trends/research')->assertUnauthorized();
        $user = User::factory()->create();
        $brand = Brand::factory()->for($user)->create();
        $foreign = Brand::factory()->create();
        Queue::fake();

        $this->actingAs($user)
            ->postJson("/api/v1/brands/{$foreign->id}/trends/research", ['days' => 7])
            ->assertNotFound();

        $response = $this->postJson("/api/v1/brands/{$brand->id}/trends/research", [
            'days' => 7,
            'geographic_market' => 'Nairobi',
            'sources' => ['rss'],
        ])->assertAccepted()
            ->assertJsonPath('data.status', 'queued');

        $jobId = $response->json('data.id');
        Queue::assertPushed(RunTrendResearch::class, fn (RunTrendResearch $job): bool => $job->researchJobId === $jobId);

        $duplicate = $this->postJson("/api/v1/brands/{$brand->id}/trends/research", [
            'sources' => ['rss'],
            'geographic_market' => 'Nairobi',
            'days' => 7,
        ])->assertOk()->assertJsonPath('data.id', $jobId);

        $this->assertSame(1, TrendResearchJob::query()->count());
        $this->assertSame('queued', $duplicate->json('data.status'));
    }

    public function test_trends_filter_paginate_show_sources_and_feedback_are_owner_scoped(): void
    {
        $user = User::factory()->create();
        $brand = Brand::factory()->for($user)->create();
        $foreign = Brand::factory()->create();
        $job = $this->researchJob($brand, $user);
        $topic = $this->topic($brand, $job, [
            'title' => 'Relevant hiking research',
            'category' => 'educational',
            'relevance_score' => 92,
            'momentum' => null,
            'geographic_market' => 'Kenya',
        ]);
        $source = TrendSource::query()->create([
            'source_key' => 'rss',
            'name' => 'Example RSS',
            'type' => 'rss',
            'connection_status' => 'ready',
        ]);
        TrendTopicSource::query()->create([
            'trend_topic_id' => $topic->id,
            'trend_source_id' => $source->id,
            'source_key' => 'rss',
            'source_name' => 'Example RSS',
            'title' => 'Hiking research',
            'url' => 'https://example.test/hiking',
            'url_hash' => hash('sha256', 'https://example.test/hiking'),
            'evidence' => 'A sourced article excerpt.',
        ]);
        $otherTopic = $this->topic($brand, $job, [
            'title' => 'Low relevance topic',
            'category' => 'general',
            'relevance_score' => 12,
        ]);
        $foreignJob = $this->researchJob($foreign, $foreign->user);
        $foreignTopic = $this->topic($foreign, $foreignJob, ['title' => 'Private foreign topic']);

        $this->actingAs($user)
            ->getJson("/api/v1/brands/{$brand->id}/trends?category=educational&min_relevance=75&source=rss&geographic_market=Kenya&per_page=1")
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.title', 'Relevant hiking research')
            ->assertJsonPath('data.0.source_count', 1)
            ->assertJsonMissing(['title' => 'Private foreign topic']);

        $this->getJson("/api/v1/brands/{$brand->id}/trends/{$topic->id}")
            ->assertOk()
            ->assertJsonPath('data.sources.0.evidence', 'A sourced article excerpt.');
        $this->getJson("/api/v1/brands/{$brand->id}/trends/{$foreignTopic->id}")
            ->assertNotFound();

        $this->postJson("/api/v1/brands/{$brand->id}/trends/{$topic->id}/feedback", [
            'feedback' => 'saved',
        ])->assertOk()->assertJsonPath('data.feedback_type', 'saved');
        $this->getJson("/api/v1/brands/{$brand->id}/trends?saved_only=1")
            ->assertOk()
            ->assertJsonPath('meta.total', 1);
        $this->postJson("/api/v1/brands/{$brand->id}/trends/{$otherTopic->id}/feedback", [
            'feedback' => 'dismissed',
        ])->assertOk();
        $this->getJson("/api/v1/brands/{$brand->id}/trends")
            ->assertOk()
            ->assertJsonMissing(['title' => 'Low relevance topic']);
    }

    public function test_research_status_preferences_and_feedback_validation(): void
    {
        $user = User::factory()->create();
        $brand = Brand::factory()->for($user)->create();
        $foreign = Brand::factory()->create();

        $this->actingAs($user)
            ->getJson("/api/v1/brands/{$brand->id}/trends/status")
            ->assertOk()->assertJsonPath('data', null);
        $this->getJson("/api/v1/brands/{$foreign->id}/trend-preferences")->assertNotFound();
        $this->getJson("/api/v1/brands/{$brand->id}/trend-preferences")
            ->assertOk()
            ->assertJsonPath('data.research_frequency', 'manual');

        $this->putJson("/api/v1/brands/{$brand->id}/trend-preferences", [
            'research_frequency' => 'weekly',
            'research_time' => '08:30',
            'timezone' => 'Africa/Nairobi',
            'preferred_sources' => ['rss'],
            'categories' => ['educational'],
            'geographic_market' => 'Kenya',
        ])->assertOk()->assertJsonPath('data.research_frequency', 'weekly');

        $this->assertSame(
            'weekly',
            BrandTrendPreference::query()->where('brand_id', $brand->id)->value('research_frequency'),
        );
        $this->postJson("/api/v1/brands/{$brand->id}/trends/research", [
            'days' => 9,
        ])->assertUnprocessable();
    }

    public function test_sources_endpoint_returns_all_five_providers_with_correct_status(): void
    {
        $user = User::factory()->create();
        $brand = Brand::factory()->for($user)->create();

        $response = $this->actingAs($user)
            ->getJson("/api/v1/brands/{$brand->id}/trends/sources")
            ->assertOk();

        $data = $response->json('data');
        $this->assertCount(5, $data);

        $keys = collect($data)->pluck('key')->all();
        $this->assertContains('rss', $keys);
        $this->assertContains('news_api', $keys);
        $this->assertContains('reddit', $keys);
        $this->assertContains('youtube', $keys);
        $this->assertContains('google_trends', $keys);

        // When unconfigured, news_api, reddit, youtube return configuration_required (not not_implemented)
        $statuses = collect($data)->keyBy('key');
        $this->assertContains($statuses['news_api']['connection_status'], ['ready', 'configuration_required']);
        $this->assertContains($statuses['reddit']['connection_status'], ['ready', 'configuration_required']);
        $this->assertContains($statuses['youtube']['connection_status'], ['ready', 'configuration_required']);
        $this->assertSame('ready', $statuses['google_trends']['connection_status']);
    }

    private function researchJob(Brand $brand, User $user): TrendResearchJob
    {
        return TrendResearchJob::query()->create([
            'brand_id' => $brand->id,
            'user_id' => $user->id,
            'status' => 'completed',
            'criteria_hash' => hash('sha256', (string) $brand->id),
            'filters' => ['days' => 30, 'sources' => ['rss']],
            'started_at' => now(),
            'completed_at' => now(),
        ]);
    }

    private function topic(Brand $brand, TrendResearchJob $job, array $attributes = []): TrendingTopic
    {
        $title = $attributes['title'] ?? 'Sourced trend topic';

        return TrendingTopic::query()->create([
            'brand_id' => $brand->id,
            'research_job_id' => $job->id,
            'title' => $title,
            'normalized_title' => str($title)->lower()->toString(),
            'description' => 'Based on source records.',
            'category' => 'general',
            'relevance_score' => 50,
            'relevance_reason' => 'Matches documented brand context.',
            'opportunity_score' => 45,
            'researched_at' => now(),
            ...$attributes,
        ]);
    }
}
