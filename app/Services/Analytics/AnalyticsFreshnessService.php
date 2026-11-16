<?php

namespace App\Services\Analytics;

use App\Models\AnalyticsCollectionLog;
use App\Models\AnalyticsSnapshot;
use App\Models\SocialAccount;

class AnalyticsFreshnessService
{
    /**
     * Compute data freshness indicators for an account.
     */
    public function getAccountFreshness(SocialAccount $account): array
    {
        $lastLog = AnalyticsCollectionLog::where('social_account_id', $account->id)
            ->latest('started_at')
            ->first();

        $lastSnapshot = AnalyticsSnapshot::where('social_account_id', $account->id)
            ->latest('collected_at')
            ->first();

        $collectedAt = $lastSnapshot?->collected_at ?? $lastLog?->completed_at;

        if (!$collectedAt) {
            return [
                'status'           => 'never_collected',
                'label'            => 'Never collected',
                'last_collected_at'=> null,
                'age_in_hours'     => null,
                'is_stale'         => true,
            ];
        }

        $ageHours = round(now()->diffInMinutes($collectedAt) / 60, 1);

        if ($ageHours <= 6) {
            $status = 'fresh';
            $label = 'Fresh (< 6h)';
            $isStale = false;
        } elseif ($ageHours <= 24) {
            $status = 'moderate';
            $label = 'Up to date (< 24h)';
            $isStale = false;
        } else {
            $status = 'stale';
            $label = 'Stale (> 24h)';
            $isStale = true;
        }

        return [
            'status'            => $status,
            'label'             => $label,
            'last_collected_at' => $collectedAt->toIso8601String(),
            'age_in_hours'      => $ageHours,
            'is_stale'          => $isStale,
            'last_run_status'   => $lastLog?->status ?? 'unknown',
        ];
    }

    /**
     * Compute brand-wide freshness summary across all accounts.
     */
    public function getBrandFreshness(int $brandId): array
    {
        $accounts = SocialAccount::where('brand_id', $brandId)
            ->where('status', 'connected')
            ->get();

        if ($accounts->isEmpty()) {
            return [
                'overall_status' => 'no_accounts',
                'label'          => 'No connected accounts',
                'accounts'       => [],
            ];
        }

        $accountFreshness = [];
        $hasStale = false;

        foreach ($accounts as $account) {
            $freshness = $this->getAccountFreshness($account);
            $accountFreshness[$account->id] = array_merge($freshness, [
                'platform'     => $account->provider,
                'display_name' => $account->display_name,
            ]);
            if ($freshness['is_stale']) {
                $hasStale = true;
            }
        }

        return [
            'overall_status' => $hasStale ? 'attention_needed' : 'all_fresh',
            'label'          => $hasStale ? 'Some accounts have stale analytics' : 'All accounts up to date',
            'accounts'       => $accountFreshness,
        ];
    }
}
