<?php

namespace App\Console\Commands;

use App\Models\Brand;
use App\Models\SocialAccount;
use App\Services\Analytics\AnalyticsCollectionService;
use Illuminate\Console\Command;

class CollectSocialAnalyticsCommand extends Command
{
    protected $signature = 'analytics:collect 
                            {--account= : Specific Social Account ID} 
                            {--brand= : Specific Brand ID} 
                            {--platform= : Specific Platform (facebook, instagram, tiktok, youtube, linkedin)} 
                            {--type=full : Collection type (account, post, full)}';

    protected $description = 'Collect social media analytics and metrics from official platform APIs';

    public function handle(AnalyticsCollectionService $collectionService): int
    {
        $accountId = $this->option('account');
        $brandId = $this->option('brand');
        $platform = $this->option('platform');
        $type = $this->option('type') ?: 'full';

        $query = SocialAccount::query()->where('status', 'connected');

        if ($accountId) {
            $query->where('id', $accountId);
        }

        if ($brandId) {
            $query->where('brand_id', $brandId);
        }

        if ($platform) {
            $query->where('provider', strtolower($platform));
        }

        $accounts = $query->get();

        if ($accounts->isEmpty()) {
            $this->info('No connected social accounts found matching the criteria.');
            return Command::SUCCESS;
        }

        $this->info("Starting analytics collection for {$accounts->count()} connected account(s)...");

        $successCount = 0;
        $failCount = 0;

        foreach ($accounts as $account) {
            $this->line("Collecting {$account->provider} for Account #{$account->id} ({$account->display_name})...");
            $result = $collectionService->collectAccount($account, ['type' => $type]);

            if ($result['success']) {
                $this->info("✓ Success: {$result['records_collected']} records collected.");
                $successCount++;
            } else {
                $this->error("✗ Failed: " . ($result['error'] ?? 'Unknown error'));
                $failCount++;
            }
        }

        $this->info("Collection completed. Succeeded: {$successCount}, Failed: {$failCount}.");

        return $failCount === 0 ? Command::SUCCESS : Command::FAILURE;
    }
}
