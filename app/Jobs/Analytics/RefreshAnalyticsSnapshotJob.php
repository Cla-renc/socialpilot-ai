<?php

namespace App\Jobs\Analytics;

use App\Models\Brand;
use App\Services\Analytics\AnalyticsCollectionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RefreshAnalyticsSnapshotJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public Brand $brand,
        public array $options = []
    ) {}

    public function handle(AnalyticsCollectionService $collectionService): void
    {
        $collectionService->collectBrand($this->brand->id, $this->options);
    }
}
