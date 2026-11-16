<?php
namespace App\Jobs\Publishing;

use App\Models\PublishingAttempt;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RetryFailedPublishingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(public readonly PublishingAttempt $attempt) {}

    public function handle(): void
    {
        $attempt = $this->attempt->fresh();
        if (!$attempt || $attempt->status !== 'failed' || !$attempt->is_retryable) return;
        if ($attempt->attempt_number >= 5) return;

        $newAttempt                   = $attempt->replicate();
        $newAttempt->attempt_number   = $attempt->attempt_number + 1;
        $newAttempt->status           = 'publishing';
        $newAttempt->idempotency_key  = hash('sha256', $attempt->idempotency_key . ':retry:' . $newAttempt->attempt_number);
        $newAttempt->error_message    = null;
        $newAttempt->error_code       = null;
        $newAttempt->published_at     = null;
        $newAttempt->next_retry_at    = null;
        $newAttempt->save();

        PublishImmediatePostJob::dispatch($newAttempt);
    }
}
