<?php
namespace App\Services\Publishing;

use App\Models\PublishingAttempt;
use App\Models\PublishingNotification;

class PublishingNotificationService
{
    public function notifySuccess(PublishingAttempt $attempt): void
    {
        PublishingNotification::create([
            'user_id'               => $attempt->user_id,
            'brand_id'              => $attempt->brand_id,
            'publishing_attempt_id' => $attempt->id,
            'scheduled_post_id'     => $attempt->scheduled_post_id,
            'notification_type'     => 'success',
            'platform'              => $attempt->platform,
            'title'                 => 'Post Published Successfully',
            'message'               => "Your post was published to {$attempt->platform} successfully.",
            'metadata'              => ['platform' => $attempt->platform, 'external_post_url' => $attempt->external_post_url],
        ]);
    }

    public function notifyFailure(PublishingAttempt $attempt): void
    {
        PublishingNotification::create([
            'user_id'               => $attempt->user_id,
            'brand_id'              => $attempt->brand_id,
            'publishing_attempt_id' => $attempt->id,
            'scheduled_post_id'     => $attempt->scheduled_post_id,
            'notification_type'     => 'failure',
            'platform'              => $attempt->platform,
            'title'                 => 'Publishing Failed',
            'message'               => "Failed to publish to {$attempt->platform}: " . ($attempt->error_message ?? 'Unknown error'),
            'metadata'              => ['platform' => $attempt->platform, 'error_code' => $attempt->error_code],
        ]);
    }

    public function notifyPartialSuccess(PublishingAttempt $attempt): void
    {
        PublishingNotification::create([
            'user_id'               => $attempt->user_id,
            'brand_id'              => $attempt->brand_id,
            'publishing_attempt_id' => $attempt->id,
            'scheduled_post_id'     => $attempt->scheduled_post_id,
            'notification_type'     => 'partial',
            'platform'              => $attempt->platform,
            'title'                 => 'Post Partially Published',
            'message'               => "Post was published to some platforms but failed on {$attempt->platform}.",
            'metadata'              => ['platform' => $attempt->platform],
        ]);
    }
}
