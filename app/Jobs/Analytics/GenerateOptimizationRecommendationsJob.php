<?php

namespace App\Jobs\Analytics;

use App\Models\Brand;
use App\Models\User;
use App\Services\Analytics\AiOptimizationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GenerateOptimizationRecommendationsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public Brand $brand,
        public ?User $user = null
    ) {}

    public function handle(AiOptimizationService $optimizationService): void
    {
        $optimizationService->generateRecommendations($this->brand, $this->user);
    }
}
