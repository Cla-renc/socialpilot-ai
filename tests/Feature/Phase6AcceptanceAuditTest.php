<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\BrandContentDraft;
use App\Models\ContentGenerationJob;
use App\Models\ContentRevision;
use App\Models\ContentVariation;
use App\Models\TrendingTopic;
use App\Models\TrendResearchJob;
use App\Models\User;
use App\Services\ContentGenerationService;
use App\Services\ContentImprovementService;
use App\Services\ContentVariationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class Phase6AcceptanceAuditTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Requirement 2: Deterministic Fallback Audit.
     * Ensures transparent fallback, honest reporting of AI failures, no masquerading.
     */
    public function test_fallback_never_masquerades_as_ai_and_failed_providers_are_honestly_reported(): void
    {
        $user = User::factory()->create();
        $brand = Brand::factory()->for($user)->create([
            'name' => 'Iron Mind',
            'industry' => 'Personal Development',
            'niche' => 'Mental Toughness',
        ]);

        // 1. Internal fallback explicitly has is_ai_generated = false and ai_provider = internal
        $internalJob = ContentGenerationJob::create([
            'user_id' => $user->id,
            'brand_id' => $brand->id,
            'platform' => 'linkedin',
            'objective' => 'brand_authority',
            'provider' => 'internal',
            'status' => 'pending',
            'progress' => 0,
        ]);

        $service = app(ContentGenerationService::class);
        $draft = $service->generateForJob($internalJob);

        $this->assertEquals('internal', $draft->ai_provider);
        $this->assertFalse((bool) $draft->is_ai_generated);
        $this->assertEquals('completed', $internalJob->fresh()->status);
        $this->assertEquals(100, $internalJob->fresh()->progress);

        // 2. When an external AI provider fails or is unconfigured, it reports failure honestly
        Http::fake([
            '*/internal/v1/content/generate' => Http::response([
                'detail' => "AI provider 'unconfigured_external_provider' is not available or configured on the server.",
            ], 400),
        ]);

        $failedJob = ContentGenerationJob::create([
            'user_id' => $user->id,
            'brand_id' => $brand->id,
            'platform' => 'linkedin',
            'objective' => 'brand_authority',
            'provider' => 'unconfigured_external_provider',
            'status' => 'pending',
            'progress' => 0,
        ]);

        try {
            $service->generateForJob($failedJob);
            $this->fail('Expected exception for unconfigured provider');
        } catch (\Throwable $e) {
            $this->assertStringContainsString('unconfigured_external_provider', $e->getMessage());
        }

        $this->assertEquals('failed', $failedJob->fresh()->status);
        $this->assertNotNull($failedJob->fresh()->error_message);
        $this->assertDatabaseHas('agent_executions', [
            'brand_id' => $brand->id,
            'agent_key' => 'content_writing',
            'status' => 'failed',
        ]);
    }

    /**
     * Requirement 3: Brand Context Verification.
     * Two brands with distinct identities receive distinct outputs matching their archetype.
     */
    public function test_brand_context_produces_archetype_distinct_outputs(): void
    {
        $user = User::factory()->create();

        // Brand A: Discipline & Personal Development
        $brandA = Brand::factory()->for($user)->create([
            'name' => 'Disciplined Life',
            'industry' => 'Personal Development',
            'niche' => 'Habits & Mindset',
            'audience' => 'High performers',
            'tone' => 'Direct, demanding',
        ]);

        // Brand B: E-Commerce Retail
        $brandB = Brand::factory()->for($user)->create([
            'name' => 'Glow Essentials',
            'industry' => 'Beauty & Skincare',
            'niche' => 'Clean Cosmetics',
            'audience' => 'Young professionals',
            'tone' => 'Friendly, vibrant',
        ]);

        $service = app(ContentGenerationService::class);

        $payloadA = $service->buildBrandPayload($brandA);
        $payloadB = $service->buildBrandPayload($brandB);

        $this->assertEquals('Disciplined Life', $payloadA['name']);
        $this->assertEquals('Personal Development', $payloadA['industry']);
        $this->assertEquals('Glow Essentials', $payloadB['name']);
        $this->assertEquals('Beauty & Skincare', $payloadB['industry']);

        // Generate content for both brands
        $jobA = ContentGenerationJob::create([
            'user_id' => $user->id,
            'brand_id' => $brandA->id,
            'platform' => 'linkedin',
            'objective' => 'brand_authority',
            'provider' => 'internal',
            'status' => 'pending',
            'progress' => 0,
        ]);
        $draftA = $service->generateForJob($jobA);

        $jobB = ContentGenerationJob::create([
            'user_id' => $user->id,
            'brand_id' => $brandB->id,
            'platform' => 'instagram',
            'objective' => 'promotion',
            'provider' => 'internal',
            'status' => 'pending',
            'progress' => 0,
        ]);
        $draftB = $service->generateForJob($jobB);

        // Assert distinct outputs and proper brand scoping
        $this->assertStringContainsString('Disciplined Life', $draftA->title);
        $this->assertStringContainsString('Personal Development', $draftA->body);
        $this->assertStringContainsString('Glow Essentials', $draftB->title);
        $this->assertStringContainsString('Beauty & Skincare', $draftB->body);

        $this->assertStringNotContainsString('Glow Essentials', $draftA->body);
        $this->assertStringNotContainsString('Disciplined Life', $draftB->body);
    }

    /**
     * Requirement 4: Phase 5 Trend Integration.
     * Content generated from persisted trend carries citations and source relationships.
     */
    public function test_trend_based_content_preserves_citations_and_database_relations(): void
    {
        $user = User::factory()->create();
        $brand = Brand::factory()->for($user)->create();

        $researchJob = TrendResearchJob::create([
            'user_id' => $user->id,
            'brand_id' => $brand->id,
            'status' => 'completed',
            'criteria_hash' => 'hash_audit_4',
            'filters' => ['days' => 30],
        ]);

        $topic = TrendingTopic::create([
            'brand_id' => $brand->id,
            'research_job_id' => $researchJob->id,
            'title' => 'Circadian rhythm lighting optimization',
            'normalized_title' => 'circadian rhythm lighting optimization',
            'relevance_score' => 91,
            'relevance_reason' => 'Direct match for wellness habits',
            'opportunity_score' => 85,
            'momentum' => 'growing',
            'momentum_evidence' => 'Search velocity increased 42% over past 30 days',
            'researched_at' => now(),
        ]);

        $job = ContentGenerationJob::create([
            'user_id' => $user->id,
            'brand_id' => $brand->id,
            'platform' => 'linkedin',
            'objective' => 'education',
            'trend_id' => $topic->id,
            'provider' => 'internal',
            'status' => 'pending',
            'progress' => 0,
        ]);

        $service = app(ContentGenerationService::class);
        $draft = $service->generateForJob($job);

        // Verify trend source relationship persisted
        $this->assertDatabaseHas('content_trend_sources', [
            'draft_id' => $draft->id,
            'trending_topic_id' => $topic->id,
        ]);

        $this->assertCount(1, $draft->trendSources);
        $this->assertEquals($topic->id, $draft->trendSources->first()->trending_topic_id);
        $this->assertStringContainsString('Circadian rhythm lighting optimization', $draft->title);
    }

    /**
     * Requirement 5: Platform-Specific Adaptation.
     * Content generated for different platforms has platform-appropriate structure and metadata.
     */
    public function test_platform_adaptation_structures_content_appropriately(): void
    {
        $user = User::factory()->create();
        $brand = Brand::factory()->for($user)->create();
        $service = app(ContentGenerationService::class);

        $platforms = ['linkedin', 'instagram', 'tiktok', 'youtube', 'facebook'];
        $drafts = [];

        foreach ($platforms as $p) {
            $job = ContentGenerationJob::create([
                'user_id' => $user->id,
                'brand_id' => $brand->id,
                'platform' => $p,
                'objective' => 'engagement',
                'provider' => 'internal',
                'status' => 'pending',
                'progress' => 0,
            ]);
            $drafts[$p] = $service->generateForJob($job);
        }

        // Verify each platform draft exists and is saved with correct platform attribute
        foreach ($platforms as $p) {
            $this->assertEquals($p, $drafts[$p]->platform);
            $this->assertNotEmpty($drafts[$p]->hook);
            $this->assertNotEmpty($drafts[$p]->body);
            $this->assertNotEmpty($drafts[$p]->call_to_action);
            $this->assertNotNull($drafts[$p]->quality_score);
        }
    }

    /**
     * Requirement 6: Content Variations and Improvement Operations.
     */
    public function test_variations_and_improvement_operations_preserve_history(): void
    {
        $user = User::factory()->create();
        $brand = Brand::factory()->for($user)->create();

        $draft = BrandContentDraft::create([
            'user_id' => $user->id,
            'brand_id' => $brand->id,
            'title' => 'Mastering Focus',
            'body' => "First paragraph of deep work.\n\nSecond paragraph with tips.\n\nThird paragraph summary.",
            'hook' => 'Why deep work matters',
            'call_to_action' => 'Read more.',
            'platform' => 'linkedin',
            'status' => 'draft',
            'prompt' => 'prompt',
        ]);

        // 1. Generate variations
        $varService = app(ContentVariationService::class);
        $variations = $varService->generateVariations($draft, $user, 2);
        $this->assertCount(2, $variations);

        // 2. Select a variation
        $selectedId = $variations[0]['id'];
        $selectedVariation = ContentVariation::find($selectedId);
        $varService->selectVariation($draft, $selectedVariation, $user);
        $this->assertEquals($selectedId, $draft->fresh()->selected_variation_id);
        $this->assertTrue((bool) $selectedVariation->fresh()->is_selected);

        // 3. Apply improvement operations
        $impService = app(ContentImprovementService::class);
        $improved = $impService->improve($draft, $user, 'shorten');
        $this->assertNotNull($improved->body);

        // Verify revisions recorded
        $revisions = ContentRevision::where('draft_id', $draft->id)->get();
        $this->assertGreaterThanOrEqual(1, $revisions->count());
        $this->assertDatabaseHas('content_revisions', [
            'draft_id' => $draft->id,
            'action' => 'shorten',
        ]);
    }

    /**
     * Requirement 7: Cross-Platform Synchronization.
     * End-to-end sync between Web/API creation, mobile retrieval, edit, variations, selection, and deletion.
     */
    public function test_cross_platform_synchronization_lifecycle(): void
    {
        $user = User::factory()->create();
        $brand = Brand::factory()->for($user)->create(['name' => 'Sync Test Brand']);

        // Step 1: Web / API creates draft via POST /api/v1/brands/{brand}/content/generate
        $createRes = $this->actingAs($user, 'sanctum')->postJson("/api/v1/brands/{$brand->id}/content/generate", [
            'platform' => 'linkedin',
            'objective' => 'awareness',
            'instructions' => 'Synchronized lifecycle draft',
            'sync' => true,
        ]);
        $createRes->assertCreated();
        $draftId = $createRes->json('draft.id');

        // Step 2: Mobile opens and fetches draft list via GET /api/v1/brands/{brand}/content
        $listRes = $this->actingAs($user, 'sanctum')->getJson("/api/v1/brands/{$brand->id}/content");
        $listRes->assertOk();
        $this->assertEquals($draftId, $listRes->json('data.0.id'));

        // Step 3: Mobile edits draft via PUT /api/v1/content/{draft}
        $editRes = $this->actingAs($user, 'sanctum')->putJson("/api/v1/content/{$draftId}", [
            'title' => 'Edited from Mobile App',
            'body' => 'Updated content body text from mobile.',
            'hook' => 'Mobile updated hook',
        ]);
        $editRes->assertOk();

        // Step 4: Web verifies edit via GET /api/v1/content/{draft}
        $getRes = $this->actingAs($user, 'sanctum')->getJson("/api/v1/content/{$draftId}");
        $getRes->assertOk();
        $this->assertEquals('Edited from Mobile App', $getRes->json('data.title'));
        $this->assertEquals('Mobile updated hook', $getRes->json('data.hook'));

        // Step 5: Web generates variations via POST /api/v1/content/{draft}/variations
        $varRes = $this->actingAs($user, 'sanctum')->postJson("/api/v1/content/{$draftId}/variations", ['count' => 2]);
        $varRes->assertCreated();
        $variationId = $varRes->json('data.0.id');

        // Step 6: Mobile lists variations via GET /api/v1/content/{draft}/variations
        $mobileVarRes = $this->actingAs($user, 'sanctum')->getJson("/api/v1/content/{$draftId}/variations");
        $mobileVarRes->assertOk();
        $this->assertGreaterThanOrEqual(2, count($mobileVarRes->json('data')));

        // Step 7: Mobile selects variation via POST /api/v1/content/{draft}/variations/{var}/select
        $selectRes = $this->actingAs($user, 'sanctum')->postJson("/api/v1/content/{$draftId}/variations/{$variationId}/select");
        $selectRes->assertOk();

        // Step 8: Web confirms selection
        $draftCheck = BrandContentDraft::find($draftId);
        $this->assertEquals($variationId, $draftCheck->selected_variation_id);

        // Step 9: Web deletes draft via DELETE /api/v1/content/{draft}
        $delRes = $this->actingAs($user, 'sanctum')->deleteJson("/api/v1/content/{$draftId}");
        $delRes->assertOk();

        // Both verify draft is gone
        $this->assertDatabaseMissing('brand_content_drafts', ['id' => $draftId]);
        $this->actingAs($user, 'sanctum')->getJson("/api/v1/content/{$draftId}")->assertNotFound();
    }

    /**
     * Requirement 8: Security and Ownership Isolation.
     */
    public function test_security_and_ownership_strict_enforcement(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $brand = Brand::factory()->for($owner)->create();

        $draft = BrandContentDraft::create([
            'user_id' => $owner->id,
            'brand_id' => $brand->id,
            'title' => 'Confidential Strategy Draft',
            'body' => 'Secret content.',
            'platform' => 'linkedin',
            'status' => 'draft',
            'prompt' => 'prompt',
        ]);

        $job = ContentGenerationJob::create([
            'user_id' => $owner->id,
            'brand_id' => $brand->id,
            'platform' => 'linkedin',
            'status' => 'processing',
            'progress' => 50,
        ]);

        // Stranger cannot generate for owner's brand
        $this->actingAs($stranger, 'sanctum')
            ->postJson("/api/v1/brands/{$brand->id}/content/generate", ['platform' => 'linkedin', 'objective' => 'awareness'])
            ->assertForbidden();

        // Stranger cannot view owner's draft list
        $this->actingAs($stranger, 'sanctum')
            ->getJson("/api/v1/brands/{$brand->id}/content")
            ->assertForbidden();

        // Stranger cannot view owner's draft details
        $this->actingAs($stranger, 'sanctum')
            ->getJson("/api/v1/content/{$draft->id}")
            ->assertForbidden();

        // Stranger cannot edit owner's draft
        $this->actingAs($stranger, 'sanctum')
            ->putJson("/api/v1/content/{$draft->id}", ['title' => 'Malicious edit'])
            ->assertForbidden();

        // Stranger cannot delete owner's draft
        $this->actingAs($stranger, 'sanctum')
            ->deleteJson("/api/v1/content/{$draft->id}")
            ->assertForbidden();

        // Stranger cannot inspect owner's generation job status
        $this->actingAs($stranger, 'sanctum')
            ->getJson("/api/v1/content/generations/{$job->id}")
            ->assertForbidden();
    }
}
