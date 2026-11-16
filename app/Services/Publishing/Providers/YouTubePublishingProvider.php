<?php

namespace App\Services\Publishing\Providers;

use App\Models\PublishingAttempt;
use App\Models\ScheduledPost;
use App\Models\SocialAccount;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/**
 * YouTube Data API v3 publishing provider.
 *
 * Status: IMPLEMENTED — awaiting Google Cloud credentials with youtube.upload scope.
 *
 * Required OAuth scopes:
 *   - https://www.googleapis.com/auth/youtube.upload
 *   - https://www.googleapis.com/auth/youtube.readonly
 *
 * YouTube video uploads use a resumable upload flow for large files.
 * A successful upload initiation does NOT mean the video is published.
 * YouTube processes videos asynchronously after upload.
 */
class YouTubePublishingProvider extends AbstractPublishingProvider
{
    private const UPLOAD_URL = 'https://www.googleapis.com/upload/youtube/v3/videos';
    private const API_URL = 'https://www.googleapis.com/youtube/v3';
    private const REFRESH_URL = 'https://oauth2.googleapis.com/token';

    public function platform(): string
    {
        return 'youtube';
    }

    public function validateAccount(SocialAccount $account): array
    {
        if ($account->status === 'token_expired') {
            return ['valid' => false, 'reason' => 'YouTube/Google access token has expired. Please reconnect your account.'];
        }

        if ($account->status !== 'connected') {
            return ['valid' => false, 'reason' => 'YouTube account is not connected. Status: '.$account->status];
        }

        $token = $this->getAccessToken($account);
        if (! $token) {
            return ['valid' => false, 'reason' => 'No access token found for this YouTube account.'];
        }

        return ['valid' => true, 'reason' => null];
    }

    public function validateContent(ScheduledPost $post, SocialAccount $account, array $options = []): array
    {
        $errors = [];
        $title = $options['custom_title'] ?? $post->title ?? '';

        if (empty(trim($title))) {
            $errors[] = 'YouTube video requires a title.';
        }

        if (mb_strlen($title) > 100) {
            $errors[] = 'YouTube title exceeds the 100 character limit.';
        }

        $description = $options['custom_caption'] ?? $post->caption ?? $post->body ?? '';
        if (mb_strlen($description) > 5000) {
            $errors[] = 'YouTube description exceeds the 5,000 character limit.';
        }

        $mediaAssets = $post->mediaAssets()->get();
        $firstMedia = $mediaAssets->first();

        if (! $firstMedia || $firstMedia->file_type !== 'video') {
            $errors[] = 'YouTube requires a video file to publish.';
        }

        return ['valid' => empty($errors), 'errors' => $errors];
    }

    public function publish(ScheduledPost $post, SocialAccount $account, array $options = []): array
    {
        $accessToken = $this->getAccessToken($account);
        if (! $accessToken) {
            return $this->normalizedResponse(
                platform: 'youtube',
                socialAccountId: $account->id,
                success: false,
                status: 'failed',
                errorCode: 'no_access_token',
                errorMessage: 'No access token available for this YouTube account.',
                isRetryable: false
            );
        }

        $mediaAssets = $post->mediaAssets()->get();
        $firstMedia = $mediaAssets->first();

        if (! $firstMedia || $firstMedia->file_type !== 'video') {
            return $this->normalizedResponse(
                platform: 'youtube',
                socialAccountId: $account->id,
                success: false,
                status: 'failed',
                errorCode: 'no_video_media',
                errorMessage: 'YouTube requires a video file to publish.',
                isRetryable: false
            );
        }

        $title = $options['custom_title'] ?? $post->title ?? 'Untitled Video';
        $description = $options['custom_caption'] ?? $post->caption ?? $post->body ?? '';
        $privacyStatus = $options['privacy_status'] ?? 'public';
        $tags = $options['tags'] ?? (is_array($post->hashtags) ? array_map(fn ($t) => ltrim($t, '#'), $post->hashtags) : []);
        $categoryId = $options['category_id'] ?? '22'; // Default: People & Blogs

        try {
            return $this->initializeResumableUpload(
                accessToken: $accessToken,
                socialAccountId: $account->id,
                mediaAsset: $firstMedia,
                title: $title,
                description: $description,
                privacyStatus: $privacyStatus,
                tags: $tags,
                categoryId: $categoryId
            );
        } catch (\Throwable $e) {
            $this->logError('youtube', 'Publish exception', ['message' => $e->getMessage()]);

            return $this->normalizedResponse(
                platform: 'youtube',
                socialAccountId: $account->id,
                success: false,
                status: 'failed',
                errorCode: 'exception',
                errorMessage: 'YouTube publishing failed: '.$e->getMessage(),
                isRetryable: true
            );
        }
    }

    private function initializeResumableUpload(
        string $accessToken,
        int $socialAccountId,
        $mediaAsset,
        string $title,
        string $description,
        string $privacyStatus,
        array $tags,
        string $categoryId
    ): array {
        $filePath = Storage::disk(config('services.media.storage_disk', 'local'))->path($mediaAsset->file_path);
        $fileSize = file_exists($filePath) ? filesize($filePath) : null;

        $metadata = [
            'snippet' => [
                'title' => $title,
                'description' => $description,
                'tags' => $tags,
                'categoryId' => $categoryId,
            ],
            'status' => [
                'privacyStatus' => $privacyStatus,
                'selfDeclaredMadeForKids' => false,
            ],
        ];

        // Initiate the resumable upload session
        $initResponse = Http::withToken($accessToken)
            ->withHeaders([
                'X-Upload-Content-Type' => $mediaAsset->mime_type ?? 'video/mp4',
                'X-Upload-Content-Length' => $fileSize ?? 0,
            ])
            ->acceptJson()
            ->timeout(30)
            ->post(self::UPLOAD_URL.'?uploadType=resumable&part=snippet,status', $metadata);

        if ($initResponse->failed()) {
            $errData = $initResponse->json() ?? [];
            $errMsg = $errData['error']['message'] ?? 'YouTube upload initialization failed (HTTP '.$initResponse->status().')';
            $errCode = (string) ($errData['error']['code'] ?? 'youtube_upload_init_failed');

            return $this->normalizedResponse(
                platform: 'youtube',
                socialAccountId: $socialAccountId,
                success: false,
                status: 'failed',
                providerResponse: $errData,
                errorCode: $errCode,
                errorMessage: $errMsg,
                isRetryable: $this->isRetryableHttpError($initResponse->status())
            );
        }

        $uploadSessionUri = $initResponse->header('Location');
        if (! $uploadSessionUri) {
            return $this->normalizedResponse(
                platform: 'youtube',
                socialAccountId: $socialAccountId,
                success: false,
                status: 'failed',
                errorCode: 'no_upload_session_uri',
                errorMessage: 'YouTube did not return an upload session URI.',
                isRetryable: true
            );
        }

        // For the sandbox: perform the actual file upload
        if (! file_exists($filePath)) {
            return $this->normalizedResponse(
                platform: 'youtube',
                socialAccountId: $socialAccountId,
                success: false,
                status: 'failed',
                errorCode: 'media_file_not_found',
                errorMessage: 'Video file not found on storage: '.$mediaAsset->file_path,
                isRetryable: false
            );
        }

        // Upload the file content
        $uploadResponse = Http::withToken($accessToken)
            ->withHeaders([
                'Content-Type' => $mediaAsset->mime_type ?? 'video/mp4',
                'Content-Length' => $fileSize,
            ])
            ->timeout(300) // 5 minutes for large video uploads
            ->put($uploadSessionUri, ['body' => fopen($filePath, 'r')]);

        $uploadData = $uploadResponse->json() ?? [];

        if ($uploadResponse->status() === 200 || $uploadResponse->status() === 201) {
            $videoId = $uploadData['id'] ?? null;

            return $this->normalizedResponse(
                platform: 'youtube',
                socialAccountId: $socialAccountId,
                success: (bool) $videoId,
                status: $videoId ? 'processing' : 'failed',
                externalPostId: $videoId,
                externalPostUrl: $videoId ? "https://www.youtube.com/watch?v={$videoId}" : null,
                providerStatus: $videoId ? 'uploaded_processing' : 'upload_failed',
                providerResponse: $this->redactSensitiveData($uploadData),
                isRetryable: ! (bool) $videoId
            );
        }

        // Resumable upload incomplete (308 Resume Incomplete) — treat as processing
        if ($uploadResponse->status() === 308) {
            return $this->normalizedResponse(
                platform: 'youtube',
                socialAccountId: $socialAccountId,
                success: false,
                status: 'processing',
                providerStatus: 'upload_incomplete',
                providerResponse: ['upload_session_uri' => $uploadSessionUri],
                isRetryable: true
            );
        }

        $errMsg = $uploadData['error']['message'] ?? 'YouTube file upload failed (HTTP '.$uploadResponse->status().')';

        return $this->normalizedResponse(
            platform: 'youtube',
            socialAccountId: $socialAccountId,
            success: false,
            status: 'failed',
            providerResponse: $uploadData,
            errorCode: 'youtube_upload_failed',
            errorMessage: $errMsg,
            isRetryable: $this->isRetryableHttpError($uploadResponse->status())
        );
    }

    public function checkStatus(PublishingAttempt $attempt): array
    {
        if (! $attempt->external_post_id) {
            return ['status' => $attempt->status, 'provider_status' => $attempt->provider_status, 'updated' => false];
        }

        $accessToken = $this->getAccessToken($attempt->socialAccount);
        if (! $accessToken) {
            return ['status' => 'failed', 'provider_status' => 'no_token', 'updated' => true];
        }

        $response = Http::withToken($accessToken)
            ->acceptJson()
            ->timeout(15)
            ->get(self::API_URL.'/videos', [
                'part' => 'status',
                'id' => $attempt->external_post_id,
            ]);

        if ($response->failed()) {
            return ['status' => $attempt->status, 'provider_status' => $attempt->provider_status, 'updated' => false];
        }

        $items = $response->json('items', []);
        $item = $items[0] ?? null;

        if (! $item) {
            return ['status' => 'failed', 'provider_status' => 'video_not_found', 'updated' => true];
        }

        $uploadStatus = $item['status']['uploadStatus'] ?? null;

        return match ($uploadStatus) {
            'processed' => [
                'status' => 'published',
                'provider_status' => 'processed',
                'external_post_url' => "https://www.youtube.com/watch?v={$attempt->external_post_id}",
                'published_at' => now()->toIso8601String(),
                'updated' => true,
            ],
            'failed', 'rejected', 'deleted' => [
                'status' => 'failed',
                'provider_status' => $uploadStatus,
                'error_message' => 'YouTube video processing failed with status: '.$uploadStatus,
                'updated' => true,
            ],
            default => [
                'status' => 'processing',
                'provider_status' => $uploadStatus ?? 'processing',
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

        $settings = config('services.social.youtube', []);

        $response = Http::asForm()
            ->acceptJson()
            ->timeout(15)
            ->post(self::REFRESH_URL, [
                'client_id' => $settings['client_id'] ?? '',
                'client_secret' => $settings['client_secret'] ?? '',
                'refresh_token' => $token->refresh_token,
                'grant_type' => 'refresh_token',
            ]);

        if ($response->failed()) {
            return ['success' => false, 'expires_at' => null];
        }

        $data = $response->json();
        $newToken = $data['access_token'] ?? null;
        $expiresIn = $data['expires_in'] ?? 3600;
        $expiresAt = now()->addSeconds((int) $expiresIn);

        if ($newToken && $token) {
            $token->update([
                'access_token' => $newToken,
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

        $response = Http::acceptJson()
            ->timeout(10)
            ->post('https://oauth2.googleapis.com/revoke', ['token' => $accessToken]);

        return $response->successful();
    }
}
