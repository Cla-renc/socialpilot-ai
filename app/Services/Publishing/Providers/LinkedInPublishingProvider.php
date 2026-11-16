<?php

namespace App\Services\Publishing\Providers;

use App\Models\PublishingAttempt;
use App\Models\ScheduledPost;
use App\Models\SocialAccount;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/**
 * LinkedIn publishing provider using the LinkedIn Share API and Assets API.
 *
 * Status: IMPLEMENTED — awaiting LinkedIn Developer app credentials.
 *
 * Required OAuth scopes:
 *   - w_member_social  (personal member posts)
 *   - w_organization_social (organization page posts — requires separate approval)
 *   - openid, profile (OpenID connect for identity)
 *
 * Personal member publishing and organization (Company Page) publishing
 * use different author URNs. This provider distinguishes between them
 * via the account_type stored in SocialAccount.metadata.
 */
class LinkedInPublishingProvider extends AbstractPublishingProvider
{
    private const API_URL = 'https://api.linkedin.com/v2';
    private const REFRESH_URL = 'https://www.linkedin.com/oauth/v2/accessToken';

    public function platform(): string
    {
        return 'linkedin';
    }

    public function validateAccount(SocialAccount $account): array
    {
        if ($account->status === 'token_expired') {
            return ['valid' => false, 'reason' => 'LinkedIn access token has expired. Please reconnect your account.'];
        }

        if ($account->status !== 'connected') {
            return ['valid' => false, 'reason' => 'LinkedIn account is not connected. Status: '.$account->status];
        }

        $token = $this->getAccessToken($account);
        if (! $token) {
            return ['valid' => false, 'reason' => 'No access token found for this LinkedIn account.'];
        }

        $authorUrn = $account->metadata['author_urn'] ?? null;
        if (! $authorUrn) {
            return ['valid' => false, 'reason' => 'No LinkedIn author URN available for this account. Please reconnect.'];
        }

        return ['valid' => true, 'reason' => null];
    }

    public function validateContent(ScheduledPost $post, SocialAccount $account, array $options = []): array
    {
        $errors = [];
        $text = $options['custom_caption'] ?? $post->caption ?? $post->body ?? '';

        if (empty(trim($text))) {
            $errors[] = 'LinkedIn post requires text content.';
        }

        if (mb_strlen($text) > 3000) {
            $errors[] = 'LinkedIn post text exceeds the 3,000 character limit.';
        }

        return ['valid' => empty($errors), 'errors' => $errors];
    }

    public function publish(ScheduledPost $post, SocialAccount $account, array $options = []): array
    {
        $accessToken = $this->getAccessToken($account);
        if (! $accessToken) {
            return $this->normalizedResponse(
                platform: 'linkedin',
                socialAccountId: $account->id,
                success: false,
                status: 'failed',
                errorCode: 'no_access_token',
                errorMessage: 'No access token available for this LinkedIn account.',
                isRetryable: false
            );
        }

        $authorUrn = $account->metadata['author_urn'] ?? null;
        if (! $authorUrn) {
            return $this->normalizedResponse(
                platform: 'linkedin',
                socialAccountId: $account->id,
                success: false,
                status: 'failed',
                errorCode: 'no_author_urn',
                errorMessage: 'No LinkedIn author URN found. Please reconnect this account.',
                isRetryable: false
            );
        }

        $text = $options['custom_caption'] ?? $post->caption ?? $post->body ?? '';

        try {
            $mediaAssets = $post->mediaAssets()->get();
            $firstMedia = $mediaAssets->first();

            if ($firstMedia && $firstMedia->file_type === 'image') {
                return $this->publishImagePost($accessToken, $account->id, $authorUrn, $text, $firstMedia);
            } elseif ($firstMedia && $firstMedia->file_type === 'video') {
                return $this->publishVideoPost($accessToken, $account->id, $authorUrn, $text, $firstMedia, $post->title ?? 'Video');
            } else {
                return $this->publishTextPost($accessToken, $account->id, $authorUrn, $text);
            }
        } catch (\Throwable $e) {
            $this->logError('linkedin', 'Publish exception', ['message' => $e->getMessage()]);

            return $this->normalizedResponse(
                platform: 'linkedin',
                socialAccountId: $account->id,
                success: false,
                status: 'failed',
                errorCode: 'exception',
                errorMessage: 'LinkedIn publishing failed: '.$e->getMessage(),
                isRetryable: true
            );
        }
    }

    private function publishTextPost(string $accessToken, int $socialAccountId, string $authorUrn, string $text): array
    {
        $payload = [
            'author' => $authorUrn,
            'lifecycleState' => 'PUBLISHED',
            'specificContent' => [
                'com.linkedin.ugc.ShareContent' => [
                    'shareCommentary' => ['text' => $text],
                    'shareMediaCategory' => 'NONE',
                ],
            ],
            'visibility' => [
                'com.linkedin.ugc.MemberNetworkVisibility' => 'PUBLIC',
            ],
        ];

        $response = Http::withToken($accessToken)
            ->withHeaders(['X-Restli-Protocol-Version' => '2.0.0'])
            ->acceptJson()
            ->timeout(30)
            ->post(self::API_URL.'/ugcPosts', $payload);

        $data = $response->json() ?? [];

        if ($response->failed()) {
            $errMsg = $data['message'] ?? $data['error'] ?? 'LinkedIn text post failed (HTTP '.$response->status().')';
            return $this->normalizedResponse(
                platform: 'linkedin',
                socialAccountId: $socialAccountId,
                success: false,
                status: 'failed',
                providerResponse: $data,
                errorCode: 'linkedin_post_failed',
                errorMessage: $errMsg,
                isRetryable: $this->isRetryableHttpError($response->status())
            );
        }

        $postId = $data['id'] ?? null;
        $encodedPostId = $postId ? urlencode((string) $postId) : null;

        return $this->normalizedResponse(
            platform: 'linkedin',
            socialAccountId: $socialAccountId,
            success: true,
            status: 'published',
            externalPostId: $postId,
            externalPostUrl: $encodedPostId ? "https://www.linkedin.com/feed/update/{$encodedPostId}/" : null,
            providerStatus: 'published',
            providerResponse: $data,
            publishedAt: now()->toIso8601String()
        );
    }

    private function publishImagePost(string $accessToken, int $socialAccountId, string $authorUrn, string $text, $mediaAsset): array
    {
        // Step 1: Register image upload
        $registerResponse = Http::withToken($accessToken)
            ->withHeaders(['X-Restli-Protocol-Version' => '2.0.0'])
            ->acceptJson()
            ->timeout(15)
            ->post(self::API_URL.'/assets?action=registerUpload', [
                'registerUploadRequest' => [
                    'recipes' => ['urn:li:digitalmediaRecipe:feedshare-image'],
                    'owner' => $authorUrn,
                    'serviceRelationships' => [
                        ['relationshipType' => 'OWNER', 'identifier' => 'urn:li:userGeneratedContent'],
                    ],
                ],
            ]);

        if ($registerResponse->failed()) {
            return $this->normalizedResponse(
                platform: 'linkedin',
                socialAccountId: $socialAccountId,
                success: false,
                status: 'failed',
                providerResponse: $registerResponse->json() ?? [],
                errorCode: 'image_register_failed',
                errorMessage: 'Failed to register LinkedIn image upload.',
                isRetryable: true
            );
        }

        $registerData = $registerResponse->json();
        $uploadUrl = $registerData['value']['uploadMechanism']['com.linkedin.digitalmedia.uploading.MediaUploadHttpRequest']['uploadUrl'] ?? null;
        $assetUrn = $registerData['value']['asset'] ?? null;

        if (! $uploadUrl || ! $assetUrn) {
            return $this->normalizedResponse(
                platform: 'linkedin',
                socialAccountId: $socialAccountId,
                success: false,
                status: 'failed',
                errorCode: 'no_upload_url',
                errorMessage: 'LinkedIn did not return upload URL or asset URN.',
                isRetryable: true
            );
        }

        // Step 2: Upload the image binary
        $filePath = Storage::disk(config('services.media.storage_disk', 'local'))->path($mediaAsset->file_path);
        if (! file_exists($filePath)) {
            return $this->normalizedResponse(
                platform: 'linkedin',
                socialAccountId: $socialAccountId,
                success: false,
                status: 'failed',
                errorCode: 'media_file_not_found',
                errorMessage: 'Image file not found on storage.',
                isRetryable: false
            );
        }

        $uploadResponse = Http::withToken($accessToken)
            ->withHeaders(['Content-Type' => $mediaAsset->mime_type ?? 'image/jpeg'])
            ->timeout(60)
            ->put($uploadUrl, file_get_contents($filePath));

        if ($uploadResponse->failed() && $uploadResponse->status() !== 201) {
            return $this->normalizedResponse(
                platform: 'linkedin',
                socialAccountId: $socialAccountId,
                success: false,
                status: 'failed',
                errorCode: 'image_upload_failed',
                errorMessage: 'LinkedIn image file upload failed (HTTP '.$uploadResponse->status().').',
                isRetryable: $this->isRetryableHttpError($uploadResponse->status())
            );
        }

        // Step 3: Post with the asset
        $postPayload = [
            'author' => $authorUrn,
            'lifecycleState' => 'PUBLISHED',
            'specificContent' => [
                'com.linkedin.ugc.ShareContent' => [
                    'shareCommentary' => ['text' => $text],
                    'shareMediaCategory' => 'IMAGE',
                    'media' => [
                        [
                            'status' => 'READY',
                            'description' => ['text' => $text],
                            'media' => $assetUrn,
                            'title' => ['text' => 'Image'],
                        ],
                    ],
                ],
            ],
            'visibility' => [
                'com.linkedin.ugc.MemberNetworkVisibility' => 'PUBLIC',
            ],
        ];

        $postResponse = Http::withToken($accessToken)
            ->withHeaders(['X-Restli-Protocol-Version' => '2.0.0'])
            ->acceptJson()
            ->timeout(30)
            ->post(self::API_URL.'/ugcPosts', $postPayload);

        $postData = $postResponse->json() ?? [];

        if ($postResponse->failed()) {
            $errMsg = $postData['message'] ?? 'LinkedIn image post failed.';
            return $this->normalizedResponse(
                platform: 'linkedin',
                socialAccountId: $socialAccountId,
                success: false,
                status: 'failed',
                providerResponse: $postData,
                errorCode: 'linkedin_image_post_failed',
                errorMessage: $errMsg,
                isRetryable: $this->isRetryableHttpError($postResponse->status())
            );
        }

        $postId = $postData['id'] ?? null;
        $encodedPostId = $postId ? urlencode((string) $postId) : null;

        return $this->normalizedResponse(
            platform: 'linkedin',
            socialAccountId: $socialAccountId,
            success: true,
            status: 'published',
            externalPostId: $postId,
            externalPostUrl: $encodedPostId ? "https://www.linkedin.com/feed/update/{$encodedPostId}/" : null,
            providerStatus: 'published',
            providerResponse: $postData,
            publishedAt: now()->toIso8601String()
        );
    }

    private function publishVideoPost(string $accessToken, int $socialAccountId, string $authorUrn, string $text, $mediaAsset, string $title): array
    {
        // LinkedIn video upload is a multi-step process similar to images
        // Step 1: Register upload
        $registerResponse = Http::withToken($accessToken)
            ->withHeaders(['X-Restli-Protocol-Version' => '2.0.0'])
            ->acceptJson()
            ->timeout(15)
            ->post(self::API_URL.'/assets?action=registerUpload', [
                'registerUploadRequest' => [
                    'recipes' => ['urn:li:digitalmediaRecipe:feedshare-video'],
                    'owner' => $authorUrn,
                    'serviceRelationships' => [
                        ['relationshipType' => 'OWNER', 'identifier' => 'urn:li:userGeneratedContent'],
                    ],
                ],
            ]);

        if ($registerResponse->failed()) {
            return $this->normalizedResponse(
                platform: 'linkedin',
                socialAccountId: $socialAccountId,
                success: false,
                status: 'failed',
                errorCode: 'video_register_failed',
                errorMessage: 'Failed to register LinkedIn video upload.',
                isRetryable: true
            );
        }

        $registerData = $registerResponse->json();
        $uploadUrl = $registerData['value']['uploadMechanism']['com.linkedin.digitalmedia.uploading.MediaUploadHttpRequest']['uploadUrl'] ?? null;
        $assetUrn = $registerData['value']['asset'] ?? null;

        if (! $uploadUrl || ! $assetUrn) {
            return $this->normalizedResponse(
                platform: 'linkedin',
                socialAccountId: $socialAccountId,
                success: false,
                status: 'failed',
                errorCode: 'no_video_upload_url',
                errorMessage: 'LinkedIn did not return video upload URL.',
                isRetryable: true
            );
        }

        $filePath = Storage::disk(config('services.media.storage_disk', 'local'))->path($mediaAsset->file_path);
        if (! file_exists($filePath)) {
            return $this->normalizedResponse(
                platform: 'linkedin',
                socialAccountId: $socialAccountId,
                success: false,
                status: 'failed',
                errorCode: 'media_file_not_found',
                errorMessage: 'Video file not found on storage.',
                isRetryable: false
            );
        }

        // Upload video
        $uploadResponse = Http::withToken($accessToken)
            ->withHeaders(['Content-Type' => $mediaAsset->mime_type ?? 'video/mp4'])
            ->timeout(180)
            ->put($uploadUrl, file_get_contents($filePath));

        if ($uploadResponse->failed() && $uploadResponse->status() !== 201) {
            return $this->normalizedResponse(
                platform: 'linkedin',
                socialAccountId: $socialAccountId,
                success: false,
                status: 'failed',
                errorCode: 'video_upload_failed',
                errorMessage: 'LinkedIn video file upload failed (HTTP '.$uploadResponse->status().').',
                isRetryable: $this->isRetryableHttpError($uploadResponse->status())
            );
        }

        // Post with video asset (video may still be processing on LinkedIn's side)
        $postPayload = [
            'author' => $authorUrn,
            'lifecycleState' => 'PUBLISHED',
            'specificContent' => [
                'com.linkedin.ugc.ShareContent' => [
                    'shareCommentary' => ['text' => $text],
                    'shareMediaCategory' => 'VIDEO',
                    'media' => [
                        [
                            'status' => 'READY',
                            'description' => ['text' => $text],
                            'media' => $assetUrn,
                            'title' => ['text' => $title],
                        ],
                    ],
                ],
            ],
            'visibility' => [
                'com.linkedin.ugc.MemberNetworkVisibility' => 'PUBLIC',
            ],
        ];

        $postResponse = Http::withToken($accessToken)
            ->withHeaders(['X-Restli-Protocol-Version' => '2.0.0'])
            ->acceptJson()
            ->timeout(30)
            ->post(self::API_URL.'/ugcPosts', $postPayload);

        $postData = $postResponse->json() ?? [];

        if ($postResponse->failed()) {
            return $this->normalizedResponse(
                platform: 'linkedin',
                socialAccountId: $socialAccountId,
                success: false,
                status: 'failed',
                providerResponse: $postData,
                errorCode: 'linkedin_video_post_failed',
                errorMessage: $postData['message'] ?? 'LinkedIn video post failed.',
                isRetryable: $this->isRetryableHttpError($postResponse->status())
            );
        }

        $postId = $postData['id'] ?? null;
        $encodedPostId = $postId ? urlencode((string) $postId) : null;

        return $this->normalizedResponse(
            platform: 'linkedin',
            socialAccountId: $socialAccountId,
            success: (bool) $postId,
            status: $postId ? 'published' : 'failed',
            externalPostId: $postId,
            externalPostUrl: $encodedPostId ? "https://www.linkedin.com/feed/update/{$encodedPostId}/" : null,
            providerStatus: $postId ? 'published' : 'error',
            providerResponse: $postData,
            publishedAt: $postId ? now()->toIso8601String() : null
        );
    }

    public function refreshToken(SocialAccount $account): array
    {
        $token = $account->token;
        if (! $token?->refresh_token) {
            return ['success' => false, 'expires_at' => null];
        }

        $settings = config('services.social.linkedin', []);

        $response = Http::asForm()
            ->acceptJson()
            ->timeout(15)
            ->post(self::REFRESH_URL, [
                'grant_type' => 'refresh_token',
                'refresh_token' => $token->refresh_token,
                'client_id' => $settings['client_id'] ?? '',
                'client_secret' => $settings['client_secret'] ?? '',
            ]);

        if ($response->failed()) {
            return ['success' => false, 'expires_at' => null];
        }

        $data = $response->json();
        $newToken = $data['access_token'] ?? null;
        $expiresIn = $data['expires_in'] ?? 5184000;
        $expiresAt = now()->addSeconds((int) $expiresIn);

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

        $settings = config('services.social.linkedin', []);

        $response = Http::asForm()
            ->timeout(10)
            ->post('https://www.linkedin.com/oauth/v2/revoke', [
                'client_id' => $settings['client_id'] ?? '',
                'client_secret' => $settings['client_secret'] ?? '',
                'token' => $accessToken,
            ]);

        return $response->successful();
    }
}
