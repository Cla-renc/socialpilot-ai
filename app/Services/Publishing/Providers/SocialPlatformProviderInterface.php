<?php

namespace App\Services\Publishing\Providers;

use App\Models\PublishingAttempt;
use App\Models\ScheduledPost;
use App\Models\SocialAccount;

/**
 * Contract every platform publishing provider must implement.
 */
interface SocialPlatformProviderInterface
{
    /**
     * Platform key this provider handles: facebook, instagram, tiktok, youtube, linkedin.
     */
    public function platform(): string;

    /**
     * Validate that the social account is in a connectable/publishable state.
     * Returns ['valid' => bool, 'reason' => string|null].
     */
    public function validateAccount(SocialAccount $account): array;

    /**
     * Validate that the content meets platform requirements before attempting publish.
     * Returns ['valid' => bool, 'errors' => string[]].
     */
    public function validateContent(ScheduledPost $post, SocialAccount $account, array $options = []): array;

    /**
     * Publish the post and return a normalized response array.
     * Must never fabricate a success result.
     * Returns the normalized PublishingResponse array.
     */
    public function publish(ScheduledPost $post, SocialAccount $account, array $options = []): array;

    /**
     * Upload media to the platform if required (e.g. Instagram container creation).
     * Returns ['success' => bool, 'media_id' => string|null, 'error' => string|null].
     */
    public function uploadMedia(SocialAccount $account, string $mediaPath, string $mediaType, array $options = []): array;

    /**
     * Check the current status of a previously initiated publishing attempt.
     * Returns the normalized status update array.
     */
    public function checkStatus(PublishingAttempt $attempt): array;

    /**
     * Attempt to refresh the access token for the given social account.
     * Returns ['success' => bool, 'expires_at' => Carbon|null].
     */
    public function refreshToken(SocialAccount $account): array;

    /**
     * Revoke the connection and tokens for the social account.
     * Returns whether revocation was confirmed by the provider.
     */
    public function revokeConnection(SocialAccount $account): bool;

    /**
     * Whether this provider supports retrying failed attempts.
     */
    public function supportsRetry(): bool;

    /**
     * Max number of retry attempts for transient failures.
     */
    public function maxRetries(): int;
}
