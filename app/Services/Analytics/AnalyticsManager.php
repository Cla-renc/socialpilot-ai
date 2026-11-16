<?php

namespace App\Services\Analytics;

use App\Models\SocialAccount;
use App\Services\Analytics\Providers\FacebookAnalyticsProvider;
use App\Services\Analytics\Providers\InstagramAnalyticsProvider;
use App\Services\Analytics\Providers\LinkedInAnalyticsProvider;
use App\Services\Analytics\Providers\SocialAnalyticsProviderInterface;
use App\Services\Analytics\Providers\TikTokAnalyticsProvider;
use App\Services\Analytics\Providers\YouTubeAnalyticsProvider;
use InvalidArgumentException;

class AnalyticsManager
{
    /**
     * @var array<string, SocialAnalyticsProviderInterface>
     */
    protected array $providers = [];

    public function __construct(
        FacebookAnalyticsProvider $facebook,
        InstagramAnalyticsProvider $instagram,
        TikTokAnalyticsProvider $tiktok,
        YouTubeAnalyticsProvider $youtube,
        LinkedInAnalyticsProvider $linkedin
    ) {
        $this->providers = [
            'facebook'  => $facebook,
            'instagram' => $instagram,
            'tiktok'    => $tiktok,
            'youtube'   => $youtube,
            'linkedin'  => $linkedin,
        ];
    }

    /**
     * Retrieve provider by platform key.
     */
    public function getProvider(string $platform): SocialAnalyticsProviderInterface
    {
        $key = strtolower(trim($platform));
        if (!isset($this->providers[$key])) {
            throw new InvalidArgumentException("Unsupported analytics platform: [{$platform}]. Supported platforms are: " . implode(', ', array_keys($this->providers)));
        }

        return $this->providers[$key];
    }

    /**
     * Retrieve provider for a specific connected SocialAccount.
     */
    public function forAccount(SocialAccount $account): SocialAnalyticsProviderInterface
    {
        return $this->getProvider($account->provider);
    }

    /**
     * List all supported platform keys.
     */
    public function getSupportedPlatforms(): array
    {
        return array_keys($this->providers);
    }

    /**
     * Check if a platform is supported.
     */
    public function isSupported(string $platform): bool
    {
        return isset($this->providers[strtolower(trim($platform))]);
    }

    /**
     * Get platform capabilities and available metrics map across all registered providers.
     */
    public function getAllCapabilities(): array
    {
        $capabilities = [];
        foreach ($this->providers as $platform => $provider) {
            $capabilities[$platform] = [
                'platform'          => $platform,
                'available_metrics' => $provider->getAvailableMetrics(),
                'date_ranges'       => $provider->getSupportedDateRanges(),
            ];
        }

        return $capabilities;
    }
}
