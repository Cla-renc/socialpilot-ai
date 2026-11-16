<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Jobs\Analytics\CollectAccountAnalyticsJob;
use App\Jobs\Analytics\GenerateAnalyticsReportJob;
use App\Jobs\Analytics\GenerateOptimizationRecommendationsJob;
use App\Models\AiOptimizationRecommendation;
use App\Models\AnalyticsCollectionLog;
use App\Models\AnalyticsReport;
use App\Models\AnalyticsSnapshot;
use App\Models\AudienceInsight;
use App\Models\Brand;
use App\Models\PostAnalytic;
use App\Models\SocialAccount;
use App\Services\Analytics\AnalyticsAggregationService;
use App\Services\Analytics\AnalyticsComparisonService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AnalyticsController extends Controller
{
    public function __construct(
        private readonly AnalyticsAggregationService $aggregation,
        private readonly AnalyticsComparisonService $comparison
    ) {}

    // GET /api/v1/analytics/overview
    public function overview(Request $request): JsonResponse
    {
        $period = $request->input('period', '30d');
        $user = $request->user();
        $brandIds = $user->brands()->pluck('id');
        $data = $this->aggregation->aggregateForUser($user->id, $period);
        $connectedAccountsCount = $data['connected_accounts_count'] ?? 0;
        $hasData = !empty($data['metrics']['total_impressions']) ||
                   !empty($data['metrics']['total_reach']) ||
                   !empty($data['metrics']['total_engagement']) ||
                   !empty($data['metrics']['total_followers']);

        return response()->json([
            'data' => $data,
            'period' => $period,
            'brand_count' => $brandIds->count(),
            'connected_accounts_count' => $connectedAccountsCount,
            'has_brands' => $brandIds->count() > 0,
            'has_connected_accounts' => $connectedAccountsCount > 0,
            'has_data' => $hasData,
        ]);
    }

    // GET /api/v1/analytics/summary
    public function summary(Request $request): JsonResponse
    {
        $user = $request->user();
        $brandId = $request->input('brand_id');
        $platform = $request->input('platform');
        $period = $request->input('period', '30d');

        if ($brandId) {
            $brand = Brand::find($brandId);
            if (!$brand || $brand->user_id !== $user->id) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }
        }

        $data = $this->aggregation->aggregateForUser($user->id, $period, $brandId, $platform);
        return response()->json(['data' => $data]);
    }

    // GET /api/v1/analytics/brands/{brand}
    public function brandAnalytics(Brand $brand, Request $request): JsonResponse
    {
        if ($brand->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        $period = $request->input('period', '30d');
        $data = $this->aggregation->aggregateForBrand($brand->id, $period);
        return response()->json(['data' => $data, 'brand' => ['id' => $brand->id, 'name' => $brand->name]]);
    }

    // GET /api/v1/analytics/brands/{brand}/comparison
    public function brandComparison(Brand $brand, Request $request): JsonResponse
    {
        if ($brand->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        $period = $request->input('period', '30d');
        $data = $this->comparison->comparePeriods($brand->id, $period);
        return response()->json(['data' => $data]);
    }

    // GET /api/v1/analytics/platforms
    public function platformAnalytics(Request $request): JsonResponse
    {
        $user = $request->user();
        $brandId = $request->input('brand_id');
        if ($brandId) {
            $brand = Brand::find($brandId);
            if (!$brand || $brand->user_id !== $user->id) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }
        }
        $platform = $request->input('platform');
        $period = $request->input('period', '30d');
        $data = $this->aggregation->aggregateByPlatform($user->id, $period, $brandId, $platform);
        return response()->json(['data' => $data]);
    }

    // GET /api/v1/analytics/accounts/{socialAccount}
    public function accountAnalytics(SocialAccount $socialAccount, Request $request): JsonResponse
    {
        $brand = $socialAccount->brand;
        if (!$brand || $brand->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        $period = $request->input('period', '30d');
        $data = $this->aggregation->aggregateForAccount($socialAccount->id, $period);
        return response()->json(['data' => $data, 'account' => ['id' => $socialAccount->id, 'platform' => $socialAccount->provider]]);
    }

    // GET /api/v1/analytics/posts
    public function posts(Request $request): JsonResponse
    {
        $user = $request->user();
        $brandIds = $user->brands()->pluck('id');
        $accountIds = SocialAccount::whereIn('brand_id', $brandIds)->pluck('id');

        $query = PostAnalytic::whereIn('social_account_id', $accountIds)
            ->when($request->brand_id, function ($q) use ($request) {
                $ids = SocialAccount::where('brand_id', $request->brand_id)->pluck('id');
                $q->whereIn('social_account_id', $ids);
            })
            ->when($request->platform, fn($q) => $q->where('platform', $request->platform))
            ->orderByDesc('collected_at');

        return response()->json($query->paginate(20));
    }

    // GET /api/v1/analytics/posts/{externalPostId}
    public function postShow(Request $request, string $externalPostId): JsonResponse
    {
        $user = $request->user();
        $brandIds = $user->brands()->pluck('id');
        $accountIds = SocialAccount::whereIn('brand_id', $brandIds)->pluck('id');

        $records = PostAnalytic::where('external_post_id', $externalPostId)
            ->whereIn('social_account_id', $accountIds)
            ->get();

        if ($records->isEmpty()) {
            return response()->json(['message' => 'Not found'], 404);
        }
        return response()->json(['data' => $records]);
    }

    // GET /api/v1/analytics/top-performing
    public function topPerforming(Request $request): JsonResponse
    {
        $user = $request->user();
        $brandIds = $user->brands()->pluck('id');
        $accountIds = SocialAccount::whereIn('brand_id', $brandIds)->pluck('id');

        $metric = $request->input('metric', 'engagement_rate');
        $posts = PostAnalytic::whereIn('social_account_id', $accountIds)
            ->where('metric_name', $metric)
            ->when($request->platform, fn($q) => $q->where('platform', $request->platform))
            ->orderByDesc('metric_value')
            ->limit(10)
            ->get();

        return response()->json(['data' => $posts]);
    }

    // GET /api/v1/analytics/underperforming
    public function underperforming(Request $request): JsonResponse
    {
        $user = $request->user();
        $brandIds = $user->brands()->pluck('id');
        $accountIds = SocialAccount::whereIn('brand_id', $brandIds)->pluck('id');

        $metric = $request->input('metric', 'engagement_rate');
        $posts = PostAnalytic::whereIn('social_account_id', $accountIds)
            ->where('metric_name', $metric)
            ->when($request->platform, fn($q) => $q->where('platform', $request->platform))
            ->orderBy('metric_value')
            ->limit(10)
            ->get();

        return response()->json(['data' => $posts]);
    }

    // GET /api/v1/analytics/audience
    public function audience(Request $request): JsonResponse
    {
        $user = $request->user();
        $brandIds = $user->brands()->pluck('id');
        $data = AudienceInsight::whereIn('brand_id', $brandIds)
            ->when($request->brand_id, fn($q) => $q->where('brand_id', $request->brand_id))
            ->when($request->platform, fn($q) => $q->where('platform', $request->platform))
            ->orderByDesc('collected_at')
            ->get();
        return response()->json(['data' => $data]);
    }

    // GET /api/v1/analytics/history
    public function history(Request $request): JsonResponse
    {
        $user = $request->user();
        $brandIds = $user->brands()->pluck('id');
        $data = AnalyticsSnapshot::whereIn('brand_id', $brandIds)
            ->when($request->brand_id, fn($q) => $q->where('brand_id', $request->brand_id))
            ->when($request->social_account_id, fn($q) => $q->where('social_account_id', $request->social_account_id))
            ->when($request->metric_name, fn($q) => $q->where('metric_name', $request->metric_name))
            ->orderByDesc('collected_at')
            ->paginate(50);
        return response()->json($data);
    }

    // POST /api/v1/analytics/collect
    public function triggerCollection(Request $request): JsonResponse
    {
        $validated = $request->validate(['social_account_id' => 'required|integer|exists:social_accounts,id']);
        $account = SocialAccount::findOrFail($validated['social_account_id']);
        $brand = $account->brand;
        if (!$brand || $brand->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        CollectAccountAnalyticsJob::dispatch($account);
        return response()->json(['message' => 'Analytics collection queued.', 'social_account_id' => $account->id], 202);
    }

    // GET /api/v1/analytics/collection-status
    public function collectionStatus(Request $request): JsonResponse
    {
        $user = $request->user();
        $brandIds = $user->brands()->pluck('id');
        $accountIds = SocialAccount::whereIn('brand_id', $brandIds)->pluck('id');
        $logs = AnalyticsCollectionLog::whereIn('social_account_id', $accountIds)
            ->when($request->social_account_id, fn($q) => $q->where('social_account_id', $request->social_account_id))
            ->orderByDesc('started_at')
            ->limit(50)
            ->get();
        return response()->json(['data' => $logs]);
    }

    // GET /api/v1/analytics/recommendations
    public function recommendations(Request $request): JsonResponse
    {
        $user = $request->user();
        $brandIds = $user->brands()->pluck('id');
        $data = AiOptimizationRecommendation::whereIn('brand_id', $brandIds)
            ->when($request->brand_id, fn($q) => $q->where('brand_id', $request->brand_id))
            ->when($request->status, fn($q) => $q->where('recommendation_status', $request->status))
            ->orderByDesc('created_at')
            ->paginate(20);
        return response()->json($data);
    }

    // POST /api/v1/analytics/recommendations/generate
    public function generateRecommendations(Request $request): JsonResponse
    {
        $validated = $request->validate(['brand_id' => 'required|integer|exists:brands,id']);
        $brand = Brand::findOrFail($validated['brand_id']);
        if ($brand->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        GenerateOptimizationRecommendationsJob::dispatch($brand, $request->user());
        return response()->json(['message' => 'AI recommendation generation queued.', 'brand_id' => $brand->id], 202);
    }

    // POST /api/v1/analytics/recommendations/{id}/accept
    public function acceptRecommendation(Request $request, int $id): JsonResponse
    {
        $rec = AiOptimizationRecommendation::find($id);
        if (!$rec) return response()->json(['message' => 'Not found'], 404);
        $brand = Brand::find($rec->brand_id);
        if (!$brand || $brand->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        $rec->update(['status' => 'accepted', 'recommendation_status' => 'accepted']);
        return response()->json(['data' => $rec]);
    }

    // POST /api/v1/analytics/recommendations/{id}/dismiss
    public function dismissRecommendation(Request $request, int $id): JsonResponse
    {
        $rec = AiOptimizationRecommendation::find($id);
        if (!$rec) return response()->json(['message' => 'Not found'], 404);
        $brand = Brand::find($rec->brand_id);
        if (!$brand || $brand->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        $rec->update(['status' => 'dismissed', 'recommendation_status' => 'dismissed']);
        return response()->json(['data' => $rec]);
    }

    // GET /api/v1/analytics/reports
    public function reports(Request $request): JsonResponse
    {
        $user = $request->user();
        $data = AnalyticsReport::where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->paginate(20);
        return response()->json($data);
    }

    // POST /api/v1/analytics/reports/generate
    public function generateReport(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'brand_id'     => 'required|integer|exists:brands,id',
            'report_type'  => 'required|string|in:overview,brand,platform,content,audience,brand_summary,platform_performance,content_performance,ai_optimization,custom',
            'period_start' => 'required|date',
            'period_end'   => 'required|date|after_or_equal:period_start',
            'format'       => 'required|string|in:pdf,csv',
        ]);
        $brand = Brand::findOrFail($validated['brand_id']);
        if ($brand->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        // Create report record first
        $report = AnalyticsReport::create([
            'user_id'          => $request->user()->id,
            'brand_id'         => $validated['brand_id'],
            'report_type'      => $validated['report_type'],
            'report_name'      => ucfirst($validated['report_type']) . ' Report',
            'period_start'     => $validated['period_start'],
            'period_end'       => $validated['period_end'],
            'date_range_start' => $validated['period_start'],
            'date_range_end'   => $validated['period_end'],
            'format'           => $validated['format'],
            'status'           => 'pending',
            'filters'          => [],
        ]);
        GenerateAnalyticsReportJob::dispatch($report->id);
        return response()->json(['message' => 'Report generation queued.', 'report_id' => $report->id], 202);
    }

    // GET /api/v1/analytics/reports/{id}
    public function reportShow(Request $request, int $id): JsonResponse
    {
        $report = AnalyticsReport::find($id);
        if (!$report) return response()->json(['message' => 'Not found'], 404);
        if ($report->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        $data = $report->toArray();
        if ($report->status === 'completed' && $report->file_path) {
            $data['download_url'] = url('/api/v1/analytics/reports/' . $report->id . '/download');
        }
        return response()->json(['data' => $data]);
    }

    // GET /api/v1/analytics/reports/{id}/download
    public function downloadReport(Request $request, int $id)
    {
        $report = AnalyticsReport::find($id);
        if (!$report) return response()->json(['message' => 'Not found'], 404);
        if ($report->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        if (!$report->file_path || !Storage::disk('local')->exists($report->file_path)) {
            return response()->json(['message' => 'Report file not found'], 404);
        }
        return Storage::disk('local')->download($report->file_path, $report->file_name ?? "report_{$id}.{$report->format}");
    }
}
