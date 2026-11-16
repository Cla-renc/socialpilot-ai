<?php

namespace App\Jobs;

use App\Models\Brand;
use App\Services\AutopilotService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessAutopilotSchedulingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public ?int $brandId = null
    ) {}

    public function handle(AutopilotService $autopilotService): void
    {
        if ($this->brandId) {
            $brand = Brand::find($this->brandId);
            if ($brand) {
                $autopilotService->runAutopilotForBrand($brand);
            }
        } else {
            $autopilotService->runAutopilotForAllActiveBrands();
        }
    }
}
