<?php
namespace App\Console\Commands;

use App\Jobs\Publishing\RefreshSocialAccountTokenJob;
use App\Models\SocialAccount;
use Illuminate\Console\Command;

class RefreshSocialTokensCommand extends Command
{
    protected $signature   = 'publishing:refresh-tokens';
    protected $description = 'Refresh expiring social media OAuth tokens';

    public function handle(): int
    {
        $accounts = SocialAccount::where('status', 'active')
            ->where(function ($q) {
                $q->whereNull('token_expires_at')
                  ->orWhere('token_expires_at', '<=', now()->addDay());
            })
            ->get();

        foreach ($accounts as $account) {
            RefreshSocialAccountTokenJob::dispatch($account);
        }

        $this->info("Queued token refresh for {$accounts->count()} accounts.");
        return self::SUCCESS;
    }
}
