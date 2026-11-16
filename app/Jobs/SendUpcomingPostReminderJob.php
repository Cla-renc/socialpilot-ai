<?php

namespace App\Jobs;

use App\Models\ScheduledPost;
use App\Notifications\DashboardEventNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendUpcomingPostReminderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        // Posts scheduled within the next 60 minutes that haven't sent a reminder yet
        $windowStart = now();
        $windowEnd = now()->addHour();

        $posts = ScheduledPost::query()
            ->where('status', 'scheduled')
            ->whereBetween('scheduled_at', [$windowStart, $windowEnd])
            ->whereNull('metadata->reminder_sent_at')
            ->with(['brand', 'user'])
            ->get();

        foreach ($posts as $post) {
            $formattedTime = $post->scheduled_at->setTimezone($post->timezone ?: 'UTC')->format('g:i A');

            $post->user?->notify(new DashboardEventNotification(
                title: "Upcoming Scheduled Post",
                description: "Your post '{$post->title}' is scheduled for {$formattedTime} ({$post->timezone}).",
                actionUrl: "/brands/{$post->brand_id}/scheduling/{$post->id}",
                brandId: $post->brand_id,
            ));

            $meta = $post->metadata ?? [];
            $meta['reminder_sent_at'] = now()->toIso8601String();
            $post->update(['metadata' => $meta]);
        }
    }
}
