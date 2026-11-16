<?php

namespace App\Console\Commands;

use App\Models\PublishingQueue;
use App\Services\PublishingQueueService;
use Illuminate\Console\Command;

class ProcessPublishingQueueCommand extends Command
{
    protected $signature = 'scheduling:process-queue {--queue= : Specific publishing queue ID to process}';
    protected $description = 'Process ready items in publishing queues and schedule them';

    public function handle(PublishingQueueService $queueService): int
    {
        $queueId = $this->option('queue');

        if ($queueId) {
            $queue = PublishingQueue::find($queueId);
            if (!$queue) {
                $this->error("Queue #{$queueId} not found.");
                return self::FAILURE;
            }
            $this->info("Processing queue #{$queueId}...");
            $scheduledCount = $queueService->processQueue($queue);
            $this->info("Scheduled {$scheduledCount} item(s) from queue.");
            return self::SUCCESS;
        }

        $activeQueues = PublishingQueue::where('status', 'active')->get();
        $total = 0;
        foreach ($activeQueues as $q) {
            $total += $queueService->processQueue($q);
        }

        $this->info("Processed " . $activeQueues->count() . " active queue(s). Scheduled {$total} item(s).");
        return self::SUCCESS;
    }
}
