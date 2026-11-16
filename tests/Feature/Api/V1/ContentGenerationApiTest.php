<?php

namespace Tests\Feature\Api\V1;

use App\Models\Brand;
use App\Models\BrandContentDraft;
use App\Models\ContentGenerationJob;
use App\Models\ContentVariation;
use App\Models\TrendingTopic;
use App\Models\TrendResearchJob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContentGenerationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_content_generation_requires_authentication(): void
    {
        $brand = Brand::factory()->create();

        $response = $this->postJson("/api/v1/brands/{$brand->id}/content/generate", [
            'platform' => 'linkedin',
            'objective' => 'engagement',
        ]);

        $response->assertUnauthorized();
    }

    public function test_content_generation_enforces_brand_ownership(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $brand = Brand::factory()->create(['user_id' => $owner->id]);

        $response = $this->actingAs($stranger, 'sanctum')->postJson("/api/v1/brands/{$brand->id}/content/generate", [
            'platform' => 'linkedin',
            'objective' => 'engagement',
        ]);

        $response->assertForbidden();
    }

    public function test_sync_content_generation_persists_draft_initial_revision_and_notifications(): void
    {
        $user = User::factory()->create();
        $brand = Brand::factory()->create([
            'user_id' => $user->id,
            'name' => 'Apex Habits',
            'industry' => 'Personal Development',
            'niche' => 'Mindset',
        ]);

        $response = $this->actingAs($user, 'sanctum')->postJson("/api/v1/brands/{$brand->id}/content/generate", [
            'platform' => 'linkedin',
            'objective' => 'brand_authority',
            'category' => 'educational',
            'instructions' => 'How to build ruthless daily consistency',
            'sync' => true,
        ]);

        $response->assertCreated();
        $response->assertJsonStructure([
            'message',
            'data' => ['id', 'status', 'progress', 'result_draft_id'],
            'draft' => ['id', 'title', 'hook', 'body', 'caption', 'call_to_action', 'hashtags', 'quality_score'],
        ]);

        $this->assertDatabaseHas('brand_content_drafts', [
            'brand_id' => $brand->id,
            'user_id' => $user->id,
            'platform' => 'linkedin',
            'objective' => 'brand_authority',
        ]);

        $draftId = $response->json('draft.id');

        $this->assertDatabaseHas('content_revisions', [
            'draft_id' => $draftId,
            'user_id' => $user->id,
            'revision_number' => 1,
            'action' => 'initial_generation',
        ]);

        $this->assertDatabaseHas('agent_executions', [
            'brand_id' => $brand->id,
            'agent_key' => 'content_writing',
            'status' => 'completed',
        ]);
    }

    public function test_trend_based_content_generation_associates_trend_source(): void
    {
        $user = User::factory()->create();
        $brand = Brand::factory()->create(['user_id' => $user->id]);
        $researchJob = TrendResearchJob::create([
            'user_id' => $user->id,
            'brand_id' => $brand->id,
            'status' => 'completed',
            'criteria_hash' => 'hash_test_123',
            'filters' => ['days' => 30],
        ]);
        $topic = TrendingTopic::create([
            'brand_id' => $brand->id,
            'research_job_id' => $researchJob->id,
            'title' => 'Micro-breaks improve retention',
            'normalized_title' => 'micro-breaks improve retention',
            'relevance_score' => 85,
            'relevance_reason' => 'Matches brand keywords and audience interests',
            'opportunity_score' => 78,
            'researched_at' => now(),
        ]);

        $response = $this->actingAs($user, 'sanctum')->postJson("/api/v1/brands/{$brand->id}/content/generate", [
            'platform' => 'instagram',
            'objective' => 'education',
            'trend_id' => $topic->id,
            'sync' => true,
        ]);

        $response->assertCreated();
        $draftId = $response->json('draft.id');

        $this->assertDatabaseHas('content_trend_sources', [
            'draft_id' => $draftId,
            'trending_topic_id' => $topic->id,
        ]);
    }

    public function test_generation_status_reports_progress_and_enforces_privacy(): void
    {
        $user = User::factory()->create();
        $stranger = User::factory()->create();
        $brand = Brand::factory()->create(['user_id' => $user->id]);

        $job = ContentGenerationJob::create([
            'user_id' => $user->id,
            'brand_id' => $brand->id,
            'status' => 'processing',
            'progress' => 45,
            'platform' => 'tiktok',
        ]);

        // Stranger cannot view job
        $this->actingAs($stranger, 'sanctum')
            ->getJson("/api/v1/content/generations/{$job->id}")
            ->assertForbidden();

        // Owner can view job
        $res = $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/content/generations/{$job->id}")
            ->assertOk();

        $res->assertJsonPath('data.status', 'processing');
        $res->assertJsonPath('data.progress', 45);
    }

    public function test_content_library_search_filter_and_pagination(): void
    {
        $user = User::factory()->create();
        $brand = Brand::factory()->create(['user_id' => $user->id]);

        BrandContentDraft::create([
            'user_id' => $user->id,
            'brand_id' => $brand->id,
            'title' => 'Mastering Morning Routines',
            'body' => 'Routine content details',
            'platform' => 'linkedin',
            'category' => 'educational',
            'status' => 'draft',
            'prompt' => 'prompt',
        ]);

        BrandContentDraft::create([
            'user_id' => $user->id,
            'brand_id' => $brand->id,
            'title' => 'Product Announcement Special',
            'body' => 'Promo content details',
            'platform' => 'instagram',
            'category' => 'promotional',
            'status' => 'draft',
            'prompt' => 'prompt',
        ]);

        // List all
        $listRes = $this->actingAs($user, 'sanctum')->getJson("/api/v1/brands/{$brand->id}/content");
        $listRes->assertOk();
        $this->assertCount(2, $listRes->json('data'));

        // Filter by platform
        $filterRes = $this->actingAs($user, 'sanctum')->getJson("/api/v1/brands/{$brand->id}/content?platform=instagram");
        $filterRes->assertOk();
        $this->assertCount(1, $filterRes->json('data'));
        $this->assertEquals('Product Announcement Special', $filterRes->json('data.0.title'));

        // Search
        $searchRes = $this->actingAs($user, 'sanctum')->getJson("/api/v1/brands/{$brand->id}/content?search=Morning");
        $searchRes->assertOk();
        $this->assertCount(1, $searchRes->json('data'));
    }

    public function test_content_detail_update_and_deletion(): void
    {
        $user = User::factory()->create();
        $brand = Brand::factory()->create(['user_id' => $user->id]);

        $draft = BrandContentDraft::create([
            'user_id' => $user->id,
            'brand_id' => $brand->id,
            'title' => 'Original Title',
            'body' => 'Original body text',
            'hook' => 'Original hook',
            'platform' => 'linkedin',
            'status' => 'draft',
            'prompt' => 'test prompt',
        ]);

        // Details
        $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/content/{$draft->id}")
            ->assertOk()
            ->assertJsonPath('data.title', 'Original Title');

        // Update
        $updateRes = $this->actingAs($user, 'sanctum')
            ->putJson("/api/v1/content/{$draft->id}", [
                'title' => 'Updated Title',
                'body' => 'Updated body text',
                'hook' => 'Updated hook',
            ]);

        $updateRes->assertOk();
        $this->assertEquals('Updated Title', $draft->fresh()->title);

        // Verify revision was saved
        $this->assertDatabaseHas('content_revisions', [
            'draft_id' => $draft->id,
            'action' => 'user_edit',
        ]);

        // Delete
        $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/v1/content/{$draft->id}")
            ->assertOk();

        $this->assertDatabaseMissing('brand_content_drafts', ['id' => $draft->id]);
    }

    public function test_variations_generation_and_selection(): void
    {
        $user = User::factory()->create();
        $brand = Brand::factory()->create(['user_id' => $user->id]);

        $draft = BrandContentDraft::create([
            'user_id' => $user->id,
            'brand_id' => $brand->id,
            'title' => 'Focus Guide',
            'body' => 'Focus on the fundamentals.',
            'hook' => 'Why focus matters:',
            'platform' => 'linkedin',
            'status' => 'draft',
            'prompt' => 'prompt',
        ]);

        // Generate variations
        $genRes = $this->actingAs($user, 'sanctum')->postJson("/api/v1/content/{$draft->id}/variations", [
            'count' => 2,
        ]);
        $genRes->assertCreated();
        $this->assertCount(2, $genRes->json('data'));

        $variationId = $genRes->json('data.0.id');

        // Select variation
        $selRes = $this->actingAs($user, 'sanctum')->postJson("/api/v1/content/{$draft->id}/variations/{$variationId}/select");
        $selRes->assertOk();

        $this->assertEquals($variationId, $draft->fresh()->selected_variation_id);
        $this->assertTrue(ContentVariation::find($variationId)->is_selected);
    }

    public function test_content_improvement_and_revision_history(): void
    {
        $user = User::factory()->create();
        $brand = Brand::factory()->create(['user_id' => $user->id]);

        $draft = BrandContentDraft::create([
            'user_id' => $user->id,
            'brand_id' => $brand->id,
            'title' => 'Draft to improve',
            'body' => "First paragraph.\n\nSecond paragraph.\n\nThird paragraph.",
            'hook' => 'Old hook',
            'platform' => 'linkedin',
            'status' => 'draft',
            'prompt' => 'prompt',
        ]);

        $impRes = $this->actingAs($user, 'sanctum')->postJson("/api/v1/content/{$draft->id}/improve", [
            'operation' => 'shorten',
        ]);

        $impRes->assertOk();

        $revRes = $this->actingAs($user, 'sanctum')->getJson("/api/v1/content/{$draft->id}/revisions");
        $revRes->assertOk();
        $this->assertCount(1, $revRes->json('data'));
        $this->assertEquals('shorten', $revRes->json('data.0.action'));
    }

    public function test_content_settings_get_and_update(): void
    {
        $user = User::factory()->create();
        $brand = Brand::factory()->create(['user_id' => $user->id]);

        $getRes = $this->actingAs($user, 'sanctum')->getJson("/api/v1/brands/{$brand->id}/content-settings");
        $getRes->assertOk();
        $this->assertEquals('linkedin', $getRes->json('data.default_platform'));

        $updateRes = $this->actingAs($user, 'sanctum')->putJson("/api/v1/brands/{$brand->id}/content-settings", [
            'default_platform' => 'instagram',
            'default_objective' => 'education',
            'writing_guidelines' => 'Always include practical step-by-step guidance.',
        ]);

        $updateRes->assertOk();
        $this->assertEquals('instagram', $updateRes->json('data.default_platform'));
        $this->assertEquals('education', $updateRes->json('data.default_objective'));
    }
}
