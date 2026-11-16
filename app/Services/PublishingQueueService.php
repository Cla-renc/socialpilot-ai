<?php

namespace App\Services;

use App\Models\Brand;
use App\Models\BrandContentDraft;
use App\Models\PublishingQueue;
use App\Models\PublishingQueueItem;
use App\Models\ScheduledPost;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PublishingQueueService
{
    public function __construct(
        protected ContentSchedulingService $schedulingService
    ) {}

    /**
     * Get or create the brand's default publishing queue.
     */
    public function getOrCreateDefaultQueue(Brand $brand): PublishingQueue
    {
        return PublishingQueue::firstOrCreate(
            ['brand_id' => $brand->id],
            [
                'user_id'                  => $brand->user_id,
                'name'                     => "{$brand->name} Main Queue",
                'status'                   => 'active',
                'posting_interval_minutes' => 1440, // 24 hours
                'preferred_days'           => ['monday', 'wednesday', 'friday'],
                'preferred_hours'          => ['10:00', '15:00'],
                'timezone'                 => $brand->settings?->timezone ?: 'UTC',
                'next_slot_at'             => now()->addDay()->setTime(10, 0),
            ]
        );
    }

    /**
     * Add a content draft to the queue.
     */
    public function addDraftToQueue(PublishingQueue $queue, BrandContentDraft $draft, array $options = []): PublishingQueueItem
    {
        if ($draft->brand_id !== $queue->brand_id) {
            throw ValidationException::withMessages(['content_draft_id' => 'Draft belongs to another brand.']);
        }

        $maxPos = (int) $queue->items()->max('position');

        return PublishingQueueItem::create([
            'publishing_queue_id' => $queue->id,
            'user_id'             => $queue->user_id,
            'brand_id'            => $queue->brand_id,
            'content_draft_id'    => $draft->id,
            'position'            => $maxPos + 1,
            'priority'            => $options['priority'] ?? 'normal',
            'status'              => 'ready_to_schedule',
            'target_platform'     => $options['target_platform'] ?? $draft->platform ?: 'linkedin',
            'notes'               => $options['notes'] ?? null,
        ]);
    }

    /**
     * Add a scheduled post to the queue.
     */
    public function addPostToQueue(PublishingQueue $queue, ScheduledPost $post, array $options = []): PublishingQueueItem
    {
        if ($post->brand_id !== $queue->brand_id) {
            throw ValidationException::withMessages(['scheduled_post_id' => 'Post belongs to another brand.']);
        }

        $maxPos = (int) $queue->items()->max('position');

        return PublishingQueueItem::create([
            'publishing_queue_id' => $queue->id,
            'user_id'             => $queue->user_id,
            'brand_id'            => $queue->brand_id,
            'scheduled_post_id'   => $post->id,
            'content_draft_id'    => $post->content_draft_id,
            'position'            => $maxPos + 1,
            'priority'            => $options['priority'] ?? 'normal',
            'status'              => 'ready_to_schedule',
            'target_platform'     => $options['target_platform'] ?? $post->platforms->first()?->platform ?: 'linkedin',
            'notes'               => $options['notes'] ?? null,
        ]);
    }

    /**
     * Reorder queue items.
     */
    public function reorderQueue(PublishingQueue $queue, array $orderedItemIds): PublishingQueue
    {
        DB::transaction(function () use ($queue, $orderedItemIds) {
            foreach ($orderedItemIds as $index => $itemId) {
                PublishingQueueItem::query()
                    ->where('publishing_queue_id', $queue->id)
                    ->where('id', $itemId)
                    ->update(['position' => $index + 1]);
            }
        });

        return $queue->fresh(['items']);
    }

    /**
     * Pause a queue.
     */
    public function pauseQueue(PublishingQueue $queue): PublishingQueue
    {
        $queue->update(['status' => 'paused']);
        return $queue->fresh();
    }

    /**
     * Resume a queue.
     */
    public function resumeQueue(PublishingQueue $queue): PublishingQueue
    {
        $queue->update(['status' => 'active']);
        return $queue->fresh();
    }

    /**
     * Calculate next available slot based on preferred days and hours.
     */
    public function calculateNextSlot(PublishingQueue $queue): Carbon
    {
        $brandTz = $queue->timezone ?: 'UTC';
        $preferredDays = $queue->preferred_days ?: ['monday', 'wednesday', 'friday'];
        $preferredHours = $queue->preferred_hours ?: ['10:00'];

        $cursor = now($brandTz)->addHours(2);

        // Find next day matching preferred days
        for ($i = 0; $i < 14; $i++) {
            $dayName = strtolower($cursor->format('l'));
            if (in_array($dayName, $preferredDays)) {
                // Check if any preferred hour is still in the future for this day
                foreach ($preferredHours as $hStr) {
                    $parts = explode(':', $hStr);
                    $h = (int) ($parts[0] ?? 10);
                    $m = (int) ($parts[1] ?? 0);

                    $slot = $cursor->copy()->setTime($h, $m, 0);
                    if ($slot->isFuture()) {
                        return $slot->setTimezone('UTC');
                    }
                }
            }
            $cursor->addDay()->setTime(0, 0, 0);
        }

        return now()->addDays(1)->setTime(10, 0, 0);
    }

    /**
     * Process items in an active queue.
     */
    public function processQueue(PublishingQueue $queue): int
    {
        if ($queue->status !== 'active') {
            return 0;
        }

        $items = $queue->items()
            ->where('status', 'ready_to_schedule')
            ->orderBy('position')
            ->get();

        $processedCount = 0;
        $slotUtc = $this->calculateNextSlot($queue);
        $minIntervalMinutes = $queue->posting_interval_minutes ?: 1440;

        foreach ($items as $item) {
            if ($item->contentDraft) {
                // Schedule this draft
                $this->schedulingService->schedulePost(
                    user: $queue->user,
                    brand: $queue->brand,
                    data: [
                        'content_draft_id' => $item->contentDraft->id,
                        'title'            => $item->contentDraft->title,
                        'caption'          => $item->contentDraft->caption,
                        'body'             => $item->contentDraft->body,
                        'platform'         => $item->target_platform ?: ($item->contentDraft->platform ?: 'linkedin'),
                        'scheduled_at'     => $slotUtc->toIso8601String(),
                        'timezone'         => $queue->timezone,
                        'source'           => 'queue_processor',
                    ]
                );

                $item->update(['status' => 'scheduled']);
                $slotUtc->addMinutes($minIntervalMinutes);
                $processedCount++;
            }
        }

        $queue->update(['next_slot_at' => $slotUtc]);

        return $processedCount;
    }
}
