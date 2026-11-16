<?php

namespace App\Services;

use App\Jobs\ProcessBrandPost;
use App\Models\Brand;
use App\Models\BrandPost;
use App\Models\User;
use Carbon\Carbon;

class PublishingService
{
    public function createPost(User $user, Brand $brand, array $data): BrandPost
    {
        $content = $data['content'];
        $scheduledFor = isset($data['scheduled_for']) ? Carbon::parse($data['scheduled_for']) : null;
        $status = $data['status'] ?? ($scheduledFor && $scheduledFor->isFuture() ? 'scheduled' : 'queued');

        $post = BrandPost::query()->create([
            'user_id' => $user->id,
            'brand_id' => $brand->id,
            'content_draft_id' => $data['content_draft_id'] ?? null,
            'provider' => $data['provider'] ?? config('services.ai.default_provider', 'internal'),
            'platform' => $data['platform'],
            'status' => $status,
            'scheduled_for' => $scheduledFor,
            'content' => $content,
            'metadata' => $data['metadata'] ?? [],
        ]);

        if ($status === 'queued') {
            ProcessBrandPost::dispatchSync($post->id);
        }

        return $post->fresh();
    }
}
