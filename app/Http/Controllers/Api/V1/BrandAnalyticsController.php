<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\BrandAnalytics;
use Illuminate\Http\Request;

class BrandAnalyticsController extends Controller
{
    public function index(Request $request, Brand $brand)
    {
        abort_unless($brand->user_id === $request->user()->id, 403, 'Unauthorized brand access.');

        $records = $brand->analytics()->latest('metric_date')->get();

        return response()->json([
            'data' => $records->map(fn (BrandAnalytics $record) => [
                'id' => $record->id,
                'brand_id' => $record->brand_id,
                'metric_date' => $record->metric_date->toDateString(),
                'followers' => $record->followers,
                'reach' => $record->reach,
                'impressions' => $record->impressions,
                'engagements' => $record->engagements,
                'clicks' => $record->clicks,
                'shares' => $record->shares,
                'saves' => $record->saves,
                'replies' => $record->replies,
                'engagement_rate' => (float) $record->engagement_rate,
                'metadata' => $record->metadata,
            ]),
        ]);
    }

    public function store(Request $request, Brand $brand)
    {
        abort_unless($brand->user_id === $request->user()->id, 403, 'Unauthorized brand access.');

        $validated = $request->validate([
            'metric_date' => ['required', 'date'],
            'followers' => ['nullable', 'integer', 'min:0'],
            'reach' => ['nullable', 'integer', 'min:0'],
            'impressions' => ['nullable', 'integer', 'min:0'],
            'engagements' => ['nullable', 'integer', 'min:0'],
            'clicks' => ['nullable', 'integer', 'min:0'],
            'shares' => ['nullable', 'integer', 'min:0'],
            'saves' => ['nullable', 'integer', 'min:0'],
            'replies' => ['nullable', 'integer', 'min:0'],
            'engagement_rate' => ['nullable', 'numeric', 'min:0'],
            'metadata' => ['nullable', 'array'],
        ]);

        $record = $brand->analytics()->updateOrCreate(
            ['brand_id' => $brand->id, 'metric_date' => $validated['metric_date']],
            [
                'followers' => $validated['followers'] ?? 0,
                'reach' => $validated['reach'] ?? 0,
                'impressions' => $validated['impressions'] ?? 0,
                'engagements' => $validated['engagements'] ?? 0,
                'clicks' => $validated['clicks'] ?? 0,
                'shares' => $validated['shares'] ?? 0,
                'saves' => $validated['saves'] ?? 0,
                'replies' => $validated['replies'] ?? 0,
                'engagement_rate' => $validated['engagement_rate'] ?? 0,
                'metadata' => $validated['metadata'] ?? [],
            ]
        );

        return response()->json([
            'data' => [
                'id' => $record->id,
                'brand_id' => $record->brand_id,
                'metric_date' => $record->metric_date->toDateString(),
                'engagement_rate' => (float) $record->engagement_rate,
            ],
        ], 201);
    }
}
