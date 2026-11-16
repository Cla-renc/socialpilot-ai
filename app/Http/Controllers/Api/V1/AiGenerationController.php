<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\BrandContentDraft;
use App\Services\AiGenerationService;
use Illuminate\Http\Request;

class AiGenerationController extends Controller
{
    public function __construct(protected AiGenerationService $generationService) {}

    public function generate(Request $request, Brand $brand)
    {
        abort_unless($brand->user_id === $request->user()->id, 403, 'Unauthorized brand access.');

        $validated = $request->validate([
            'provider' => ['nullable', 'string', 'in:internal,openai,gemini,groq,openrouter,ollama'],
            'platform' => ['nullable', 'string', 'in:linkedin,x,instagram,tiktok,facebook'],
            'content_type' => ['nullable', 'string', 'in:caption,carousel,thread,video'],
            'prompt' => ['required', 'string', 'min:10', 'max:2000'],
            'status' => ['nullable', 'string', 'in:draft,approved,scheduled,published'],
            'scheduled_for' => ['nullable', 'date'],
            'title' => ['nullable', 'string', 'max:255'],
        ]);

        $draft = $this->generationService->generate($brand, $request->user(), $validated);

        return response()->json([
            'data' => [
                'id' => $draft->id,
                'brand_id' => $draft->brand_id,
                'title' => $draft->title,
                'platform' => $draft->platform,
                'content_type' => $draft->content_type,
                'provider' => $draft->ai_provider,
                'status' => $draft->status,
                'body' => $draft->body,
                'prompt' => $draft->prompt,
                'hashtags' => $draft->hashtags,
                'metadata' => $draft->metadata,
            ],
        ], 201);
    }
}
