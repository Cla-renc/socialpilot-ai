<?php

namespace App\Jobs;

use App\Models\PublishingQueue;
use App\Services\PublishingQueueService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ScheduleQueuedContentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public ?int $queueId = null
    ) {}

    public function handle(PublishingQueueService $queueService): void
    {
        if ($this->queueId) {
            $queue = PublishingQueue::find($this->queueId);
            if ($queue) {
                $queueService->processQueue($queue);
            }
        } else {
            $activeQueues = PublishingQueue::where('status', 'active')->get();
            foreach ($activeQueues as $q) {
                $queueService->processQueue($q);
            }
        }
    }
}
