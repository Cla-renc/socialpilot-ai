<?php

namespace App\Services;

use App\Models\Brand;
use App\Models\BrandContentDraft;
use App\Models\User;

class BrandContentDraftService
{
    public function generateForBrand(Brand $brand, User $user, array $data): BrandContentDraft
    {
        $platform = $data['platform'] ?? 'linkedin';
        $contentType = $data['content_type'] ?? 'caption';
        $prompt = $data['prompt'] ?? 'Create a strong social post that reflects our brand voice.';
        $title = $data['title'] ?? $this->buildTitle($brand, $platform, $contentType);

        $body = $this->buildDraftBody($brand, $platform, $contentType, $prompt);
        $hashtags = $this->extractHashtags($body);

        return BrandContentDraft::query()->create([
            'user_id' => $user->id,
            'brand_id' => $brand->id,
            'title' => $title,
            'platform' => $platform,
            'content_type' => $contentType,
            'status' => $data['status'] ?? 'draft',
            'prompt' => $prompt,
            'body' => $body,
            'hashtags' => $hashtags,
            'metadata' => [
                'brand_voice' => $brand->tone ?? $brand->settings?->brand_voice ?? 'confident and practical',
                'audience' => $brand->audience ?? 'target audience',
                'platform_strategy' => $platform,
            ],
            'scheduled_for' => $data['scheduled_for'] ?? null,
            'ai_provider' => $data['ai_provider'] ?? 'internal',
            'ai_model' => $data['ai_model'] ?? 'socialpilot-lite',
            'is_ai_generated' => false,
        ]);
    }

    protected function buildTitle(Brand $brand, string $platform, string $contentType): string
    {
        return ucfirst($brand->name).' '.$platform.' '.str_replace('_', ' ', $contentType).' idea';
    }

    protected function buildDraftBody(Brand $brand, string $platform, string $contentType, string $prompt): string
    {
        $brandVoice = $brand->tone ?? $brand->settings?->brand_voice ?? 'confident and practical';
        $audience = $brand->audience ?? 'your target audience';
        $hook = match ($platform) {
            'x' => 'Quick insight for teams who want momentum:',
            'instagram' => 'A simple visual story worth saving:',
            'tiktok' => 'Here is the bite-sized version worth watching:',
            'facebook' => 'A practical reminder for your community:',
            default => 'Here is the message worth sharing:',
        };

        $body = "{$hook}\n\n";
        $body .= "{$brand->name} is built for {$audience}, and our approach is {$brandVoice}.\n\n";
        $body .= "Prompt focus: {$prompt}\n\n";
        $body .= "This {$contentType} aims to spark action, keep the message useful, and build trust with an audience that wants clarity over noise.\n\n";
        $body .= "If you want to refine this further, tighten the hook, make it more personal, or add a stronger CTA for the next step.";

        return $body;
    }

    protected function extractHashtags(string $body): array
    {
        preg_match_all('/#([A-Za-z0-9_]+)/', $body, $matches);

        return array_values(array_unique($matches[1]));
    }
}
