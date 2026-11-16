<?php

namespace App\Services\Publishing\Providers;

use App\Models\PublishingAttempt;
use App\Models\ScheduledPost;
use App\Models\SocialAccount;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Abstract base for all publishing providers.
 * Provides shared helper methods and default implementations.
 */
abstract class AbstractPublishingProvider implements SocialPlatformProviderInterface
{
    /**
     * Build the normalized response array returned by every publish() call.
     */
    protected function normalizedResponse(
        string $platform,
        int $socialAccountId,
        bool $success,
        string $status,
        ?string $externalPostId = null,
        ?string $externalPostUrl = null,
        ?string $providerStatus = null,
        ?array $providerResponse = null,
        ?string $errorCode = null,
        ?string $errorMessage = null,
        ?string $publishedAt = null,
        bool $isRetryable = false
    ): array {
        return [
            'platform'          => $platform,
            'social_account_id' => $socialAccountId,
            'success'           => $success,
            'publishing_status' => $status,
            'external_post_id'  => $externalPostId,
            'external_post_url' => $externalPostUrl,
            'provider_status'   => $providerStatus,
            'provider_response' => $providerResponse ? $this->redactSensitiveData($providerResponse) : null,
            'error_code'        => $errorCode,
            'error_message'     => $errorMessage,
            'published_at'      => $publishedAt,
            'is_retryable'      => $isRetryable,
        ];
    }

    /**
     * Remove sensitive data from provider responses before storage.
     */
    protected function redactSensitiveData(array $data): array
    {
        $sensitiveKeys = ['access_token', 'refresh_token', 'token', 'secret', 'password', 'client_secret'];
        foreach ($sensitiveKeys as $key) {
            if (array_key_exists($key, $data)) {
                $data[$key] = '[REDACTED]';
            }
        }

        return $data;
    }

    /**
     * Classify whether an HTTP status code represents a retryable error.
     */
    protected function isRetryableHttpError(int $statusCode): bool
    {
        return in_array($statusCode, [429, 500, 502, 503, 504], true);
    }

    /**
     * Classify whether a provider error code is a permanent failure.
     */
    protected function isPermanentError(string $errorCode): bool
    {
        $permanentCodes = [
            // Generic permanent errors
            'invalid_content', 'content_policy_violation', 'unsupported_format',
            'unsupported_media_type', 'file_too_large', 'invalid_dimensions',
            // Auth errors (non-retryable)
            'invalid_access_token', 'token_revoked', 'insufficient_permissions',
            'account_restricted', 'account_suspended', 'access_forbidden',
        ];

        return in_array(strtolower($errorCode), $permanentCodes, true);
    }

    /**
     * Extract a usable access token string from a SocialAccount (decrypted by Eloquent cast).
     */
    protected function getAccessToken(SocialAccount $account): ?string
    {
        return $account->token?->access_token;
    }

    /**
     * Log a provider error without leaking credentials.
     */
    protected function logError(string $platform, string $message, array $context = []): void
    {
        $safeContext = $this->redactSensitiveData($context);
        Log::error("Publishing[{$platform}]: {$message}", $safeContext);
    }

    public function supportsRetry(): bool
    {
        return true;
    }

    public function maxRetries(): int
    {
        return 3;
    }

    public function uploadMedia(SocialAccount $account, string $mediaPath, string $mediaType, array $options = []): array
    {
        return ['success' => false, 'media_id' => null, 'error' => 'Media upload not supported by this provider.'];
    }

    public function checkStatus(PublishingAttempt $attempt): array
    {
        return [
            'status'          => $attempt->status,
            'provider_status' => $attempt->provider_status,
            'updated'         => false,
        ];
    }

    public function refreshToken(SocialAccount $account): array
    {
        return ['success' => false, 'expires_at' => null];
    }

    public function revokeConnection(SocialAccount $account): bool
    {
        return false;
    }
}
