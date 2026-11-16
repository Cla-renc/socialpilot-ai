<?php
namespace App\Services\Publishing;

use App\Jobs\Publishing\CheckPublishingStatusJob;
use App\Models\PublishingAttempt;
use App\Models\ScheduledPost;
use App\Models\SocialAccount;
use App\Services\Publishing\Providers\FacebookPublishingProvider;
use App\Services\Publishing\Providers\InstagramPublishingProvider;
use App\Services\Publishing\Providers\LinkedInPublishingProvider;
use App\Services\Publishing\Providers\SocialPlatformProviderInterface;
use App\Services\Publishing\Providers\TikTokPublishingProvider;
use App\Services\Publishing\Providers\YouTubePublishingProvider;

class PublishingManager
{
    private array $providers = [];

    public function __construct(
        FacebookPublishingProvider  $facebook,
        InstagramPublishingProvider $instagram,
        TikTokPublishingProvider    $tiktok,
        YouTubePublishingProvider   $youtube,
        LinkedInPublishingProvider  $linkedin,
    ) {
        $this->providers = [
            'facebook'  => $facebook,
            'instagram' => $instagram,
            'tiktok'    => $tiktok,
            'youtube'   => $youtube,
            'linkedin'  => $linkedin,
        ];
    }

    public function providerFor(string $platform): SocialPlatformProviderInterface
    {
        $platform = strtolower($platform);
        if (!isset($this->providers[$platform])) {
            throw new \InvalidArgumentException("No publishing provider registered for platform: {$platform}");
        }
        return $this->providers[$platform];
    }

    public function publish(ScheduledPost $post, SocialAccount $account, array $options = []): PublishingAttempt
    {
        $idempotencyKey = hash('sha256', "{$post->id}:{$account->id}:{$account->platform}:1");

        // Dedup guard
        $existing = PublishingAttempt::where('idempotency_key', $idempotencyKey)
            ->where('status', 'published')
            ->first();
        if ($existing) return $existing;

        $attempt = PublishingAttempt::create([
            'user_id'          => $post->user_id,
            'brand_id'         => $post->brand_id,
            'scheduled_post_id'=> $post->id,
            'social_account_id'=> $account->id,
            'platform'         => $account->platform,
            'status'           => 'publishing',
            'attempt_number'   => 1,
            'is_retryable'     => true,
            'idempotency_key'  => $idempotencyKey,
            'request_metadata' => $this->buildContentPayload($post, $account, $options),
        ]);

        try {
            $provider = $this->providerFor($account->platform);
            $result   = $provider->publish($post, $account, $options);
            app(PublishingStatusService::class)->updateAttemptStatus($attempt, $result);
        } catch (\Exception $e) {
            $attempt->update(['status' => 'failed', 'error_message' => $e->getMessage(), 'is_retryable' => true]);
        }

        $attempt->refresh();
        if ($attempt->status === 'processing') {
            CheckPublishingStatusJob::dispatch($attempt)->delay(now()->addSeconds(30));
        }

        return $attempt;
    }

    public function publishWithAccount(array $content, SocialAccount $account, array $options = []): PublishingAttempt
    {
        $idempotencyKey = hash('sha256', uniqid('immediate:', true) . ':' . $account->id . ':' . time());

        // Create a transient or lightweight ScheduledPost backing model so providers receive ScheduledPost
        $post = ScheduledPost::create([
            'user_id'      => $account->brand->user_id ?? $account->user_id ?? 1,
            'brand_id'     => $account->brand_id,
            'title'        => $content['title'] ?? 'Direct Publish Post',
            'caption'      => $content['text'] ?? $content['caption'] ?? '',
            'body'         => $content['text'] ?? $content['body'] ?? '',
            'status'       => 'publishing',
            'scheduled_at' => now(),
        ]);

        $attempt = PublishingAttempt::create([
            'user_id'          => $account->brand->user_id ?? null,
            'brand_id'         => $account->brand_id ?? null,
            'scheduled_post_id'=> $post->id,
            'social_account_id'=> $account->id,
            'platform'         => $account->platform,
            'status'           => 'publishing',
            'attempt_number'   => 1,
            'is_retryable'     => true,
            'idempotency_key'  => $idempotencyKey,
            'request_metadata' => $content,
        ]);

        try {
            $provider = $this->providerFor($account->platform);
            $result   = $provider->publish($post, $account, $options);
            app(PublishingStatusService::class)->updateAttemptStatus($attempt, $result);
        } catch (\Exception $e) {
            $attempt->update(['status' => 'failed', 'error_message' => $e->getMessage(), 'is_retryable' => true]);
        }

        $attempt->refresh();
        if ($attempt->status === 'processing') {
            CheckPublishingStatusJob::dispatch($attempt)->delay(now()->addSeconds(30));
        }

        return $attempt;
    }

    private function buildContentPayload(ScheduledPost $post, SocialAccount $account, array $options): array
    {
        return [
            'text'       => $post->content ?? '',
            'platform'   => $account->platform,
            'media_urls' => $options['media_urls'] ?? [],
            'hashtags'   => $post->hashtags ?? [],
            'title'      => $post->title ?? null,
        ];
    }
}
