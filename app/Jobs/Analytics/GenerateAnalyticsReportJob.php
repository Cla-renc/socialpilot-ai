<?php

namespace App\Jobs\Analytics;

use App\Models\AnalyticsReport;
use App\Models\Brand;
use App\Models\User;
use App\Services\Analytics\AnalyticsReportingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GenerateAnalyticsReportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $userOrReport;
    public ?Brand $brand = null;
    public string $reportType = 'brand_summary';
    public string $format = 'pdf';
    public ?string $startDate = null;
    public ?string $endDate = null;

    public function __construct(
        $userOrReport,
        ?Brand $brand = null,
        string $reportType = 'brand_summary',
        string $format = 'pdf',
        ?string $startDate = null,
        ?string $endDate = null
    ) {
        $this->userOrReport = $userOrReport;
        $this->brand = $brand;
        $this->reportType = $reportType;
        $this->format = $format;
        $this->startDate = $startDate;
        $this->endDate = $endDate;
    }

    public function handle(AnalyticsReportingService $reportingService): void
    {
        if (is_int($this->userOrReport) || is_numeric($this->userOrReport)) {
            $report = AnalyticsReport::find((int) $this->userOrReport);
            if ($report) {
                $reportingService->generateReport(
                    $report->user,
                    $report->brand,
                    $report->report_type,
                    $report->format,
                    $report->date_range_start?->toDateString(),
                    $report->date_range_end?->toDateString(),
                    $report
                );
            }
            return;
        }

        if ($this->userOrReport instanceof AnalyticsReport) {
            $report = $this->userOrReport;
            $reportingService->generateReport(
                $report->user,
                $report->brand,
                $report->report_type,
                $report->format,
                $report->date_range_start?->toDateString(),
                $report->date_range_end?->toDateString(),
                $report
            );
            return;
        }

        if ($this->userOrReport instanceof User && $this->brand) {
            $reportingService->generateReport(
                $this->userOrReport,
                $this->brand,
                $this->reportType,
                $this->format,
                $this->startDate,
                $this->endDate
            );
        }
    }
}
