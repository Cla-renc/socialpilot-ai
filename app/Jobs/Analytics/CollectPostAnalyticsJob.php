<?php

namespace App\Jobs\Analytics;

use App\Models\SocialAccount;
use App\Services\Analytics\AnalyticsCollectionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CollectPostAnalyticsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public function __construct(
        public SocialAccount $socialAccount,
        public array $options = []
    ) {}

    public function handle(AnalyticsCollectionService $collectionService): void
    {
        $collectionService->collectRecentPostAnalytics($this->socialAccount, $this->options);
    }
}
