<?php

namespace App\Services\Analytics;

use App\Models\AiOptimizationRecommendation;
use App\Models\AnalyticsReport;
use App\Models\Brand;
use App\Models\PostAnalytic;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AnalyticsReportingService
{
    public function __construct(
        protected AnalyticsAggregationService $aggregationService,
        protected AnalyticsComparisonService $comparisonService
    ) {}

    /**
     * Generate an analytics report synchronously or asynchronously.
     */
    public function generateReport(
        User $user,
        Brand $brand,
        string $reportType = 'brand_summary',
        string $format = 'pdf',
        ?string $startDate = null,
        ?string $endDate = null,
        ?AnalyticsReport $existingReport = null
    ): AnalyticsReport {
        $format = strtolower($format) === 'csv' ? 'csv' : 'pdf';

        $report = $existingReport ?? AnalyticsReport::create([
            'user_id'          => $user->id,
            'brand_id'         => $brand->id,
            'report_type'      => $reportType,
            'format'           => $format,
            'status'           => 'processing',
            'date_range_start' => $startDate ? \Carbon\Carbon::parse($startDate) : now()->subDays(30),
            'date_range_end'   => $endDate ? \Carbon\Carbon::parse($endDate) : now(),
        ]);

        if ($existingReport) {
            $report->update(['status' => 'processing']);
        }

        try {
            $data = $this->compileReportData($brand, $reportType, $report->date_range_start, $report->date_range_end);

            $fileName = sprintf(
                'report_%s_%s_%s.%s',
                Str::slug($brand->name),
                $reportType,
                now()->format('Ymd_His'),
                $format
            );

            $relativePath = "reports/{$brand->id}/{$fileName}";

            if ($format === 'csv') {
                $content = $this->buildCsv($data, $reportType);
            } else {
                $content = $this->buildPdf($data, $brand, $reportType, $report);
            }

            Storage::disk('local')->put($relativePath, $content);

            $report->update([
                'status'       => 'completed',
                'file_path'    => $relativePath,
                'file_name'    => $fileName,
                'generated_at' => now(),
                'metadata'     => [
                    'metrics_count' => count($data['metrics'] ?? []),
                    'generated_by'  => $user->id,
                ],
            ]);
        } catch (\Throwable $e) {
            $report->update([
                'status'   => 'failed',
                'metadata' => ['error' => $e->getMessage()],
            ]);
            throw $e;
        }

        return $report;
    }

    /**
     * Compile structured analytics data for the report.
     */
    public function compileReportData(Brand $brand, string $reportType, $start, $end): array
    {
        $brandAnalytics = $this->aggregationService->getBrandAnalytics(
            $brand,
            'custom',
            $start->toDateString(),
            $end->toDateString()
        );

        $comparison = $this->comparisonService->comparePeriods(
            $brand,
            'custom',
            $start->toDateString(),
            $end->toDateString()
        );

        $topPosts = $this->aggregationService->getTopPostsForBrands([$brand->id], 10);

        $recommendations = AiOptimizationRecommendation::where('brand_id', $brand->id)
            ->where('recommendation_status', 'active')
            ->orderByDesc('created_at')
            ->take(5)
            ->get();

        return [
            'brand'           => [
                'id'       => $brand->id,
                'name'     => $brand->name,
                'industry' => $brand->industry,
            ],
            'period'          => [
                'start' => $start->toDateString(),
                'end'   => $end->toDateString(),
            ],
            'metrics'         => $brandAnalytics['metrics'] ?? [],
            'platforms'       => $brandAnalytics['platforms'] ?? [],
            'comparison'      => $comparison['metrics'] ?? [],
            'top_posts'       => $topPosts,
            'recommendations' => $recommendations,
        ];
    }

    /**
     * Build RFC 4180 compliant CSV export.
     */
    protected function buildCsv(array $data, string $reportType): string
    {
        $handle = fopen('php://temp', 'r+');

        // Header info
        fputcsv($handle, ['SocialPilot AI - Performance Analytics Report']);
        fputcsv($handle, ['Brand', $data['brand']['name']]);
        fputcsv($handle, ['Reporting Period', "{$data['period']['start']} to {$data['period']['end']}"]);
        fputcsv($handle, ['Generated At', now()->toIso8601String()]);
        fputcsv($handle, []);

        // Key Metrics Summary
        fputcsv($handle, ['--- KEY PERFORMANCE METRICS ---']);
        fputcsv($handle, ['Metric Name', 'Observed Value', 'Previous Period Value', 'Change (%)']);
        foreach ($data['metrics'] as $name => $val) {
            $prev = $data['comparison'][$name]['previous'] ?? 'N/A';
            $chg = isset($data['comparison'][$name]['percentage_change'])
                ? $data['comparison'][$name]['percentage_change'] . '%'
                : 'N/A';
            fputcsv($handle, [ucwords(str_replace('_', ' ', $name)), $val, $prev, $chg]);
        }
        fputcsv($handle, []);

        // Top Posts Table
        fputcsv($handle, ['--- TOP PERFORMING CONTENT ---']);
        fputcsv($handle, ['Platform', 'Post ID', 'Views', 'Impressions', 'Likes', 'Comments', 'Shares']);
        foreach ($data['top_posts'] as $post) {
            fputcsv($handle, [
                ucfirst($post->platform),
                $post->external_post_id,
                $post->views ?? 0,
                $post->impressions ?? 0,
                $post->likes ?? 0,
                $post->comments ?? 0,
                $post->shares ?? 0,
            ]);
        }
        fputcsv($handle, []);

        // AI Recommendations
        if (!empty($data['recommendations'])) {
            fputcsv($handle, ['--- AI PERFORMANCE OPTIMIZATION RECOMMENDATIONS ---']);
            fputcsv($handle, ['Title', 'Confidence', 'Description']);
            foreach ($data['recommendations'] as $rec) {
                fputcsv($handle, [
                    $rec->title,
                    strtoupper($rec->confidence_level),
                    $rec->description,
                ]);
            }
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return $csv;
    }

    /**
     * Build clean standard PDF document with brand identity and tables.
     */
    protected function buildPdf(array $data, Brand $brand, string $reportType, AnalyticsReport $report): string
    {
        // Construct standard valid PDF 1.4 stream
        $title = "SocialPilot AI - " . ucwords(str_replace('_', ' ', $reportType));
        $brandName = $brand->name;
        $periodStr = "Period: {$data['period']['start']} to {$data['period']['end']}";
        $genStr = "Generated on: " . now()->format('M d, Y H:i UTC');

        $lines = [
            "============================================================",
            "                 SOCIALPILOT AI ANALYTICS REPORT            ",
            "============================================================",
            "",
            "Brand: {$brandName}",
            $periodStr,
            $genStr,
            "",
            "----------------- KEY PERFORMANCE METRICS -----------------",
        ];

        foreach ($data['metrics'] as $key => $val) {
            $formatted = is_numeric($val) ? number_format((float) $val, $key === 'engagement_rate' ? 2 : 0) : (string)$val;
            $unit = $key === 'engagement_rate' ? '%' : '';
            $lines[] = sprintf("  %-25s : %s %s", ucwords(str_replace('_', ' ', $key)), $formatted, $unit);
        }

        $lines[] = "";
        $lines[] = "----------------- TOP PERFORMING CONTENT ------------------";
        if (count($data['top_posts']) === 0) {
            $lines[] = "  No post analytics collected for this period.";
        } else {
            foreach ($data['top_posts'] as $idx => $p) {
                $lines[] = sprintf(
                    "  #%d [%s] Post: %s | Views: %d | Likes: %d | Comments: %d",
                    $idx + 1,
                    strtoupper($p->platform),
                    substr($p->external_post_id, 0, 16),
                    $p->views ?? 0,
                    $p->likes ?? 0,
                    $p->comments ?? 0
                );
            }
        }

        $lines[] = "";
        $lines[] = "------------- AI PERFORMANCE OPTIMIZATIONS ----------------";
        if (count($data['recommendations']) === 0) {
            $lines[] = "  Insufficient historical data to generate reliable recommendations.";
        } else {
            foreach ($data['recommendations'] as $rec) {
                $lines[] = sprintf("  * [%s CONFIDENCE] %s", strtoupper($rec->confidence_level), $rec->title);
                $lines[] = "    " . substr($rec->description, 0, 80);
            }
        }

        $lines[] = "";
        $lines[] = "------------------------------------------------------------";
        $lines[] = "Note: Metrics reflect actual platform API data and internal";
        $lines[] = "calculations. No metrics are simulated or fabricated.";

        return $this->generateRawPdf($lines, $title);
    }

    /**
     * Pure PHP standard PDF-1.4 binary stream generator (no external dependencies required).
     */
    protected function generateRawPdf(array $textLines, string $docTitle): string
    {
        $stream = "BT /F1 10 Tf 50 750 Td 14 TL\n";
        foreach ($textLines as $line) {
            // Escape parentheses and backslashes for PDF text strings
            $safe = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $line);
            $stream .= "({$safe}) '\n";
        }
        $stream .= "ET";

        $objects = [];
        $objects[1] = "<< /Type /Catalog /Pages 2 0 R >>";
        $objects[2] = "<< /Type /Pages /Kids [3 0 R] /Count 1 >>";
        $objects[3] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>";
        $objects[4] = "<< /Length " . strlen($stream) . " >>\nstream\n{$stream}\nendstream";
        $objects[5] = "<< /Type /Font /Subtype /Type1 /BaseFont /Courier >>";

        $pdf = "%PDF-1.4\n";
        $xref = [0 => "0000000000 65535 f \n"];

        for ($i = 1; $i <= 5; $i++) {
            $offset = strlen($pdf);
            $xref[$i] = sprintf("%010d 00000 n \n", $offset);
            $pdf .= "{$i} 0 obj\n" . $objects[$i] . "\nendobj\n";
        }

        $xrefOffset = strlen($pdf);
        $pdf .= "xref\n0 6\n";
        foreach ($xref as $entry) {
            $pdf .= $entry;
        }
        $pdf .= "trailer\n<< /Size 6 /Root 1 0 R >>\nstartxref\n{$xrefOffset}\n%%EOF";

        return $pdf;
    }
}
