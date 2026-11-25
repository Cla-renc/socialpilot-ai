<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\BrandContentDraft;
use App\Models\TrendingTopic;
use App\Models\TrendResearchJob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ContentStudioGenerationFixTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 1. Authenticated browser-session request to the generation API succeeds.
     */
    public function test_authenticated_browser_session_can_generate_content(): void
    {
        $user = User::factory()->create();
        $brand = Brand::factory()->create([
            'user_id' => $user->id,
            'name' => 'Titan Focus',
            'industry' => 'Productivity',
            'niche' => 'Deep Work',
        ]);

        $response = $this->actingAs($user, 'web')->postJson("/api/v1/brands/{$brand->id}/content/generate", [
            'platform' => 'tiktok',
            'objective' => 'education',
            'category' => 'educational',
            'content_format' => 'text_post',
            'instructions' => 'Practical rules for deep work',
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
            'platform' => 'tiktok',
            'objective' => 'education',
        ]);
    }

    /**
     * 2. Unauthenticated browser-session request is rejected with 401.
     */
    public function test_unauthenticated_browser_session_request_returns_401(): void
    {
        $brand = Brand::factory()->create();

        $response = $this->postJson("/api/v1/brands/{$brand->id}/content/generate", [
            'platform' => 'linkedin',
            'objective' => 'engagement',
            'sync' => true,
        ]);

        $response->assertUnauthorized();
    }

    /**
     * 3. Authenticated Sanctum bearer-token request continues to work.
     */
    public function test_authenticated_sanctum_bearer_token_can_generate_content(): void
    {
        $user = User::factory()->create();
        $brand = Brand::factory()->create(['user_id' => $user->id]);

        Sanctum::actingAs($user, ['*']);

        $response = $this->postJson("/api/v1/brands/{$brand->id}/content/generate", [
            'platform' => 'linkedin',
            'objective' => 'engagement',
            'sync' => true,
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.status', 'completed');
    }

    /**
     * 4. Cross-tenant brand access is rejected with 403.
     */
    public function test_cross_tenant_browser_session_request_is_forbidden(): void
    {
        $owner = User::factory()->create();
        $attacker = User::factory()->create();
        $brand = Brand::factory()->create(['user_id' => $owner->id]);

        $response = $this->actingAs($attacker, 'web')->postJson("/api/v1/brands/{$brand->id}/content/generate", [
            'platform' => 'linkedin',
            'objective' => 'engagement',
            'sync' => true,
        ]);

        $response->assertForbidden();
    }

    /**
     * 5. Missing optional trend ID is accepted and treated as null.
     */
    public function test_missing_optional_trend_id_generates_content_without_trend(): void
    {
        $user = User::factory()->create();
        $brand = Brand::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user, 'web')->postJson("/api/v1/brands/{$brand->id}/content/generate", [
            'platform' => 'linkedin',
            'objective' => 'brand_authority',
            'sync' => true,
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('content_generation_jobs', [
            'brand_id' => $brand->id,
            'trend_id' => null,
            'status' => 'completed',
        ]);
    }

    /**
     * 6. Empty-string trend ID ("") is normalized to null and succeeds.
     */
    public function test_empty_string_trend_id_is_normalized_to_null_and_succeeds(): void
    {
        $user = User::factory()->create();
        $brand = Brand::factory()->create([
            'id' => 181643,
            'user_id' => $user->id,
            'name' => 'Bruce Lee Discipline Diaries',
        ]);

        $response = $this->actingAs($user, 'web')->postJson("/api/v1/brands/{$brand->id}/content/generate", [
            'platform' => 'tiktok',
            'objective' => 'education',
            'category' => '',
            'content_format' => 'text_post',
            'trend_id' => '',
            'instructions' => 'A request for educational content about masculinity referencing a named book.',
            'sync' => true,
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.status', 'completed');

        $this->assertDatabaseHas('content_generation_jobs', [
            'brand_id' => $brand->id,
            'trend_id' => null,
            'content_format' => 'text_post',
            'platform' => 'tiktok',
        ]);
    }

    /**
     * 7. Valid integer trend ID is accepted and links trend source.
     */
    public function test_valid_integer_trend_id_associates_trend_source(): void
    {
        $user = User::factory()->create();
        $brand = Brand::factory()->create(['user_id' => $user->id]);
        $researchJob = TrendResearchJob::create([
            'user_id' => $user->id,
            'brand_id' => $brand->id,
            'status' => 'completed',
            'criteria_hash' => 'hash_test_456',
            'filters' => ['days' => 14],
        ]);
        $topic = TrendingTopic::create([
            'brand_id' => $brand->id,
            'research_job_id' => $researchJob->id,
            'title' => 'Sleep optimization principles',
            'normalized_title' => 'sleep optimization principles',
            'relevance_score' => 91,
            'relevance_reason' => 'Recovery relevance',
            'opportunity_score' => 88,
            'researched_at' => now(),
        ]);

        $response = $this->actingAs($user, 'web')->postJson("/api/v1/brands/{$brand->id}/content/generate", [
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

    /**
     * 8. Invalid non-empty trend ID (non-integer string or non-existent integer) fails validation.
     */
    public function test_invalid_non_empty_trend_id_fails_validation(): void
    {
        $user = User::factory()->create();
        $brand = Brand::factory()->create(['user_id' => $user->id]);

        // String that cannot be an integer
        $resString = $this->actingAs($user, 'web')->postJson("/api/v1/brands/{$brand->id}/content/generate", [
            'platform' => 'linkedin',
            'objective' => 'education',
            'trend_id' => 'not-an-integer',
            'sync' => true,
        ]);
        $resString->assertStatus(422);
        $resString->assertJsonValidationErrors(['trend_id']);

        // Non-existent integer
        $resNonExistent = $this->actingAs($user, 'web')->postJson("/api/v1/brands/{$brand->id}/content/generate", [
            'platform' => 'linkedin',
            'objective' => 'education',
            'trend_id' => 999999,
            'sync' => true,
        ]);
        $resNonExistent->assertStatus(422);
        $resNonExistent->assertJsonValidationErrors(['trend_id']);
    }

    /**
     * 9. Generation validation failures return structured 422 JSON errors.
     */
    public function test_generation_validation_failures_return_422(): void
    {
        $user = User::factory()->create();
        $brand = Brand::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user, 'web')->postJson("/api/v1/brands/{$brand->id}/content/generate", [
            'platform' => 'unsupported_platform',
            'objective' => 'unsupported_objective',
            'sync' => true,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['platform', 'objective']);
    }

    /**
     * 10. Frontend Content Studio view includes inline error banner and non-blocking handlers.
     */
    public function test_content_studio_view_renders_inline_error_container_and_safe_handlers(): void
    {
        $user = User::factory()->create();
        $brand = Brand::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user, 'web')->get(route('brands.content.studio', $brand));

        $response->assertOk();
        $response->assertSee('id="studio-error"', false);
        $response->assertSee('id="studio-error-msg"', false);
        $response->assertSee('showInlineError', false);
        $response->assertSee('clearInlineError', false);
        $response->assertDontSee("alert('An unexpected error occurred. Please try again.');", false);
    }

    /**
     * 11. Trend belonging to another brand is rejected with 404.
     */
    public function test_foreign_brand_trend_is_rejected_with_404(): void
    {
        $user = User::factory()->create();
        $brandA = Brand::factory()->create(['user_id' => $user->id]);
        $brandB = Brand::factory()->create(['user_id' => $user->id]);

        $researchJob = TrendResearchJob::create([
            'user_id' => $user->id,
            'brand_id' => $brandB->id,
            'status' => 'completed',
            'criteria_hash' => 'hash_foreign',
            'filters' => ['days' => 14],
        ]);
        $foreignTopic = TrendingTopic::create([
            'brand_id' => $brandB->id,
            'research_job_id' => $researchJob->id,
            'title' => 'Topic from brand B',
            'normalized_title' => 'topic from brand b',
            'relevance_score' => 80,
            'relevance_reason' => 'Keyword match',
            'opportunity_score' => 75,
            'researched_at' => now(),
        ]);

        $response = $this->actingAs($user, 'web')->postJson("/api/v1/brands/{$brandA->id}/content/generate", [
            'platform' => 'linkedin',
            'objective' => 'engagement',
            'trend_id' => $foreignTopic->id,
            'sync' => true,
        ]);

        $response->assertNotFound();
    }

    /**
     * 12. Flutter Bearer Token API contract remains fully operational.
     */
    public function test_flutter_bearer_token_api_contract_preservation(): void
    {
        $user = User::factory()->create();
        $brand = Brand::factory()->create(['user_id' => $user->id]);

        $token = $user->createToken('flutter-mobile')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/brands/{$brand->id}/content/generate", [
                'platform' => 'tiktok',
                'objective' => 'education',
                'content_format' => 'short_script',
                'instructions' => 'Mobile generation test',
                'sync' => true,
            ]);

        $response->assertCreated();
        $response->assertJsonPath('data.status', 'completed');
    }
}
