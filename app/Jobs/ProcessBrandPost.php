<?php

namespace App\Jobs;

use App\Models\BrandPost;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessBrandPost implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $postId) {}

    public function handle(): void
    {
        $post = BrandPost::query()->findOrFail($this->postId);

        if ($post->status === 'scheduled' && $post->scheduled_for && $post->scheduled_for->isFuture()) {
            return;
        }

        $post->update([
            'status' => 'processing',
        ]);

        $post->refresh();

        $post->update([
            'status' => 'published',
            'published_at' => now(),
            'response_payload' => [
                'provider' => $post->provider,
                'platform' => $post->platform,
                'status' => 'success',
                'message' => 'Content was queued and published by the SocialPilot orchestration layer.',
            ],
        ]);
    }
}
