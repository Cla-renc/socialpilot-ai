<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCampaignRequest;
use App\Models\Brand;
use App\Models\Campaign;
use App\Services\CampaignService;
use Illuminate\Http\Request;

class CampaignController extends Controller
{
    public function __construct(protected CampaignService $campaignService) {}

    public function index(Request $request, Brand $brand)
    {
        abort_unless($brand->user_id === $request->user()->id, 403, 'Unauthorized brand access.');

        $campaigns = $brand->campaigns()->latest()->get();

        return response()->json([
            'data' => $campaigns->map(fn (Campaign $campaign) => $this->transformCampaign($campaign)),
        ]);
    }

    public function store(StoreCampaignRequest $request, Brand $brand)
    {
        abort_unless($brand->user_id === $request->user()->id, 403, 'Unauthorized brand access.');

        $campaign = $this->campaignService->createCampaign($request->user(), $brand, $request->validated());

        return response()->json([
            'data' => $this->transformCampaign($campaign),
        ], 201);
    }

    public function show(Request $request, Brand $brand, Campaign $campaign)
    {
        abort_unless($brand->id === $campaign->brand_id && $brand->user_id === $request->user()->id, 403, 'Unauthorized campaign access.');

        return response()->json([
            'data' => $this->transformCampaign($campaign->load('posts')),
        ]);
    }

    protected function transformCampaign(Campaign $campaign): array
    {
        return [
            'id' => $campaign->id,
            'brand_id' => $campaign->brand_id,
            'user_id' => $campaign->user_id,
            'name' => $campaign->name,
            'objective' => $campaign->objective,
            'status' => $campaign->status,
            'start_date' => $campaign->start_date?->toDateString(),
            'end_date' => $campaign->end_date?->toDateString(),
            'timezone' => $campaign->timezone,
            'metadata' => $campaign->metadata,
            'posts_count' => $campaign->posts()->count(),
            'created_at' => $campaign->created_at?->toISOString(),
            'updated_at' => $campaign->updated_at?->toISOString(),
        ];
    }
}
