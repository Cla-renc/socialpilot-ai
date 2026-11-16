<?php

namespace App\Jobs;

use App\Models\Brand;
use App\Services\BestTimeToPostService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RefreshSchedulingRecommendationsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $brandId
    ) {}

    public function handle(BestTimeToPostService $service): void
    {
        $brand = Brand::find($this->brandId);
        if ($brand) {
            $service->getRecommendationsForBrand($brand, forceRefresh: true);
        }
    }
}
