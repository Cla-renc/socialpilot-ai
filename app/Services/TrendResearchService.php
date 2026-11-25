<?php

namespace App\Services;

use App\Jobs\RunTrendResearch;
use App\Models\Brand;
use App\Models\TrendResearchJob as TrendResearchRecord;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class TrendResearchService
{
    public function start(Brand $brand, User $user, array $filters, bool $sync = false): array
    {
        $defaultSources = config('services.trends.rss_feeds', []) ? ['rss', 'google_trends'] : ['google_trends'];
        $selectedSources = ! empty($filters['sources']) ? array_values($filters['sources']) : $defaultSources;

        $criteria = [
            'categories' => array_values($filters['categories'] ?? []),
            'geographic_market' => $filters['geographic_market'] ?? $brand->region,
            'days' => (int) ($filters['days'] ?? 30),
            'sources' => $selectedSources,
        ];
        sort($criteria['categories']);
        sort($criteria['sources']);
        $criteriaHash = hash('sha256', json_encode($criteria, JSON_THROW_ON_ERROR));

        $record = DB::transaction(function () use ($brand, $user, $criteria, $criteriaHash): array {
            Brand::query()->whereKey($brand->id)->lockForUpdate()->firstOrFail();
            $active = TrendResearchRecord::query()
                ->where('brand_id', $brand->id)
                ->where('criteria_hash', $criteriaHash)
                ->whereIn('status', ['queued', 'running'])
                ->latest('id')
                ->first();

            if ($active) {
                return [$active, false];
            }

            $record = $brand->trendResearchJobs()->create([
                'user_id' => $user->id,
                'status' => 'queued',
                'criteria_hash' => $criteriaHash,
                'filters' => $criteria,
            ]);

            return [$record, true];
        });

        [$record, $created] = $record;
        if ($created) {
            if ($sync) {
                try {
                    $job = new RunTrendResearch($record->id);
                    $job->handle();
                } catch (\Throwable $e) {
                    $job = new RunTrendResearch($record->id);
                    $job->failed($e);
                }
            } else {
                RunTrendResearch::dispatch($record->id)
                    ->onConnection('database')
                    ->afterCommit();
            }
        }

        return [$record->fresh(['topics.sources']), $created];
    }
}
