<?php
namespace App\Jobs\Publishing;

use App\Models\ScheduledPost;
use App\Services\Publishing\PublishingManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class PublishScheduledPostJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 1;
    public int $timeout = 300;

    public function __construct(public readonly ScheduledPost $post) {}

    public function handle(PublishingManager $manager): void
    {
        $post = $this->post->fresh();
        if (!$post) return;

        if ($post->requires_approval && !in_array($post->status, ['approved', 'scheduled'])) {
            Log::info("Phase9: Skipping unapproved post {$post->id}");
            return;
        }

        $post->update(['status' => 'publishing']);

        // Try platform-specific accounts first (ScheduledPostPlatform)
        $platforms = method_exists($post, 'scheduledPostPlatforms')
            ? $post->scheduledPostPlatforms()->with('socialAccount')->get()
            : collect();

        if ($platforms->isNotEmpty()) {
            foreach ($platforms as $platform) {
                if (!$platform->socialAccount) continue;
                $manager->publish($post, $platform->socialAccount);
            }
        } else {
            // Fallback: brand's active social accounts
            $accounts = $post->brand->socialAccounts()->where('status', 'active')->get();
            foreach ($accounts as $account) {
                $manager->publish($post, $account);
            }
        }
    }
}
