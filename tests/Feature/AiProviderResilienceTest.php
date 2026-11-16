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

class AiProviderResilienceTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Brand $brand;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->brand = Brand::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Starlight Media',
            'industry' => 'Tech & Innovation',
            'audience' => 'Founders and Product Leaders',
            'tone' => 'bold, concise, data-driven',
        ]);
    }

    public function test_1_successful_ai_content_generation_with_groq_verified_model(): void
    {
        Config::set('services.ai.providers.groq.api_key', 'test-groq-api-key');
        Config::set('services.ai.providers.groq.model', 'openai/gpt-oss-120b');

        Http::fake([
            'https://api.groq.com/openai/v1/chat/completions' => Http::response([
                'id' => 'chatcmpl-test-123',
                'object' => 'chat.completion',
                'model' => 'openai/gpt-oss-120b',
                'choices' => [
                    [
                        'index' => 0,
                        'message' => [
                            'role' => 'assistant',
                            'content' => "Starlight Media: Simplifying team velocity with automated systems.\n\n#Tech #Innovation #Leadership",
                        ],
                        'finish_reason' => 'stop',
                    ],
                ],
            ], 200),
        ]);

        $service = app(AiGenerationService::class);
        $draft = $service->generate($this->brand, $this->user, [
            'provider' => 'groq',
            'platform' => 'linkedin',
            'content_type' => 'caption',
            'prompt' => 'Announce our new workflow automation system.',
        ]);

        $this->assertTrue($draft->is_ai_generated);
        $this->assertSame('groq', $draft->ai_provider);
        $this->assertSame('openai/gpt-oss-120b', $draft->ai_model);
        $this->assertStringContainsString('Simplifying team velocity', $draft->body);
        $this->assertDatabaseHas('brand_content_drafts', [
            'id' => $draft->id,
            'ai_provider' => 'groq',
            'is_ai_generated' => true,
        ]);
    }

    public function test_2_brand_specific_prompt_construction(): void
    {
        Config::set('services.ai.providers.groq.api_key', 'test-groq-api-key');
        Config::set('services.ai.providers.groq.model', 'openai/gpt-oss-120b');

        Http::fake([
            'https://api.groq.com/openai/v1/chat/completions' => function ($request) {
                $payload = json_decode($request->body(), true);
                $promptText = $payload['messages'][0]['content'];

                $this->assertStringContainsString("Starlight Media", $promptText);
                $this->assertStringContainsString("Founders and Product Leaders", $promptText);
                $this->assertStringContainsString("bold, concise, data-driven", $promptText);

                return Http::response([
                    'choices' => [['message' => ['content' => 'Brand tailored output']]],
                ], 200);
            },
        ]);

        $service = app(AiGenerationService::class);
        $draft = $service->generate($this->brand, $this->user, [
            'provider' => 'groq',
            'platform' => 'linkedin',
            'content_type' => 'caption',
            'prompt' => 'Deliver our quarterly key results.',
        ]);

        $this->assertTrue($draft->is_ai_generated);
    }

    public function test_3_platform_specific_adaptation(): void
    {
        Config::set('services.ai.providers.groq.api_key', 'test-groq-api-key');
        Config::set('services.ai.providers.groq.model', 'openai/gpt-oss-120b');

        Http::fake([
            'https://api.groq.com/openai/v1/chat/completions' => function ($request) {
                $payload = json_decode($request->body(), true);
                $promptText = $payload['messages'][0]['content'];
                $platform = str_contains($promptText, 'linkedin') ? 'linkedin' : (str_contains($promptText, 'instagram') ? 'instagram' : 'x');

                return Http::response([
                    'choices' => [['message' => ['content' => "Custom post for {$platform}"]]],
                ], 200);
            },
        ]);

        $service = app(AiGenerationService::class);
        $draftLinkedin = $service->generate($this->brand, $this->user, [
            'provider' => 'groq',
            'platform' => 'linkedin',
            'content_type' => 'caption',
            'prompt' => 'Product update announcement.',
        ]);
        $this->assertSame('linkedin', $draftLinkedin->platform);
        $this->assertTrue($draftLinkedin->is_ai_generated);

        $draftInstagram = $service->generate($this->brand, $this->user, [
            'provider' => 'groq',
            'platform' => 'instagram',
            'content_type' => 'caption',
            'prompt' => 'Visual lifestyle update.',
        ]);
        $this->assertSame('instagram', $draftInstagram->platform);
        $this->assertTrue($draftInstagram->is_ai_generated);
    }

    public function test_4_provider_timeout_triggers_honest_fallback(): void
    {
        Config::set('services.ai.providers.groq.api_key', 'test-groq-api-key');
        Config::set('services.ai.providers.groq.model', 'openai/gpt-oss-120b');

        Http::fake([
            'https://api.groq.com/openai/v1/chat/completions' => function () {
                throw new ConnectionException("cURL error 28: Operation timed out after 60000 milliseconds");
            },
        ]);

        $service = app(AiGenerationService::class);
        $draft = $service->generate($this->brand, $this->user, [
            'provider' => 'groq',
            'platform' => 'linkedin',
            'content_type' => 'caption',
            'prompt' => 'Write about scaling infrastructure.',
        ]);

        // Honest fallback guarantee
        $this->assertFalse($draft->is_ai_generated);
        $this->assertSame('internal', $draft->ai_provider);
        $this->assertNotEmpty($draft->body);
        $this->assertDatabaseHas('brand_content_drafts', [
            'id' => $draft->id,
            'ai_provider' => 'internal',
            'is_ai_generated' => false,
        ]);
    }

    public function test_5_http_429_rate_limiting_triggers_honest_fallback(): void
    {
        Config::set('services.ai.providers.groq.api_key', 'test-groq-api-key');
        Config::set('services.ai.providers.groq.model', 'openai/gpt-oss-120b');

        Http::fake([
            'https://api.groq.com/openai/v1/chat/completions' => Http::response([
                'error' => [
                    'message' => 'Rate limit reached for model openai/gpt-oss-120b: Limit 30 RPM.',
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
            'prompt' => 'Write an update.',
        ]);

        $this->assertFalse($draft->is_ai_generated);
        $this->assertSame('internal', $draft->ai_provider);
    }

    public function test_6_invalid_credentials_triggers_honest_fallback(): void
    {
        Config::set('services.ai.providers.groq.api_key', 'invalid-key');
        Config::set('services.ai.providers.groq.model', 'openai/gpt-oss-120b');

        Http::fake([
            'https://api.groq.com/openai/v1/chat/completions' => Http::response([
                'error' => ['message' => 'Invalid API Key', 'type' => 'invalid_request_error'],
            ], 401),
        ]);

        $service = app(AiGenerationService::class);
        $draft = $service->generate($this->brand, $this->user, [
            'provider' => 'groq',
            'platform' => 'linkedin',
            'content_type' => 'caption',
            'prompt' => 'Write an update.',
        ]);

        $this->assertFalse($draft->is_ai_generated);
        $this->assertSame('internal', $draft->ai_provider);
    }

    public function test_7_provider_outage_http_503_triggers_honest_fallback(): void
    {
        Config::set('services.ai.providers.groq.api_key', 'test-groq-api-key');
        Config::set('services.ai.providers.groq.model', 'openai/gpt-oss-120b');

        Http::fake([
            'https://api.groq.com/openai/v1/chat/completions' => Http::response([
                'error' => ['message' => 'Service Unavailable'],
            ], 503),
        ]);

        $service = app(AiGenerationService::class);
        $draft = $service->generate($this->brand, $this->user, [
            'provider' => 'groq',
            'platform' => 'linkedin',
            'content_type' => 'caption',
            'prompt' => 'Write an update.',
        ]);

        $this->assertFalse($draft->is_ai_generated);
        $this->assertSame('internal', $draft->ai_provider);
    }

    public function test_8_zero_cost_guarantee_no_paid_providers_or_automatic_silent_fallback(): void
    {
        // 1. When Groq is not configured (empty API key), it must NOT silently use OpenAI or Gemini
        Config::set('services.ai.providers.groq.api_key', null);
        Config::set('services.ai.providers.openai.api_key', null);
        Config::set('services.ai.providers.gemini.api_key', null);

        $service = app(AiGenerationService::class);
        $draft = $service->generate($this->brand, $this->user, [
            'provider' => 'groq',
            'platform' => 'linkedin',
            'content_type' => 'caption',
            'prompt' => 'Write without credentials.',
        ]);

        // Honest internal fallback
        $this->assertFalse($draft->is_ai_generated);
        $this->assertSame('internal', $draft->ai_provider);

        // 2. Confirm OpenAI is disabled
        $this->assertFalse(config('services.ai.providers.openai.enabled'));

        // 3. Confirm Gemini is disabled
        $this->assertFalse(config('services.ai.providers.gemini.enabled'));

        // 4. Confirm default model matches verified active model
        $this->assertSame('openai/gpt-oss-120b', config('services.ai.providers.groq.model'));
    }
}
