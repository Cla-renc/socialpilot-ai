<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\BrandContentDraft;
use App\Models\ContentVariation;
use App\Models\TrendingTopic;
use App\Models\TrendResearchJob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContentStudioWebTest extends TestCase
{
    use RefreshDatabase;

    public function test_content_studio_requires_auth_and_brand_ownership(): void
    {
        $brand = Brand::factory()->create();
        $stranger = User::factory()->create();

        $this->get(route('brands.content.studio', $brand))
            ->assertRedirect(route('login'));

        $this->actingAs($stranger)
            ->get(route('brands.content.studio', $brand))
            ->assertForbidden();
    }

    public function test_content_studio_view_renders_with_brand_and_trends(): void
    {
        $user = User::factory()->create();
        $brand = Brand::factory()->for($user)->create(['name' => 'Peak Athletics']);
        $job = TrendResearchJob::create([
            'user_id' => $user->id,
            'brand_id' => $brand->id,
            'status' => 'completed',
            'criteria_hash' => 'hash123',
            'filters' => ['days' => 30],
        ]);
        $topic = TrendingTopic::create([
            'brand_id' => $brand->id,
            'research_job_id' => $job->id,
            'title' => 'Cold exposure recovery benefits',
            'normalized_title' => 'cold exposure recovery benefits',
            'relevance_score' => 92,
            'relevance_reason' => 'Athletics recovery keywords match',
            'opportunity_score' => 84,
            'researched_at' => now(),
        ]);

        $response = $this->actingAs($user)
            ->get(route('brands.content.studio', $brand))
            ->assertOk()
            ->assertSee('Peak Athletics')
            ->assertSee('Content Studio')
            ->assertSee('Cold exposure recovery benefits');
    }

    public function test_content_library_view_renders_drafts_and_filters(): void
    {
        $user = User::factory()->create();
        $brand = Brand::factory()->for($user)->create();

        $draft1 = BrandContentDraft::create([
            'user_id' => $user->id,
            'brand_id' => $brand->id,
            'title' => '5 Recovery Myths Busted',
            'body' => 'Debunking the top 5 recovery myths.',
            'platform' => 'linkedin',
            'status' => 'draft',
            'prompt' => 'test prompt',
        ]);

        $draft2 = BrandContentDraft::create([
            'user_id' => $user->id,
            'brand_id' => $brand->id,
            'title' => 'Quick Hydration Hack',
            'body' => 'Hydrate with electrolytes.',
            'platform' => 'instagram',
            'status' => 'draft',
            'prompt' => 'test prompt',
        ]);

        $this->actingAs($user)
            ->get(route('brands.content.index', $brand))
            ->assertOk()
            ->assertSee('5 Recovery Myths Busted')
            ->assertSee('Quick Hydration Hack');

        // Filter by platform
        $this->actingAs($user)
            ->get(route('brands.content.index', [$brand, 'platform' => 'linkedin']))
            ->assertOk()
            ->assertSee('5 Recovery Myths Busted')
            ->assertDontSee('Quick Hydration Hack');
    }

    public function test_content_detail_view_renders_draft_variations_and_revisions(): void
    {
        $user = User::factory()->create();
        $brand = Brand::factory()->for($user)->create();

        $draft = BrandContentDraft::create([
            'user_id' => $user->id,
            'brand_id' => $brand->id,
            'title' => 'Elite Daily Routine',
            'body' => 'Master your morning routine to dominate your day.',
            'hook' => 'What if 30 minutes changed your career?',
            'platform' => 'linkedin',
            'status' => 'draft',
            'quality_score' => 85,
            'prompt' => 'test prompt',
        ]);

        ContentVariation::create([
            'user_id' => $user->id,
            'brand_id' => $brand->id,
            'draft_id' => $draft->id,
            'style' => 'bold',
            'hook' => 'Stop waking up without a plan.',
            'body' => 'Bold variation body text.',
            'quality_score' => 87,
        ]);

        $this->actingAs($user)
            ->get(route('brands.content.show', [$brand, $draft]))
            ->assertOk()
            ->assertSee('Elite Daily Routine')
            ->assertSee('What if 30 minutes changed your career?')
            ->assertSee('Stop waking up without a plan.')
            ->assertSee('Quality: 85/100');
    }
}
