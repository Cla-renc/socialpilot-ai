<?php
namespace App\Services\Publishing;

use App\Models\PublishingAttempt;
use App\Models\ScheduledPost;
use App\Models\ScheduledPostPlatform;

class PublishingStatusService
{
    public function updateAttemptStatus(PublishingAttempt $attempt, array $result): void
    {
        $update = [
            'external_post_id'  => $result['external_post_id'] ?? $attempt->external_post_id,
            'external_post_url' => $result['external_post_url'] ?? $attempt->external_post_url,
            'provider_status'   => $result['provider_status'] ?? null,
            'provider_response' => isset($result['provider_response']) ? json_encode($result['provider_response']) : $attempt->provider_response,
            'error_code'        => $result['error_code'] ?? null,
            'error_message'     => $result['error_message'] ?? null,
        ];

        $publishingStatus = $result['publishing_status'] ?? null;

        if (!empty($result['success'])) {
            $update['status']       = 'published';
            $update['published_at'] = $result['published_at'] ?? now();
            $update['is_retryable'] = false;
        } elseif ($publishingStatus === 'processing') {
            $update['status'] = 'processing';
        } elseif (isset($result['success']) && $result['success'] === false) {
            $update['status']       = 'failed';
            $update['is_retryable'] = $result['is_retryable'] ?? true;
        }

        $attempt->update($update);
        $attempt->refresh();

        $this->updateScheduledPostPlatformStatus($attempt);

        if ($attempt->scheduled_post_id) {
            $post = ScheduledPost::find($attempt->scheduled_post_id);
            if ($post) {
                $this->computeAndUpdateOverallPostStatus($post);
            }
        }
    }

    public function computeAndUpdateOverallPostStatus(ScheduledPost $post): string
    {
        $attempts = PublishingAttempt::where('scheduled_post_id', $post->id)
            ->whereNotIn('status', ['cancelled', 'pending'])
            ->get();

        if ($attempts->isEmpty()) {
            return $post->status;
        }

        $statuses   = $attempts->pluck('status')->unique()->values()->toArray();
        $allPublished = $attempts->every(fn($a) => $a->status === 'published');
        $allFailed    = $attempts->every(fn($a) => $a->status === 'failed');
        $anyProcessing = $attempts->contains(fn($a) => in_array($a->status, ['processing', 'publishing']));

        if ($allPublished) {
            $overall = 'published';
        } elseif ($allFailed) {
            $overall = 'failed';
        } elseif ($anyProcessing) {
            $overall = 'publishing';
        } else {
            $overall = 'partially_published';
        }

        $post->update(['status' => $overall]);
        return $overall;
    }

    public function updateScheduledPostPlatformStatus(PublishingAttempt $attempt): void
    {
        if (!$attempt->scheduled_post_platform_id) return;

        $platform = ScheduledPostPlatform::find($attempt->scheduled_post_platform_id);
        if ($platform) {
            $platform->update(['status' => $attempt->status]);
        }
    }
}
