<?php

namespace App\Services\Analytics\Providers;

use App\Models\SocialAccount;

interface SocialAnalyticsProviderInterface
{
    /**
     * The unique platform identifier (e.g. facebook, instagram, tiktok, youtube, linkedin).
     */
    public function platform(): string;

    /**
     * Retrieve account-level insights (reach, impressions, views, engagement, etc.).
     *
     * @param SocialAccount $account
     * @param array $options [since, until, period, metrics]
     * @return array Normalized array with keys: success, platform, data, errors, metadata
     */
    public function getAccountInsights(SocialAccount $account, array $options = []): array;

    /**
     * Retrieve post-level insights for a published post.
     *
     * @param SocialAccount $account
     * @param string $externalPostId
     * @param array $options
     * @return array Normalized array with keys: success, platform, external_post_id, metrics, errors
     */
    public function getPostInsights(SocialAccount $account, string $externalPostId, array $options = []): array;

    /**
     * Retrieve audience insights (demographics, country, age_gender, devices).
     *
     * @param SocialAccount $account
     * @param array $options
     * @return array Normalized array with keys: success, platform, dimensions, errors
     */
    public function getAudienceInsights(SocialAccount $account, array $options = []): array;

    /**
     * Retrieve current follower count and historical follower stats if supported.
     *
     * @param SocialAccount $account
     * @param array $options
     * @return array Normalized array: success, followers, growth, historical, errors
     */
    public function getFollowerMetrics(SocialAccount $account, array $options = []): array;

    /**
     * List of officially available metrics for this platform and their capabilities.
     *
     * @return array Metric definitions with availability, units, descriptions
     */
    public function getAvailableMetrics(): array;

    /**
     * List of supported reporting periods and date range limits.
     *
     * @return array Supported periods (e.g. day, week, month, lifetime, custom)
     */
    public function getSupportedDateRanges(): array;

    /**
     * Validate whether the account has valid credentials and required analytics permissions.
     *
     * @param SocialAccount $account
     * @return array [valid => bool, scopes_present => array, scopes_missing => array, reason => ?string]
     */
    public function validatePermissions(SocialAccount $account): array;

    /**
     * Normalize platform-specific metric responses into standard SocialPilot AI metric format.
     *
     * @param array $rawMetrics
     * @return array Normalized metrics map
     */
    public function normalizeMetrics(array $rawMetrics): array;
}
