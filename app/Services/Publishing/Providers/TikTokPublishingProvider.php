<?php

namespace App\Services\Publishing\Providers;

use App\Models\PublishingAttempt;
use App\Models\ScheduledPost;
use App\Models\SocialAccount;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/**
 * TikTok Content Posting API provider.
 *
 * Status: IMPLEMENTED — awaiting TikTok Developer app approval for video.publish scope.
 *
 * IMPORTANT: TikTok Content Posting API requires explicit app review and approval before
 * direct publishing can be used. Without approval, only INBOX_PUSH (draft-to-creator) mode
 * is available. This provider reports the actual state of your TikTok app approval level.
 *
 * Required scopes: video.upload, video.publish (requires app review).
 * Alternative without full approval: video.upload with INBOX_PUSH privacy.
 */
class TikTokPublishingProvider extends AbstractPublishingProvider
{
    private const BASE_URL = 'https://open.tiktokapis.com/v2';

    public function platform(): string
    {
        return 'tiktok';
    }

    public function validateAccount(SocialAccount $account): array
    {
        if ($account->status === 'token_expired') {
            return ['valid' => false, 'reason' => 'TikTok access token has expired. Please reconnect your account.'];
        }

        if ($account->status !== 'connected') {
            return ['valid' => false, 'reason' => 'TikTok account is not connected. Status: '.$account->status];
        }

        $token = $this->getAccessToken($account);
        if (! $token) {
            return ['valid' => false, 'reason' => 'No access token found for this TikTok account.'];
        }

        return ['valid' => true, 'reason' => null];
    }

    public function validateContent(ScheduledPost $post, SocialAccount $account, array $options = []): array
    {
        $errors = [];
        $caption = $options['custom_caption'] ?? $post->caption ?? $post->body ?? '';

        if (mb_strlen($caption) > 2200) {
            $errors[] = 'TikTok caption exceeds the 2,200 character limit.';
        }

        $mediaAssets = $post->mediaAssets()->get();
        $firstMedia = $mediaAssets->first();

        if (! $firstMedia || $firstMedia->file_type !== 'video') {
            $errors[] = 'TikTok requires a video file. Text-only posts are not supported through the Content Posting API.';
        }

        return ['valid' => empty($errors), 'errors' => $errors];
    }

    public function publish(ScheduledPost $post, SocialAccount $account, array $options = []): array
    {
        $accessToken = $this->getAccessToken($account);
        if (! $accessToken) {
            return $this->normalizedResponse(
                platform: 'tiktok',
                socialAccountId: $account->id,
                success: false,
                status: 'failed',
                errorCode: 'no_access_token',
                errorMessage: 'No access token available for this TikTok account.',
                isRetryable: false
            );
        }

        // First check creator info to verify publishing capabilities
        $creatorInfoResult = $this->fetchCreatorInfo($accessToken);
        if (! $creatorInfoResult['success']) {
            return $this->normalizedResponse(
                platform: 'tiktok',
                socialAccountId: $account->id,
                success: false,
                status: 'failed',
                errorCode: 'creator_info_failed',
                errorMessage: 'Could not verify TikTok creator account: '.$creatorInfoResult['error'],
                isRetryable: false
            );
        }

        $mediaAssets = $post->mediaAssets()->get();
        $firstMedia = $mediaAssets->first();

        if (! $firstMedia || $firstMedia->file_type !== 'video') {
            return $this->normalizedResponse(
                platform: 'tiktok',
                socialAccountId: $account->id,
                success: false,
                status: 'failed',
                errorCode: 'no_video_media',
                errorMessage: 'TikTok requires a video file to publish.',
                isRetryable: false
            );
        }

        $caption = $options['custom_caption'] ?? $post->caption ?? $post->body ?? '';
        $privacyLevel = $options['privacy_level'] ?? $creatorInfoResult['max_privacy_level_allowed'] ?? 'PUBLIC_TO_EVERYONE';

        return $this->initializeVideoUpload(
            accessToken: $accessToken,
            socialAccountId: $account->id,
            caption: $caption,
            mediaAsset: $firstMedia,
            privacyLevel: $privacyLevel,
            options: $options
        );
    }

    private function fetchCreatorInfo(string $accessToken): array
    {
        $response = Http::withToken($accessToken)
            ->acceptJson()
            ->timeout(15)
            ->post(self::BASE_URL.'/post/publish/creator_info/query/', []);

        if ($response->failed()) {
            $errData = $response->json() ?? [];
            return [
                'success' => false,
                'error' => $errData['error']['message'] ?? 'TikTok creator info query failed (HTTP '.$response->status().')',
            ];
        }

        $data = $response->json('data', []);

        return [
            'success' => true,
            'creator_username' => $data['creator_username'] ?? null,
            'max_video_post_duration_sec' => $data['max_video_post_duration_sec'] ?? 300,
            'max_privacy_level_allowed' => $data['privacy_level_options'][0] ?? 'PUBLIC_TO_EVERYONE',
            'comment_disabled' => $data['comment_disabled'] ?? false,
            'duet_disabled' => $data['duet_disabled'] ?? false,
            'stitch_disabled' => $data['stitch_disabled'] ?? false,
        ];
    }

    private function initializeVideoUpload(
        string $accessToken,
        int $socialAccountId,
        string $caption,
        $mediaAsset,
        string $privacyLevel,
        array $options
    ): array {
        $mediaUrl = Storage::disk(config('services.media.storage_disk', 'local'))->url($mediaAsset->file_path);
        $fileSize = Storage::disk(config('services.media.storage_disk', 'local'))->size($mediaAsset->file_path);

        $initPayload = [
            'post_info' => [
                'title' => $caption,
                'privacy_level' => $privacyLevel,
                'disable_duet' => $options['disable_duet'] ?? false,
                'disable_comment' => $options['disable_comment'] ?? false,
                'disable_stitch' => $options['disable_stitch'] ?? false,
            ],
            'source_info' => [
                'source' => 'PULL_FROM_URL',
                'video_url' => $mediaUrl,
            ],
        ];

        $initResponse = Http::withToken($accessToken)
            ->acceptJson()
            ->timeout(30)
            ->post(self::BASE_URL.'/post/publish/video/init/', $initPayload);

        $initData = $initResponse->json() ?? [];

        if ($initResponse->failed()) {
            $errMsg = $initData['error']['message'] ?? 'TikTok video upload initialization failed.';
            $errCode = $initData['error']['code'] ?? 'tiktok_upload_init_failed';

            // Check if this is a permissions error (app not approved for direct posting)
            $isPermissionsError = in_array($errCode, ['access_token_invalid', 'scope_not_authorized', 'app_id_not_found'], true)
                || str_contains(strtolower($errMsg), 'unauthorized')
                || str_contains(strtolower($errMsg), 'scope');

            return $this->normalizedResponse(
                platform: 'tiktok',
                socialAccountId: $socialAccountId,
                success: false,
                status: 'failed',
                providerResponse: $initData,
                errorCode: (string) $errCode,
                errorMessage: $isPermissionsError
                    ? 'TikTok app does not have permission for direct posting. App review for video.publish scope is required. '.$errMsg
                    : $errMsg,
                isRetryable: $this->isRetryableHttpError($initResponse->status()) && ! $isPermissionsError
            );
        }

        $publishId = $initData['data']['publish_id'] ?? null;

        if (! $publishId) {
            return $this->normalizedResponse(
                platform: 'tiktok',
                socialAccountId: $socialAccountId,
                success: false,
                status: 'failed',
                providerResponse: $initData,
                errorCode: 'no_publish_id',
                errorMessage: 'TikTok did not return a publish_id. The upload may not have been accepted.',
                isRetryable: true
            );
        }

        // TikTok upload initialized — video is being processed, not yet published
        return $this->normalizedResponse(
            platform: 'tiktok',
            socialAccountId: $socialAccountId,
            success: false,
            status: 'processing',
            externalPostId: $publishId,
            providerStatus: 'upload_initialized',
            providerResponse: $initData,
            isRetryable: true
        );
    }

    public function checkStatus(PublishingAttempt $attempt): array
    {
        if ($attempt->status !== 'processing' || ! $attempt->external_post_id) {
            return ['status' => $attempt->status, 'provider_status' => $attempt->provider_status, 'updated' => false];
        }

        $accessToken = $this->getAccessToken($attempt->socialAccount);
        if (! $accessToken) {
            return ['status' => 'failed', 'provider_status' => 'no_token', 'updated' => true];
        }

        $response = Http::withToken($accessToken)
            ->acceptJson()
            ->timeout(15)
            ->post(self::BASE_URL.'/post/publish/status/fetch/', [
                'publish_id' => $attempt->external_post_id,
            ]);

        if ($response->failed()) {
            return ['status' => $attempt->status, 'provider_status' => $attempt->provider_status, 'updated' => false];
        }

        $statusData = $response->json('data', []);
        $processStatus = $statusData['status'] ?? null;

        return match ($processStatus) {
            'PUBLISH_COMPLETE' => [
                'status' => 'published',
                'provider_status' => 'published',
                'published_at' => now()->toIso8601String(),
                'updated' => true,
            ],
            'FAILED' => [
                'status' => 'failed',
                'provider_status' => 'failed',
                'error_message' => $statusData['fail_reason'] ?? 'TikTok video processing failed.',
                'updated' => true,
            ],
            default => [
                'status' => 'processing',
                'provider_status' => 'processing_'.strtolower($processStatus ?? 'unknown'),
                'updated' => false,
            ],
        };
    }

    public function refreshToken(SocialAccount $account): array
    {
        $token = $account->token;
        if (! $token?->refresh_token) {
            return ['success' => false, 'expires_at' => null];
        }

        $settings = config('services.social.tiktok', []);
        if (empty($settings['client_id']) || empty($settings['client_secret'])) {
            return ['success' => false, 'expires_at' => null];
        }

        $response = Http::asForm()
            ->acceptJson()
            ->timeout(15)
            ->post('https://open.tiktokapis.com/v2/oauth/token/', [
                'client_key' => $settings['client_id'],
                'client_secret' => $settings['client_secret'],
                'grant_type' => 'refresh_token',
                'refresh_token' => $token->refresh_token,
            ]);

        if ($response->failed()) {
            return ['success' => false, 'expires_at' => null];
        }

        $data = $response->json('data', $response->json() ?? []);
        $newToken = $data['access_token'] ?? null;
        $expiresIn = $data['expires_in'] ?? null;
        $expiresAt = $expiresIn ? now()->addSeconds((int) $expiresIn) : null;

        if ($newToken && $token) {
            $token->update([
                'access_token' => $newToken,
                'refresh_token' => $data['refresh_token'] ?? $token->refresh_token,
                'expires_at' => $expiresAt,
            ]);
        }

        return ['success' => (bool) $newToken, 'expires_at' => $expiresAt];
    }

    public function revokeConnection(SocialAccount $account): bool
    {
        $accessToken = $this->getAccessToken($account);
        if (! $accessToken) {
            return false;
        }

        $settings = config('services.social.tiktok', []);

        $response = Http::asForm()
            ->acceptJson()
            ->timeout(10)
            ->post('https://open.tiktokapis.com/v2/oauth/revoke/', [
                'client_key' => $settings['client_id'] ?? '',
                'client_secret' => $settings['client_secret'] ?? '',
                'token' => $accessToken,
            ]);

        return $response->successful();
    }

    public function uploadMedia(SocialAccount $account, string $mediaPath, string $mediaType, array $options = []): array
    {
        return ['success' => false, 'media_id' => null, 'error' => 'TikTok uses URL-based pull upload. Use publish() directly.'];
    }
}
