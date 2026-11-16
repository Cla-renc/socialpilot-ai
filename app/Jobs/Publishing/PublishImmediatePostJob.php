<?php
namespace App\Jobs\Publishing;

use App\Models\PublishingAttempt;
use App\Models\SocialAccount;
use App\Services\Publishing\PublishingManager;
use App\Services\Publishing\PublishingStatusService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class PublishImmediatePostJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 1;
    public int $timeout = 120;

    public function __construct(
        public readonly PublishingAttempt $attempt,
        public readonly array $options = []
    ) {}

    public function handle(PublishingManager $manager, PublishingStatusService $statusService): void
    {
        $attempt = $this->attempt->fresh();
        if (!$attempt || in_array($attempt->status, ['published', 'cancelled'])) {
            return;
        }

        $account = SocialAccount::find($attempt->social_account_id);
        if (!$account) {
            $attempt->update(['status' => 'failed', 'error_message' => 'Social account not found', 'is_retryable' => false]);
            return;
        }

        try {
            $provider = $manager->providerFor($attempt->platform);
            $post     = $attempt->scheduledPost;
            if (!$post) {
                $post = \App\Models\ScheduledPost::create([
                    'user_id'      => $attempt->user_id,
                    'brand_id'     => $attempt->brand_id,
                    'title'        => 'Direct Publish',
                    'caption'      => $attempt->request_metadata['text'] ?? '',
                    'body'         => $attempt->request_metadata['text'] ?? '',
                    'status'       => 'publishing',
                    'scheduled_at' => now(),
                ]);
            }
            $result   = $provider->publish($post, $account, $this->options);
            $statusService->updateAttemptStatus($attempt, $result);
        } catch (\Exception $e) {
            Log::error("PublishImmediatePostJob failed for attempt {$attempt->id}: " . $e->getMessage());
            $attempt->update(['status' => 'failed', 'error_message' => $e->getMessage(), 'is_retryable' => true]);
        }

        $attempt->refresh();
        if ($attempt->status === 'processing') {
            CheckPublishingStatusJob::dispatch($attempt)->delay(now()->addSeconds(30));
        }
    }
}
