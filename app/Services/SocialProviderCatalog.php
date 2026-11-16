<?php

namespace App\Services;

use App\Services\Social\SocialProviderManager;

class SocialProviderCatalog
{
    public function statuses(): array
    {
        return app(SocialProviderManager::class)->statuses();
    }

    public function supports(string $provider): bool
    {
        return array_key_exists(strtolower($provider), app(SocialProviderManager::class)->all());
    }
}
