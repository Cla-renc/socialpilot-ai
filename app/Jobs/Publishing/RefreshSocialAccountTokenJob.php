<?php
namespace App\Jobs\Publishing;

use App\Models\SocialAccount;
use App\Services\Publishing\PublishingManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RefreshSocialAccountTokenJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 2;
    public int $timeout = 60;

    public function __construct(public readonly SocialAccount $account) {}

    public function handle(PublishingManager $manager): void
    {
        try {
            $provider = $manager->providerFor($this->account->platform);
            $result   = $provider->refreshToken($this->account);
            if ($result) {
                $this->account->update(['status' => 'active']);
                Log::info("Phase9: Token refreshed for account {$this->account->id}");
            }
        } catch (\Exception $e) {
            $this->account->update(['status' => 'token_expired']);
            Log::warning("Phase9: Token refresh failed for account {$this->account->id}: " . $e->getMessage());
        }
    }
}
