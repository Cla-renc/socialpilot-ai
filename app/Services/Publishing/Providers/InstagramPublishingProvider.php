<?php

namespace App\Services\Publishing\Providers;

use App\Models\PublishingAttempt;
use App\Models\ScheduledPost;
use App\Models\SocialAccount;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Instagram publishing provider using the Meta Content Publishing API.
 *
 * Status: IMPLEMENTED — awaiting live Meta app credentials with instagram_content_publish scope.
 *
 * Instagram publishing requires a 2-step process:
 *   1. Create a media container (image/video).
 *   2. Publish the container (once it reaches FINISHED status).
 *
 * A successful container creation is NOT a successful publication.
 */
class InstagramPublishingProvider extends AbstractPublishingProvider
{
    private string $graphVersion;

    // Max poll cycles while waiting for container processing (each cycle ~5s delay)
    private const MAX_CONTAINER_POLL_ATTEMPTS = 6;

    public function __construct()
    {
        $this->graphVersion = config('services.social.instagram.graph_version', 'v22.0');
    }

    public function platform(): string
    {
        return 'instagram';
    }

    public function validateAccount(SocialAccount $account): array
    {
        if ($account->status === 'token_expired') {
            return ['valid' => false, 'reason' => 'Instagram access token has expired. Please reconnect your account.'];
        }

        if ($account->status !== 'connected') {
            return ['valid' => false, 'reason' => 'Instagram account is not connected. Status: '.$account->status];
        }

        if ($account->account_type !== 'professional') {
            return ['valid' => false, 'reason' => 'Only Instagram Professional (Business/Creator) accounts support API publishing.'];
        }

        $token = $this->getAccessToken($account);
        if (! $token) {
            return ['valid' => false, 'reason' => 'No access token found for this Instagram account.'];
        }

        return ['valid' => true, 'reason' => null];
    }

    public function validateContent(ScheduledPost $post, SocialAccount $account, array $options = []): array
    {
        $errors = [];
        $caption = $options['custom_caption'] ?? $post->caption ?? $post->body ?? '';

        if (mb_strlen($caption) > 2200) {
            $errors[] = 'Instagram caption exceeds the 2,200 character limit.';
        }

        $mediaAssets = $post->mediaAssets()->get();
        if ($mediaAssets->isEmpty()) {
            // Instagram requires at least one media item
            $errors[] = 'Instagram requires at least one image or video to publish.';
        }

        return ['valid' => empty($errors), 'errors' => $errors];
    }

    public function publish(ScheduledPost $post, SocialAccount $account, array $options = []): array
    {
        $accessToken = $this->getAccessToken($account);
        if (! $accessToken) {
            return $this->normalizedResponse(
                platform: 'instagram',
                socialAccountId: $account->id,
                success: false,
                status: 'failed',
                errorCode: 'no_access_token',
                errorMessage: 'No access token available for this Instagram account.',
                isRetryable: false
            );
        }

        $igAccountId = $account->provider_account_id;
        $caption = $options['custom_caption'] ?? $post->caption ?? $post->body ?? '';

        try {
            $mediaAssets = $post->mediaAssets()->get();
            $firstMedia = $mediaAssets->first();

            if (! $firstMedia) {
                return $this->normalizedResponse(
                    platform: 'instagram',
                    socialAccountId: $account->id,
                    success: false,
                    status: 'failed',
                    errorCode: 'no_media',
                    errorMessage: 'Instagram requires at least one image or video.',
                    isRetryable: false
                );
            }

            $mediaType = strtoupper($firstMedia->file_type ?? 'IMAGE');
            $mediaUrl = Storage::disk(config('services.media.storage_disk', 'local'))->url($firstMedia->file_path);

            if ($mediaType === 'VIDEO') {
                return $this->publishVideo($igAccountId, $accessToken, $mediaUrl, $caption, $account->id, $options);
            }

            return $this->publishImage($igAccountId, $accessToken, $mediaUrl, $caption, $account->id);
        } catch (\Throwable $e) {
            $this->logError('instagram', 'Publish exception', ['message' => $e->getMessage()]);

            return $this->normalizedResponse(
                platform: 'instagram',
                socialAccountId: $account->id,
                success: false,
                status: 'failed',
                errorCode: 'exception',
                errorMessage: 'Instagram publishing failed: '.$e->getMessage(),
                isRetryable: true
            );
        }
    }

    private function publishImage(string $igAccountId, string $accessToken, string $imageUrl, string $caption, int $socialAccountId): array
    {
        // Step 1: Create container
        $containerResponse = Http::withToken($accessToken)
            ->acceptJson()
            ->timeout(30)
            ->post("https://graph.facebook.com/{$this->graphVersion}/{$igAccountId}/media", [
                'image_url' => $imageUrl,
                'caption' => $caption,
            ]);

        $containerData = $containerResponse->json() ?? [];
        $containerId = $containerData['id'] ?? null;

        if ($containerResponse->failed() || ! $containerId) {
            $errMsg = $containerData['error']['message'] ?? 'Failed to create Instagram media container.';
            $errCode = (string) ($containerData['error']['code'] ?? 'container_creation_failed');

            return $this->normalizedResponse(
                platform: 'instagram',
                socialAccountId: $socialAccountId,
                success: false,
                status: 'failed',
                providerResponse: $containerData,
                errorCode: $errCode,
                errorMessage: $errMsg,
                isRetryable: $this->isRetryableHttpError($containerResponse->status())
            );
        }

        // Step 2: Poll until container is FINISHED
        $finishedContainerId = $this->waitForContainerReady($igAccountId, $containerId, $accessToken);

        if (! $finishedContainerId) {
            return $this->normalizedResponse(
                platform: 'instagram',
                socialAccountId: $socialAccountId,
                success: false,
                status: 'processing',
                externalPostId: $containerId,
                providerStatus: 'container_processing',
                providerResponse: $containerData,
                errorCode: 'container_timeout',
                errorMessage: 'Instagram media container is still processing. Will retry.',
                isRetryable: true
            );
        }

        // Step 3: Publish the container
        return $this->publishContainer($igAccountId, $containerId, $accessToken, $socialAccountId);
    }

    private function publishVideo(string $igAccountId, string $accessToken, string $videoUrl, string $caption, int $socialAccountId, array $options = []): array
    {
        $isReel = (bool) ($options['is_reel'] ?? false);
        $mediaType = $isReel ? 'REELS' : 'VIDEO';

        // Step 1: Create video container
        $containerResponse = Http::withToken($accessToken)
            ->acceptJson()
            ->timeout(30)
            ->post("https://graph.facebook.com/{$this->graphVersion}/{$igAccountId}/media", [
                'media_type' => $mediaType,
                'video_url' => $videoUrl,
                'caption' => $caption,
            ]);

        $containerData = $containerResponse->json() ?? [];
        $containerId = $containerData['id'] ?? null;

        if ($containerResponse->failed() || ! $containerId) {
            $errMsg = $containerData['error']['message'] ?? 'Failed to create Instagram video container.';
            $errCode = (string) ($containerData['error']['code'] ?? 'video_container_creation_failed');

            return $this->normalizedResponse(
                platform: 'instagram',
                socialAccountId: $socialAccountId,
                success: false,
                status: 'failed',
                providerResponse: $containerData,
                errorCode: $errCode,
                errorMessage: $errMsg,
                isRetryable: $this->isRetryableHttpError($containerResponse->status())
            );
        }

        // Video containers need more processing time — return processing status
        // A follow-up CheckPublishingStatusJob will poll and publish when ready
        return $this->normalizedResponse(
            platform: 'instagram',
            socialAccountId: $socialAccountId,
            success: false,
            status: 'processing',
            externalPostId: $containerId,
            providerStatus: 'container_created_awaiting_processing',
            providerResponse: $containerData,
            errorCode: null,
            errorMessage: null,
            isRetryable: true
        );
    }

    /**
     * Poll the container status until FINISHED or timeout.
     */
    private function waitForContainerReady(string $igAccountId, string $containerId, string $accessToken): bool
    {
        for ($attempt = 0; $attempt < self::MAX_CONTAINER_POLL_ATTEMPTS; $attempt++) {
            if ($attempt > 0) {
                sleep(5);
            }

            $response = Http::withToken($accessToken)
                ->acceptJson()
                ->timeout(10)
                ->get("https://graph.facebook.com/{$this->graphVersion}/{$containerId}", [
                    'fields' => 'status_code',
                ]);

            $statusCode = $response->json('status_code');

            if ($statusCode === 'FINISHED') {
                return true;
            }

            if ($statusCode === 'ERROR' || $statusCode === 'EXPIRED') {
                return false;
            }
        }

        return false;
    }

    /**
     * Publish a ready container to Instagram.
     */
    private function publishContainer(string $igAccountId, string $containerId, string $accessToken, int $socialAccountId): array
    {
        $publishResponse = Http::withToken($accessToken)
            ->acceptJson()
            ->timeout(30)
            ->post("https://graph.facebook.com/{$this->graphVersion}/{$igAccountId}/media_publish", [
                'creation_id' => $containerId,
            ]);

        $publishData = $publishResponse->json() ?? [];
        $mediaId = $publishData['id'] ?? null;

        if ($publishResponse->failed() || ! $mediaId) {
            $errMsg = $publishData['error']['message'] ?? 'Instagram publish_container call failed.';
            $errCode = (string) ($publishData['error']['code'] ?? 'publish_container_failed');

            return $this->normalizedResponse(
                platform: 'instagram',
                socialAccountId: $socialAccountId,
                success: false,
                status: 'failed',
                externalPostId: $containerId,
                providerResponse: $publishData,
                errorCode: $errCode,
                errorMessage: $errMsg,
                isRetryable: false // Don't retry after container was already created
            );
        }

        return $this->normalizedResponse(
            platform: 'instagram',
            socialAccountId: $socialAccountId,
            success: true,
            status: 'published',
            externalPostId: $mediaId,
            externalPostUrl: "https://www.instagram.com/p/{$mediaId}/",
            providerStatus: 'published',
            providerResponse: $publishData,
            publishedAt: now()->toIso8601String()
        );
    }

    /**
     * Check status for a processing Instagram container and publish if ready.
     */
    public function checkStatus(PublishingAttempt $attempt): array
    {
        if ($attempt->status !== 'processing') {
            return ['status' => $attempt->status, 'provider_status' => $attempt->provider_status, 'updated' => false];
        }

        $containerId = $attempt->external_post_id;
        if (! $containerId) {
            return ['status' => $attempt->status, 'provider_status' => $attempt->provider_status, 'updated' => false];
        }

        $accessToken = $this->getAccessToken($attempt->socialAccount);
        if (! $accessToken) {
            return ['status' => 'failed', 'provider_status' => 'no_token', 'updated' => true];
        }

        $igAccountId = $attempt->socialAccount->provider_account_id;

        $response = Http::withToken($accessToken)
            ->acceptJson()
            ->timeout(10)
            ->get("https://graph.facebook.com/{$this->graphVersion}/{$containerId}", [
                'fields' => 'status_code',
            ]);

        $statusCode = $response->json('status_code');

        if ($statusCode === 'FINISHED') {
            $result = $this->publishContainer($igAccountId, $containerId, $accessToken, $attempt->social_account_id);

            return [
                'status'            => $result['publishing_status'],
                'provider_status'   => $result['provider_status'],
                'external_post_id'  => $result['external_post_id'],
                'external_post_url' => $result['external_post_url'],
                'published_at'      => $result['published_at'],
                'updated'           => true,
            ];
        }

        if ($statusCode === 'ERROR' || $statusCode === 'EXPIRED') {
            return [
                'status'          => 'failed',
                'provider_status' => 'container_'.$statusCode,
                'error_message'   => 'Instagram media container processing failed with status: '.$statusCode,
                'updated'         => true,
            ];
        }

        return ['status' => 'processing', 'provider_status' => 'container_'.($statusCode ?? 'unknown'), 'updated' => false];
    }

    public function revokeConnection(SocialAccount $account): bool
    {
        return false; // Instagram tokens are revoked via the Facebook/Meta mechanism
    }
}
