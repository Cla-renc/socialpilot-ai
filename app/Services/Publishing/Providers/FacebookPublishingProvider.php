<?php

namespace App\Services\Publishing\Providers;

use App\Models\PublishingAttempt;
use App\Models\ScheduledPost;
use App\Models\SocialAccount;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/**
 * Facebook Pages publishing provider using the Meta Graph API.
 *
 * Status: IMPLEMENTED — awaiting live Meta app credentials and page permissions.
 * Required scopes: pages_manage_posts, pages_read_engagement, pages_show_list.
 * Optional: publish_video for video posts.
 */
class FacebookPublishingProvider extends AbstractPublishingProvider
{
    private string $graphVersion;

    public function __construct()
    {
        $this->graphVersion = config('services.social.facebook.graph_version', 'v22.0');
    }

    public function platform(): string
    {
        return 'facebook';
    }

    public function validateAccount(SocialAccount $account): array
    {
        if ($account->status === 'token_expired') {
            return ['valid' => false, 'reason' => 'Facebook access token has expired. Please reconnect your account.'];
        }

        if ($account->status !== 'connected') {
            return ['valid' => false, 'reason' => 'Facebook account is not connected. Status: '.$account->status];
        }

        if ($account->account_type !== 'page') {
            return ['valid' => false, 'reason' => 'Only Facebook Pages can be published to through the Graph API.'];
        }

        $token = $this->getAccessToken($account);
        if (! $token) {
            return ['valid' => false, 'reason' => 'No access token found for this Facebook account.'];
        }

        return ['valid' => true, 'reason' => null];
    }

    public function validateContent(ScheduledPost $post, SocialAccount $account, array $options = []): array
    {
        $errors = [];

        $caption = $options['custom_caption'] ?? $post->caption ?? $post->body ?? '';
        if (empty(trim($caption))) {
            $errors[] = 'Facebook post requires at least a text caption.';
        }

        if (mb_strlen($caption) > 63206) {
            $errors[] = 'Facebook post caption exceeds the 63,206 character limit.';
        }

        return ['valid' => empty($errors), 'errors' => $errors];
    }

    public function publish(ScheduledPost $post, SocialAccount $account, array $options = []): array
    {
        $accessToken = $this->getAccessToken($account);
        if (! $accessToken) {
            return $this->normalizedResponse(
                platform: 'facebook',
                socialAccountId: $account->id,
                success: false,
                status: 'failed',
                errorCode: 'no_access_token',
                errorMessage: 'No access token available for this Facebook account.',
                isRetryable: false
            );
        }

        $pageId = $account->provider_account_id;
        $caption = $options['custom_caption'] ?? $post->caption ?? $post->body ?? '';

        // Check if there's media to publish
        $mediaAssets = $post->mediaAssets()->get();
        $firstMedia = $mediaAssets->first();

        try {
            if ($firstMedia && $firstMedia->file_type === 'video') {
                return $this->publishVideo($pageId, $accessToken, $post, $firstMedia, $caption, $account->id);
            } elseif ($firstMedia && $firstMedia->file_type === 'image') {
                return $this->publishImagePost($pageId, $accessToken, $firstMedia, $caption, $account->id);
            } else {
                return $this->publishTextPost($pageId, $accessToken, $caption, $account->id);
            }
        } catch (\Throwable $e) {
            $this->logError('facebook', 'Publish exception', ['message' => $e->getMessage()]);

            return $this->normalizedResponse(
                platform: 'facebook',
                socialAccountId: $account->id,
                success: false,
                status: 'failed',
                errorCode: 'exception',
                errorMessage: 'Facebook publishing failed: '.$e->getMessage(),
                isRetryable: true
            );
        }
    }

    private function publishTextPost(string $pageId, string $accessToken, string $message, int $socialAccountId): array
    {
        $response = Http::withToken($accessToken)
            ->acceptJson()
            ->timeout(30)
            ->post("https://graph.facebook.com/{$this->graphVersion}/{$pageId}/feed", [
                'message' => $message,
            ]);

        $data = $response->json() ?? [];

        if ($response->failed()) {
            $errMsg = $data['error']['message'] ?? 'Unknown Facebook API error';
            $errCode = (string) ($data['error']['code'] ?? 'facebook_api_error');

            return $this->normalizedResponse(
                platform: 'facebook',
                socialAccountId: $socialAccountId,
                success: false,
                status: 'failed',
                providerStatus: 'error',
                providerResponse: $data,
                errorCode: $errCode,
                errorMessage: $errMsg,
                isRetryable: $this->isRetryableHttpError($response->status())
            );
        }

        $postId = $data['id'] ?? null;

        return $this->normalizedResponse(
            platform: 'facebook',
            socialAccountId: $socialAccountId,
            success: true,
            status: 'published',
            externalPostId: $postId,
            externalPostUrl: $postId ? "https://www.facebook.com/{$postId}" : null,
            providerStatus: 'published',
            providerResponse: $data,
            publishedAt: now()->toIso8601String()
        );
    }

    private function publishImagePost(string $pageId, string $accessToken, $mediaAsset, string $caption, int $socialAccountId): array
    {
        // For image posts: upload photo, then create a feed post with the photo
        $mediaUrl = Storage::disk(config('services.media.storage_disk', 'local'))->url($mediaAsset->file_path);

        $photoResponse = Http::withToken($accessToken)
            ->acceptJson()
            ->timeout(60)
            ->post("https://graph.facebook.com/{$this->graphVersion}/{$pageId}/photos", [
                'url' => $mediaUrl,
                'caption' => $caption,
                'published' => true,
            ]);

        $data = $photoResponse->json() ?? [];

        if ($photoResponse->failed()) {
            $errMsg = $data['error']['message'] ?? 'Facebook photo upload failed';
            $errCode = (string) ($data['error']['code'] ?? 'photo_upload_error');

            return $this->normalizedResponse(
                platform: 'facebook',
                socialAccountId: $socialAccountId,
                success: false,
                status: 'failed',
                providerResponse: $data,
                errorCode: $errCode,
                errorMessage: $errMsg,
                isRetryable: $this->isRetryableHttpError($photoResponse->status())
            );
        }

        $postId = $data['post_id'] ?? $data['id'] ?? null;

        return $this->normalizedResponse(
            platform: 'facebook',
            socialAccountId: $socialAccountId,
            success: true,
            status: 'published',
            externalPostId: $postId,
            externalPostUrl: $postId ? "https://www.facebook.com/{$postId}" : null,
            providerStatus: 'published',
            providerResponse: $data,
            publishedAt: now()->toIso8601String()
        );
    }

    private function publishVideo(string $pageId, string $accessToken, ScheduledPost $post, $mediaAsset, string $caption, int $socialAccountId): array
    {
        // Video upload to Facebook Pages uses a different endpoint
        $title = $post->title ?? 'Video Post';

        $mediaUrl = Storage::disk(config('services.media.storage_disk', 'local'))->url($mediaAsset->file_path);

        $videoResponse = Http::withToken($accessToken)
            ->acceptJson()
            ->timeout(120)
            ->post("https://graph-video.facebook.com/{$this->graphVersion}/{$pageId}/videos", [
                'file_url' => $mediaUrl,
                'title' => $title,
                'description' => $caption,
            ]);

        $data = $videoResponse->json() ?? [];

        if ($videoResponse->failed()) {
            $errMsg = $data['error']['message'] ?? 'Facebook video upload failed';
            $errCode = (string) ($data['error']['code'] ?? 'video_upload_error');

            return $this->normalizedResponse(
                platform: 'facebook',
                socialAccountId: $socialAccountId,
                success: false,
                status: 'failed',
                providerResponse: $data,
                errorCode: $errCode,
                errorMessage: $errMsg,
                isRetryable: $this->isRetryableHttpError($videoResponse->status())
            );
        }

        $videoId = $data['id'] ?? null;

        return $this->normalizedResponse(
            platform: 'facebook',
            socialAccountId: $socialAccountId,
            success: (bool) $videoId,
            status: $videoId ? 'processing' : 'failed',
            externalPostId: $videoId,
            externalPostUrl: $videoId ? "https://www.facebook.com/video/{$videoId}" : null,
            providerStatus: $videoId ? 'processing' : 'error',
            providerResponse: $data,
            publishedAt: $videoId ? now()->toIso8601String() : null,
            isRetryable: ! (bool) $videoId
        );
    }

    public function checkStatus(PublishingAttempt $attempt): array
    {
        $accessToken = $this->getAccessToken($attempt->socialAccount);
        if (! $accessToken || ! $attempt->external_post_id) {
            return ['status' => $attempt->status, 'provider_status' => $attempt->provider_status, 'updated' => false];
        }

        $response = Http::withToken($accessToken)
            ->acceptJson()
            ->timeout(10)
            ->get("https://graph.facebook.com/{$this->graphVersion}/{$attempt->external_post_id}", [
                'fields' => 'id,created_time,status_type',
            ]);

        if ($response->successful() && isset($response->json()['id'])) {
            return ['status' => 'published', 'provider_status' => 'published', 'updated' => true];
        }

        return ['status' => $attempt->status, 'provider_status' => $attempt->provider_status, 'updated' => false];
    }

    public function refreshToken(SocialAccount $account): array
    {
        // Facebook long-lived tokens don't use a refresh_token; they extend via the exchange endpoint
        $settings = config('services.social.facebook', []);
        if (empty($settings['client_id']) || empty($settings['client_secret'])) {
            return ['success' => false, 'expires_at' => null];
        }

        $currentToken = $this->getAccessToken($account);
        if (! $currentToken) {
            return ['success' => false, 'expires_at' => null];
        }

        $response = Http::acceptJson()
            ->timeout(15)
            ->get("https://graph.facebook.com/{$this->graphVersion}/oauth/access_token", [
                'client_id' => $settings['client_id'],
                'client_secret' => $settings['client_secret'],
                'grant_type' => 'fb_exchange_token',
                'fb_exchange_token' => $currentToken,
            ]);

        if ($response->failed()) {
            return ['success' => false, 'expires_at' => null];
        }

        $data = $response->json();
        $newToken = $data['access_token'] ?? null;
        $expiresIn = $data['expires_in'] ?? null;
        $expiresAt = $expiresIn ? now()->addSeconds((int) $expiresIn) : null;

        if ($newToken && $account->token) {
            $account->token->update([
                'access_token' => $newToken,
                'expires_at' => $expiresAt,
            ]);
        }

        return ['success' => (bool) $newToken, 'expires_at' => $expiresAt];
    }

    public function revokeConnection(SocialAccount $account): bool
    {
        $token = $this->getAccessToken($account);
        if (! $token) {
            return false;
        }

        $response = Http::withToken($token)
            ->timeout(15)
            ->delete("https://graph.facebook.com/{$this->graphVersion}/me/permissions");

        return $response->successful();
    }
}
