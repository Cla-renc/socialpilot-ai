<?php

namespace App\Console\Commands;

use App\Models\Brand;
use App\Services\AutopilotService;
use Illuminate\Console\Command;

class ProcessAutopilotCommand extends Command
{
    protected $signature = 'autopilot:process {--brand= : Specific brand ID to process}';
    protected $description = 'Process AI autopilot scheduling cycles for active brands';

    public function handle(AutopilotService $autopilotService): int
    {
        $brandId = $this->option('brand');

        if ($brandId) {
            $brand = Brand::find($brandId);
            if (!$brand) {
                $this->error("Brand #{$brandId} not found.");
                return self::FAILURE;
            }
            $this->info("Running autopilot for brand #{$brandId} ({$brand->name})...");
            $result = $autopilotService->runAutopilotForBrand($brand);
            $this->info("Completed. Status: {$result['status']}");
            return self::SUCCESS;
        }

        $this->info("Processing autopilot across all active brands...");
        $count = $autopilotService->runAutopilotForAllActiveBrands();
        $this->info("Processed autopilot for {$count} brand(s).");
        return self::SUCCESS;
    }
}
