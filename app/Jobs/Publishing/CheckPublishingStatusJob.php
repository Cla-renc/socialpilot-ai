<?php
namespace App\Jobs\Publishing;

use App\Models\PublishingAttempt;
use App\Services\Publishing\PublishingManager;
use App\Services\Publishing\PublishingStatusService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CheckPublishingStatusJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 1;
    public int $timeout = 60;

    private const MAX_CHECKS = 20;

    public function __construct(
        public readonly PublishingAttempt $attempt,
        public readonly int $checkCount = 0
    ) {}

    public function handle(PublishingManager $manager, PublishingStatusService $statusService): void
    {
        $attempt = $this->attempt->fresh();
        if (!$attempt || !in_array($attempt->status, ['processing', 'publishing'])) return;

        if ($this->checkCount >= self::MAX_CHECKS) {
            $attempt->update(['status' => 'failed', 'error_message' => 'Publishing timed out after repeated status checks', 'is_retryable' => true]);
            return;
        }

        try {
            $provider = $manager->providerFor($attempt->platform);
            $account  = $attempt->socialAccount ?? null;
            if (!$account) return;

            $result = $provider->checkStatus($account, $attempt->external_post_id ?? '', $attempt->metadata ?? []);
            $statusService->updateAttemptStatus($attempt, $result);

            if ($attempt->fresh()->status === 'processing') {
                $delay = (int) min(300, 30 * pow(2, $this->checkCount));
                self::dispatch($attempt, $this->checkCount + 1)->delay(now()->addSeconds($delay));
            }
        } catch (\Exception $e) {
            Log::error("CheckPublishingStatusJob failed for attempt {$attempt->id}: " . $e->getMessage());
            $attempt->update(['status' => 'failed', 'error_message' => $e->getMessage(), 'is_retryable' => true]);
        }
    }
}
