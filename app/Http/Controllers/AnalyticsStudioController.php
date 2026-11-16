<?php
namespace App\Http\Controllers;

use App\Models\AiOptimizationRecommendation;
use App\Models\AnalyticsCollectionLog;
use App\Models\AnalyticsReport;
use App\Models\AnalyticsSnapshot;
use App\Models\AudienceInsight;
use App\Models\Brand;
use App\Models\PostAnalytic;
use App\Models\SocialAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AnalyticsStudioController extends Controller
{
    public function dashboard()
    {
        $user = Auth::user();
        $brands = $user->brands()->with('socialAccounts')->get();
        $totalSnapshots = AnalyticsSnapshot::whereIn('brand_id', $brands->pluck('id'))->count();
        $totalPosts = PostAnalytic::whereIn('social_account_id',
            SocialAccount::whereIn('brand_id', $brands->pluck('id'))->pluck('id')
        )->count();
        $pendingRecs = AiOptimizationRecommendation::whereIn('brand_id', $brands->pluck('id'))
            ->where('status', 'pending')->count();
        $recentLogs = AnalyticsCollectionLog::whereIn('social_account_id',
            SocialAccount::whereIn('brand_id', $brands->pluck('id'))->pluck('id')
        )->orderByDesc('started_at')->limit(5)->get();

        $stats = [
            'total_snapshots'        => $totalSnapshots,
            'total_posts_tracked'    => $totalPosts,
            'pending_recommendations'=> $pendingRecs,
            'brands_connected'       => $brands->count(),
            'accounts_connected'     => $brands->sum(fn($b) => $b->socialAccounts->count()),
        ];

        return view('analytics.dashboard', compact('brands', 'stats', 'recentLogs'));
    }

    public function brandAnalytics(Brand $brand)
    {
        if ($brand->user_id !== Auth::id()) abort(403);

        $brand->load('socialAccounts');
        $accountIds = $brand->socialAccounts->pluck('id');

        $snapshots = AnalyticsSnapshot::where('brand_id', $brand->id)
            ->orderByDesc('collected_at')->limit(100)->get()
            ->groupBy('metric_name');

        $topPosts = PostAnalytic::whereIn('social_account_id', $accountIds)
            ->where('metric_name', 'engagement_rate')
            ->orderByDesc('metric_value')->limit(5)->get();

        return view('analytics.brand', compact('brand', 'snapshots', 'topPosts'));
    }

    public function platformAnalytics(Request $request)
    {
        $user = Auth::user();
        $brands = $user->brands()->pluck('id');
        $platform = $request->input('platform', 'all');

        $snapshots = AnalyticsSnapshot::whereIn('brand_id', $brands)
            ->when($platform !== 'all', fn($q) => $q->where('platform', $platform))
            ->orderByDesc('collected_at')->limit(200)->get()
            ->groupBy('platform');

        $platforms = SocialAccount::whereIn('brand_id', $brands)
            ->distinct()->pluck('provider')->unique()->values();

        return view('analytics.platform', compact('snapshots', 'platforms', 'platform'));
    }

    public function contentPerformance(Request $request)
    {
        $user = Auth::user();
        $brands = $user->brands()->with('socialAccounts')->get();
        $brandIds = $brands->pluck('id');
        $accountIds = SocialAccount::whereIn('brand_id', $brandIds)->pluck('id');

        $selectedBrand = $request->brand_id ? $brands->find($request->brand_id) : null;
        $filterAccountIds = $selectedBrand
            ? $selectedBrand->socialAccounts->pluck('id')
            : $accountIds;

        $topPosts = PostAnalytic::whereIn('social_account_id', $filterAccountIds)
            ->where('metric_name', 'engagement_rate')
            ->orderByDesc('metric_value')->limit(20)->get();

        $recentPosts = PostAnalytic::whereIn('social_account_id', $filterAccountIds)
            ->orderByDesc('collected_at')->limit(20)->get();

        return view('analytics.content', compact('brands', 'selectedBrand', 'topPosts', 'recentPosts'));
    }

    public function audienceInsights(Request $request)
    {
        $user = Auth::user();
        $brandIds = $user->brands()->pluck('id');
        $selectedBrand = $request->brand_id ? Brand::find($request->brand_id) : null;
        if ($selectedBrand && $selectedBrand->user_id !== $user->id) abort(403);

        $insights = AudienceInsight::whereIn('brand_id', $brandIds)
            ->when($selectedBrand, fn($q) => $q->where('brand_id', $selectedBrand->id))
            ->orderByDesc('collected_at')->get()->groupBy('insight_type');

        $brands = $user->brands()->get();
        return view('analytics.audience', compact('insights', 'brands', 'selectedBrand'));
    }

    public function recommendations(Request $request)
    {
        $user = Auth::user();
        $brandIds = $user->brands()->pluck('id');
        $status = $request->input('status', 'pending');

        $recs = AiOptimizationRecommendation::whereIn('brand_id', $brandIds)
            ->when($status !== 'all', fn($q) => $q->where('status', $status))
            ->orderByDesc('created_at')->paginate(20);

        $brands = $user->brands()->get();
        return view('analytics.recommendations', compact('recs', 'brands', 'status'));
    }

    public function reports()
    {
        $user = Auth::user();
        $reports = AnalyticsReport::where('user_id', $user->id)
            ->orderByDesc('created_at')->paginate(20);
        $brands = $user->brands()->get();
        return view('analytics.reports', compact('reports', 'brands'));
    }

    public function reportDownload(AnalyticsReport $analyticsReport)
    {
        if ($analyticsReport->user_id !== Auth::id()) abort(403);
        if ($analyticsReport->status !== 'completed' || !$analyticsReport->file_path) {
            abort(404, 'Report not ready.');
        }
        return response()->download(storage_path('app/' . $analyticsReport->file_path));
    }
}
