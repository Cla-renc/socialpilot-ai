<?php

namespace App\Services\Social;

use App\Services\Social\Contracts\SocialProvider;
use App\Services\Social\Providers\LinkedInSocialProvider;
use App\Services\Social\Providers\MetaSocialProvider;
use App\Services\Social\Providers\TikTokSocialProvider;
use App\Services\Social\Providers\YouTubeSocialProvider;

class SocialProviderManager
{
    /** @return array<string, SocialProvider> */
    public function all(): array
    {
        return [
            'facebook' => new MetaSocialProvider('facebook'),
            'instagram' => new MetaSocialProvider('instagram'),
            'tiktok' => new TikTokSocialProvider,
            'youtube' => new YouTubeSocialProvider,
            'linkedin' => new LinkedInSocialProvider,
        ];
    }

    public function provider(string $key): SocialProvider
    {
        $provider = $this->all()[strtolower($key)] ?? null;
        if (! $provider) {
            abort(404, 'Social provider not supported.');
        }

        return $provider;
    }

    public function statuses(): array
    {
        return collect($this->all())->map(fn (SocialProvider $provider): array => [
            'provider' => $provider->key(),
            'name' => $provider->name(),
            'status' => $provider->configured() ? 'not_connected' : 'configuration_required',
            'configured' => $provider->configured(),
            'connection_available' => $provider->configured(),
            'icon' => match ($provider->key()) {
                'facebook' => 'f',
                'instagram' => '◎',
                'tiktok' => '♪',
                'youtube' => '▶',
                'linkedin' => 'in',
            },
        ])->values()->all();
    }
}
