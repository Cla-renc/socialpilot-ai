<?php

namespace App\Console\Commands;

use App\Models\BrandTrendPreference;
use App\Services\TrendResearchService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Throwable;

class ScheduleTrendResearch extends Command
{
    protected $signature = 'trends:schedule';

    protected $description = 'Queue research for brands with an enabled trend schedule';

    public function handle(TrendResearchService $research): int
    {
        $queued = 0;
        $preferences = BrandTrendPreference::query()
            ->whereIn('research_frequency', ['daily', 'weekly'])
            ->whereNotNull('research_time')
            ->with(['brand.user'])
            ->get();

        foreach ($preferences as $preference) {
            $localNow = now($preference->timezone);
            if ($localNow->format('H:i') !== substr((string) $preference->research_time, 0, 5)) {
                continue;
            }
            if ($preference->research_frequency === 'weekly' && $localNow->dayOfWeek !== 1) {
                continue;
            }

            $period = $preference->research_frequency === 'daily'
                ? $localNow->toDateString()
                : $localNow->startOfWeek()->toDateString();
            $cacheKey = "trend-schedule:{$preference->brand_id}:{$preference->research_frequency}:{$period}";
            $ttl = $preference->research_frequency === 'daily' ? 86400 : 691200;
            if (! Cache::add($cacheKey, true, $ttl)) {
                continue;
            }

            try {
                [$job, $created] = $research->start(
                    $preference->brand,
                    $preference->brand->user,
                    [
                        'days' => 30,
                        'categories' => $preference->categories ?? [],
                        'geographic_market' => $preference->geographic_market,
                        'sources' => $preference->preferred_sources ?? [],
                    ],
                );
                $queued += (int) $created;
                $this->info("Brand {$preference->brand_id}: research {$job->status}.");
            } catch (Throwable $exception) {
                Cache::forget($cacheKey);
                $this->warn("Brand {$preference->brand_id}: research was not queued: ".mb_substr($exception->getMessage(), 0, 300));
            }
        }

        $this->info("Queued {$queued} scheduled trend research job(s).");

        return self::SUCCESS;
    }
}
