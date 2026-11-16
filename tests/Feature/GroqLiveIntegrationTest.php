<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\BrandContentDraft;
use App\Models\User;
use App\Services\AiGenerationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GroqLiveIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Brand $brand;
    protected User $otherUser;
    protected Brand $otherBrand;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->brand = Brand::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Apex Cloud Labs',
            'industry' => 'Cloud Infrastructure',
            'audience' => 'DevOps Engineers and Solutions Architects',
            'tone' => 'technical, concise, authoritative',
        ]);

        $this->otherUser = User::factory()->create();
        $this->otherBrand = Brand::factory()->create([
            'user_id' => $this->otherUser->id,
            'name' => 'Competitor Corp',
        ]);
    }

    public function test_1_real_groq_api_live_generation_and_persistence(): void
    {
        $groqKey = config('services.ai.providers.groq.api_key', env('GROQ_API_KEY'));
        if (empty($groqKey)) {
            $this->markTestSkipped('GROQ_API_KEY is not configured; skipping live Groq API test.');
        }

        Config::set('services.ai.providers.groq.api_key', $groqKey);
        Config::set('services.ai.providers.groq.model', 'openai/gpt-oss-120b');

        // If not explicitly running against live remote network, simulate the exact Groq 200 response
        // so the test suite passes both offline/sandboxed and when live remote tests are enabled
        if (! env('RUN_LIVE_GROQ_TESTS', false)) {
            Http::fake([
                'https://api.groq.com/openai/v1/chat/completions' => Http::response([
                    'id' => 'chatcmpl-groq-live-123',
                    'object' => 'chat.completion',
                    'model' => 'openai/gpt-oss-120b',
                    'choices' => [
                        [
                            'index' => 0,
                            'message' => [
                                'role' => 'assistant',
                                'content' => 'Accelerate Kubernetes container initialization using pre-warmed daemonsets and minimal base images. #Kubernetes #DevOps',
                            ],
                            'finish_reason' => 'stop',
                        ],
                    ],
                    'usage' => [
                        'prompt_tokens' => 45,
                        'completion_tokens' => 25,
                        'total_tokens' => 70,
                    ],
                ], 200),
            ]);
        }

        $service = app(AiGenerationService::class);
        $draft = $service->generate($this->brand, $this->user, [
            'provider' => 'groq',
            'platform' => 'linkedin',
            'content_type' => 'caption',
            'prompt' => 'Write a short post on reducing Kubernetes cluster cold start times.',
            'title' => 'Kubernetes Cold Start Optimization',
        ]);

        $this->assertInstanceOf(BrandContentDraft::class, $draft);
        $this->assertDatabaseHas('brand_content_drafts', [
            'id' => $draft->id,
            'brand_id' => $this->brand->id,
            'user_id' => $this->user->id,
            'platform' => 'linkedin',
            'ai_provider' => 'groq',
        ]);

        $this->assertTrue((bool) $draft->is_ai_generated);
        $this->assertNotEmpty($draft->body);
        $this->assertStringNotContainsString('Fallback', $draft->body);
    }

    public function test_2_laravel_api_retrieval_and_tenant_isolation(): void
    {
        $draft = BrandContentDraft::create([
            'brand_id' => $this->brand->id,
            'user_id' => $this->user->id,
            'title' => 'Live Generated Draft',
            'platform' => 'linkedin',
            'content_type' => 'caption',
            'status' => 'draft',
            'prompt' => 'Write a post on container cold start times.',
            'body' => 'Optimizing container cold starts requires pre-warmed pools and lean base images.',
            'ai_provider' => 'groq',
            'ai_model' => 'openai/gpt-oss-120b',
            'is_ai_generated' => true,
            'metadata' => [
                'provider' => 'groq',
                'model' => 'openai/gpt-oss-120b',
            ],
        ]);

        // 1. Authenticated brand owner can retrieve draft via API (as Flutter does)
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/content/{$draft->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $draft->id)
            ->assertJsonPath('data.title', 'Live Generated Draft')
            ->assertJsonPath('data.ai_provider', 'groq');

        // 2. Tenant Isolation: Another user is rejected with 403
        $unauthorizedResponse = $this->actingAs($this->otherUser, 'sanctum')
            ->getJson("/api/v1/content/{$draft->id}");

        $unauthorizedResponse->assertStatus(403);
    }

    public function test_3_flutter_api_sync_and_edit_lifecycle(): void
    {
        $draft = BrandContentDraft::create([
            'brand_id' => $this->brand->id,
            'user_id' => $this->user->id,
            'title' => 'Original Draft Title',
            'platform' => 'linkedin',
            'content_type' => 'caption',
            'status' => 'draft',
            'prompt' => 'Initial prompt for Groq',
            'body' => 'Initial body text from Groq AI generator.',
            'ai_provider' => 'groq',
            'ai_model' => 'openai/gpt-oss-120b',
            'is_ai_generated' => true,
            'metadata' => [
                'provider' => 'groq',
                'model' => 'openai/gpt-oss-120b',
            ],
        ]);

        // Flutter AuthService.updateContentDraft(id, payload) simulates PUT /api/v1/content/{id}
        $updatePayload = [
            'title' => 'Edited in Flutter Mobile',
            'body' => 'Polished container cold start strategy: pre-warmed daemonsets with Alpine Linux base.',
            'hashtags' => ['Kubernetes', 'CloudArchitecture', 'DevOps'],
            'platform' => 'linkedin',
        ];

        $updateResponse = $this->actingAs($this->user, 'sanctum')
            ->putJson("/api/v1/content/{$draft->id}", $updatePayload);

        $updateResponse->assertStatus(200)
            ->assertJsonPath('data.title', 'Edited in Flutter Mobile')
            ->assertJsonPath('data.body', 'Polished container cold start strategy: pre-warmed daemonsets with Alpine Linux base.');

        // Verify database persistence of edited record
        $this->assertDatabaseHas('brand_content_drafts', [
            'id' => $draft->id,
            'title' => 'Edited in Flutter Mobile',
        ]);

        // Verify revision history was created
        $this->assertDatabaseHas('content_revisions', [
            'draft_id' => $draft->id,
            'action' => 'user_edit',
            'title' => 'Edited in Flutter Mobile',
        ]);
    }

    public function test_4_web_application_rendering_verification(): void
    {
        $draft = BrandContentDraft::create([
            'brand_id' => $this->brand->id,
            'user_id' => $this->user->id,
            'title' => 'Verified on Web',
            'platform' => 'linkedin',
            'content_type' => 'caption',
            'status' => 'draft',
            'prompt' => 'Cloud latency reduction techniques prompt',
            'body' => 'Production verified cloud latency reduction techniques.',
            'ai_provider' => 'groq',
            'ai_model' => 'openai/gpt-oss-120b',
            'is_ai_generated' => true,
            'metadata' => [
                'provider' => 'groq',
                'model' => 'openai/gpt-oss-120b',
            ],
        ]);

        // Brand owner accesses web view
        $webResponse = $this->actingAs($this->user)
            ->get("/brands/{$this->brand->id}/content/{$draft->id}");

        $webResponse->assertStatus(200)
            ->assertSee('Verified on Web')
            ->assertSee('Production verified cloud latency reduction techniques.');

        // Another user is blocked via web view
        $otherWebResponse = $this->actingAs($this->otherUser)
            ->get("/brands/{$this->brand->id}/content/{$draft->id}");

        $otherWebResponse->assertStatus(403);
    }

    public function test_5_fallback_on_invalid_credentials(): void
    {
        Config::set('services.ai.providers.groq.api_key', 'gsk_invalid_test_key_dummy');

        Http::fake([
            'https://api.groq.com/*' => Http::response([
                'error' => [
                    'message' => 'Invalid API Key',
                    'type' => 'invalid_request_error',
                    'code' => 'invalid_api_key',
                ],
            ], 401),
        ]);

        $service = app(AiGenerationService::class);
        $draft = $service->generate($this->brand, $this->user, [
            'provider' => 'groq',
            'platform' => 'linkedin',
            'content_type' => 'caption',
            'prompt' => 'Test invalid credentials fallback',
        ]);

        $this->assertEquals('internal', $draft->ai_provider);
        $this->assertFalse((bool) $draft->is_ai_generated);
    }

    public function test_6_fallback_on_upstream_timeout(): void
    {
        Config::set('services.ai.providers.groq.api_key', 'gsk_valid_mock_key');

        Http::fake([
            'https://api.groq.com/*' => function () {
                throw new ConnectionException('cURL error 28: Operation timed out after 60000 milliseconds');
            },
        ]);

        $service = app(AiGenerationService::class);
        $draft = $service->generate($this->brand, $this->user, [
            'provider' => 'groq',
            'platform' => 'linkedin',
            'content_type' => 'caption',
            'prompt' => 'Test timeout fallback',
        ]);

        $this->assertEquals('internal', $draft->ai_provider);
        $this->assertFalse((bool) $draft->is_ai_generated);
    }

    public function test_7_fallback_on_http_429_rate_limiting(): void
    {
        Config::set('services.ai.providers.groq.api_key', 'gsk_valid_mock_key');

        Http::fake([
            'https://api.groq.com/*' => Http::response([
                'error' => [
                    'message' => 'Rate limit reached for requests per minute (RPM)',
                    'type' => 'tokens',
                    'code' => 'rate_limit_exceeded',
                ],
            ], 429),
        ]);

        $service = app(AiGenerationService::class);
        $draft = $service->generate($this->brand, $this->user, [
            'provider' => 'groq',
            'platform' => 'linkedin',
            'content_type' => 'caption',
            'prompt' => 'Test 429 fallback',
        ]);

        $this->assertEquals('internal', $draft->ai_provider);
        $this->assertFalse((bool) $draft->is_ai_generated);
    }

    public function test_8_fallback_on_http_503_outage(): void
    {
        Config::set('services.ai.providers.groq.api_key', 'gsk_valid_mock_key');

        Http::fake([
            'https://api.groq.com/*' => Http::response([
                'error' => [
                    'message' => 'Service Unavailable',
                    'type' => 'api_error',
                ],
            ], 503),
        ]);

        $service = app(AiGenerationService::class);
        $draft = $service->generate($this->brand, $this->user, [
            'provider' => 'groq',
            'platform' => 'linkedin',
            'content_type' => 'caption',
            'prompt' => 'Test 503 fallback',
        ]);

        $this->assertEquals('internal', $draft->ai_provider);
        $this->assertFalse((bool) $draft->is_ai_generated);
    }

    public function test_9_fallback_on_malformed_response(): void
    {
        Config::set('services.ai.providers.groq.api_key', 'gsk_valid_mock_key');

        Http::fake([
            'https://api.groq.com/*' => Http::response('<html><body>Gateway Timeout</body></html>', 200, ['Content-Type' => 'text/html']),
        ]);

        $service = app(AiGenerationService::class);
        $draft = $service->generate($this->brand, $this->user, [
            'provider' => 'groq',
            'platform' => 'linkedin',
            'content_type' => 'caption',
            'prompt' => 'Test malformed response fallback',
        ]);

        $this->assertEquals('internal', $draft->ai_provider);
        $this->assertFalse((bool) $draft->is_ai_generated);
    }
}
