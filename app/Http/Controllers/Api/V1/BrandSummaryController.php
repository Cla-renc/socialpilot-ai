<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use Illuminate\Http\Request;

class BrandSummaryController extends Controller
{
    public function index(Request $request, Brand $brand)
    {
        abort_unless($brand->user_id === $request->user()->id, 403, 'Unauthorized brand access.');

        $latestAnalytics = $brand->analytics()->latest('metric_date')->first();
        $publishedPosts = $brand->posts()->where('status', 'published')->count();
        $scheduledPosts = $brand->posts()->where('status', 'scheduled')->count();
        $drafts = $brand->contentDrafts()->count();
        $campaigns = $brand->campaigns()->count();
        $activeCampaigns = $brand->campaigns()->where('status', 'active')->count();
        $topPlatform = $brand->posts()
            ->selectRaw('platform, COUNT(*) as post_count')
            ->groupBy('platform')
            ->orderByDesc('post_count')
            ->first();
        $lastPublished = $brand->posts()
            ->whereNotNull('published_at')
            ->latest('published_at')
            ->first();

        return response()->json([
            'data' => [
                'brand_id' => $brand->id,
                'brand_name' => $brand->name,
                'total_posts' => $brand->posts()->count(),
                'published_posts' => $publishedPosts,
                'scheduled_posts' => $scheduledPosts,
                'drafts' => $drafts,
                'campaigns' => $campaigns,
                'active_campaigns' => $activeCampaigns,
                'followers' => $latestAnalytics?->followers ?? 0,
                'reach' => $latestAnalytics?->reach ?? 0,
                'impressions' => $latestAnalytics?->impressions ?? 0,
                'engagements' => $latestAnalytics?->engagements ?? 0,
                'engagement_rate' => (float) ($latestAnalytics?->engagement_rate ?? 0),
                'top_platform' => $topPlatform ? [
                    'platform' => $topPlatform->platform,
                    'post_count' => (int) $topPlatform->post_count,
                ] : null,
                'last_published_at' => $lastPublished?->published_at?->toISOString(),
            ],
        ]);
    }
}
