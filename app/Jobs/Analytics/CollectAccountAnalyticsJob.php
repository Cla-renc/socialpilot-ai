<?php

namespace App\Jobs\Analytics;

use App\Models\SocialAccount;
use App\Services\Analytics\AnalyticsCollectionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CollectAccountAnalyticsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;

    public function __construct(
        public SocialAccount $socialAccount,
        public array $options = []
    ) {}

    public function handle(AnalyticsCollectionService $collectionService): void
    {
        Log::info("Starting analytics collection job for account #{$this->socialAccount->id} ({$this->socialAccount->provider})");
        $result = $collectionService->collectAccount($this->socialAccount, $this->options);

        if (!$result['success']) {
            Log::warning("Analytics collection job finished with errors for account #{$this->socialAccount->id}: " . ($result['error'] ?? 'Unknown error'));
        }
    }
}
