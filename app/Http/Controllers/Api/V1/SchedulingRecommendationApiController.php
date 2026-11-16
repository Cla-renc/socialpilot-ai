<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Services\BestTimeToPostService;
use App\Services\SchedulingConflictService;
use App\Services\SchedulingIntelligenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SchedulingRecommendationApiController extends Controller
{
    public function __construct(
        protected SchedulingIntelligenceService $intelligence,
        protected BestTimeToPostService $bestTimeService,
        protected SchedulingConflictService $conflictService
    ) {}

    /**
     * Get tailored scheduling strategy & recommendations for a brand.
     */
    public function recommendations(Request $request, Brand $brand): JsonResponse
    {
        $this->authorizeBrandOwner($request, $brand);

        $strategy = $this->intelligence->getStrategy($brand);

        return response()->json([
            'brand_id'   => $brand->id,
            'brand_name' => $brand->name,
            'strategy'   => $strategy,
        ]);
    }

    /**
     * Get Best Time to Post recommendations for a brand and optional platform.
     */
    public function bestTimes(Request $request, Brand $brand): JsonResponse
    {
        $this->authorizeBrandOwner($request, $brand);

        $platform = $request->query('platform');
        $forceRefresh = (bool) $request->boolean('refresh');

        $recommendations = $this->bestTimeService->getRecommendationsForBrand($brand, $platform, $forceRefresh);

        return response()->json([
            'brand_id'   => $brand->id,
            'brand_name' => $brand->name,
            'platform'   => $platform,
            'timezone'   => $brand->settings?->timezone ?: 'UTC',
            'count'      => $recommendations->count(),
            'data'       => $recommendations,
        ]);
    }

    /**
     * Detect conflicts for a proposed scheduled post.
     */
    public function conflicts(Request $request, Brand $brand): JsonResponse
    {
        $this->authorizeBrandOwner($request, $brand);

        $validated = $request->validate([
            'scheduled_at'      => ['required', 'string'],
            'platform'          => ['nullable', 'string', 'max:32'],
            'social_account_id' => ['nullable', 'integer', 'exists:social_accounts,id'],
            'ignore_post_id'    => ['nullable', 'integer'],
        ]);

        $report = $this->conflictService->check(
            $brand,
            $validated,
            ignorePostId: $validated['ignore_post_id'] ?? null
        );

        return response()->json([
            'brand_id' => $brand->id,
            'report'   => $report,
        ]);
    }

    protected function authorizeBrandOwner(Request $request, Brand $brand): void
    {
        if ($brand->user_id !== $request->user()->id) {
            abort(403, 'Unauthorized access to brand recommendations.');
        }
    }
}
