<?php
namespace App\Services\Publishing;

use App\Jobs\Publishing\RetryFailedPublishingJob;
use App\Models\PublishingAttempt;
use Illuminate\Support\Collection;

class PublishingRetryService
{
    private const MAX_ATTEMPTS = 5;

    public function canRetry(PublishingAttempt $attempt): bool
    {
        if ($attempt->status !== 'failed')    return false;
        if (!$attempt->is_retryable)          return false;
        if ($attempt->attempt_number >= self::MAX_ATTEMPTS) return false;

        // Check if there is already a successful attempt for this post+account+platform
        $successExists = PublishingAttempt::where('social_account_id', $attempt->social_account_id)
            ->where('platform', $attempt->platform)
            ->where('scheduled_post_id', $attempt->scheduled_post_id)
            ->where('status', 'published')
            ->exists();

        return !$successExists;
    }

    public function scheduleRetry(PublishingAttempt $attempt): void
    {
        $delayMinutes = (int) pow(2, $attempt->attempt_number); // 2, 4, 8, 16, 32
        $attempt->update(['next_retry_at' => now()->addMinutes($delayMinutes)]);

        RetryFailedPublishingJob::dispatch($attempt)->delay(now()->addMinutes($delayMinutes));
    }

    public function getRetryableAttempts(): Collection
    {
        return PublishingAttempt::where('status', 'failed')
            ->where('is_retryable', true)
            ->where('attempt_number', '<', self::MAX_ATTEMPTS)
            ->where(function ($q) {
                $q->whereNull('next_retry_at')->orWhere('next_retry_at', '<=', now());
            })
            ->get();
    }
}
