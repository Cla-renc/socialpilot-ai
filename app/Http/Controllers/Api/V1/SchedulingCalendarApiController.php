<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Services\ContentSchedulingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SchedulingCalendarApiController extends Controller
{
    public function __construct(
        protected ContentSchedulingService $schedulingService
    ) {}

    /**
     * Get calendar entries in range.
     */
    public function index(Request $request, Brand $brand): JsonResponse
    {
        if ($brand->user_id !== $request->user()->id) {
            abort(403, 'Unauthorized access to brand calendar.');
        }

        $start = $request->query('start_date') ?: now()->startOfMonth()->format('Y-m-d');
        $end = $request->query('end_date') ?: now()->endOfMonth()->format('Y-m-d');

        $filters = [
            'status'            => $request->query('status'),
            'platform'          => $request->query('platform'),
            'social_account_id' => $request->query('social_account_id'),
        ];

        $entries = $this->schedulingService->getCalendar($brand, $start, $end, array_filter($filters));

        return response()->json([
            'brand_id'   => $brand->id,
            'brand_name' => $brand->name,
            'timezone'   => $brand->settings?->timezone ?: 'UTC',
            'start_date' => $start,
            'end_date'   => $end,
            'count'      => count($entries),
            'data'       => $entries,
        ]);
    }

    /**
     * Get monthly calendar summary.
     */
    public function summary(Request $request, Brand $brand): JsonResponse
    {
        if ($brand->user_id !== $request->user()->id) {
            abort(403, 'Unauthorized access to brand calendar.');
        }

        $month = $request->query('month');
        $summary = $this->schedulingService->getCalendarSummary($brand, $month);

        return response()->json([
            'brand_id'   => $brand->id,
            'brand_name' => $brand->name,
            'summary'    => $summary,
        ]);
    }
}
