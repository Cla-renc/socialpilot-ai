<?php

namespace App\Jobs\Analytics;

use App\Models\SocialAccount;
use App\Services\Analytics\AnalyticsManager;
use App\Services\Analytics\AnalyticsCollectionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CollectAudienceInsightsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public function __construct(
        public SocialAccount $socialAccount,
        public array $options = []
    ) {}

    public function handle(AnalyticsManager $analyticsManager, AnalyticsCollectionService $collectionService): void
    {
        $provider = $analyticsManager->forAccount($this->socialAccount);
        $result = $provider->getAudienceInsights($this->socialAccount, $this->options);

        if ($result['success'] && !empty($result['data']['dimensions'])) {
            $collectionService->persistAudienceInsights($this->socialAccount, $result['data']['dimensions']);
        }
    }
}
