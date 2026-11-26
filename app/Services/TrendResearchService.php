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

        $force = (bool) ($filters['force'] ?? false);
        $dedupWindow = max(0, (int) config('services.trends.dedup_window_seconds', 300));

        $record = DB::transaction(function () use ($brand, $user, $criteria, $criteriaHash, $force, $dedupWindow): array {
            Brand::query()->whereKey($brand->id)->lockForUpdate()->firstOrFail();
            $active = TrendResearchRecord::query()
                ->where('brand_id', $brand->id)
                ->where('criteria_hash', $criteriaHash)
                ->whereIn('status', ['queued', 'running'])
                ->lockForUpdate()
                ->latest('id')
                ->first();

            if ($active) {
                return [$active, false];
            }

            if (! $force && $dedupWindow > 0) {
                // Primary dedup: completed/partial jobs with a recent completed_at
                // (handles the common path and async completions).
                $windowStart = now()->subSeconds($dedupWindow);
                $recent = TrendResearchRecord::query()
                    ->where('brand_id', $brand->id)
                    ->where('criteria_hash', $criteriaHash)
                    ->whereIn('status', ['completed', 'partial'])
                    ->where('completed_at', '>=', $windowStart)
                    ->lockForUpdate()
                    ->latest('id')
                    ->first();

                // Fallback dedup: job created within the window whose completed_at
                // has not yet propagated (e.g. distributed-DB visibility lag for
                // synchronous same-request completions on TiDB Cloud Serverless).
                if (! $recent) {
                    $recent = TrendResearchRecord::query()
                        ->where('brand_id', $brand->id)
                        ->where('criteria_hash', $criteriaHash)
                        ->whereIn('status', ['completed', 'partial'])
                        ->where('created_at', '>=', $windowStart)
                        ->lockForUpdate()
                        ->latest('id')
                        ->first();
                }

                if ($recent) {
                    return [$recent, false];
                }
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
