<?php
namespace App\Console\Commands;

use App\Jobs\Publishing\PublishScheduledPostJob;
use App\Models\ScheduledPost;
use Illuminate\Console\Command;

class DispatchScheduledPublishingCommand extends Command
{
    protected $signature   = 'publishing:dispatch-scheduled';
    protected $description = 'Dispatch publishing jobs for scheduled posts whose time has come';

    public function handle(): int
    {
        $posts = ScheduledPost::where('status', 'scheduled')
            ->where('scheduled_at', '<=', now())
            ->get();

        $count = 0;
        foreach ($posts as $post) {
            PublishScheduledPostJob::dispatch($post);
            $count++;
        }

        $this->info("Dispatched {$count} publishing jobs.");
        return self::SUCCESS;
    }
}
