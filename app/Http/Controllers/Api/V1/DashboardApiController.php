<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Services\DashboardDataService;
use App\Services\SocialProviderCatalog;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DashboardApiController extends Controller
{
    public function __construct(private readonly DashboardDataService $dashboard) {}

    public function overview(Request $request)
    {
        $brand = $this->selectedBrand($request);

        return response()->json(['data' => $this->dashboard->overview($request->user(), $brand)]);
    }

    public function brands(Request $request)
    {
        return response()->json(['data' => $this->dashboard->brands($request->user())]);
    }

    public function contentActivity(Request $request)
    {
        $validated = $request->validate([
            'brand_id' => ['nullable', 'integer'],
            'per_page' => ['sometimes', 'integer', 'between:1,50'],
            'status' => ['nullable', Rule::in(['draft', 'queued', 'scheduled', 'published', 'failed', 'pending_approval'])],
        ]);
        $brand = $this->dashboard->resolveBrand($request->user(), $validated['brand_id'] ?? null);
        $items = collect($this->dashboard->contentActivity(
            $request->user(),
            $brand,
            $validated['per_page'] ?? 50,
        ));

        if (isset($validated['status'])) {
            $items = $items->where('status', $validated['status'])->values();
        }

        return response()->json([
            'data' => $items,
            'meta' => ['count' => $items->count()],
        ]);
    }

    public function publishingCalendar(Request $request)
    {
        $validated = $request->validate([
            'brand_id' => ['nullable', 'integer'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'per_page' => ['sometimes', 'integer', 'between:1,50'],
        ]);
        $brand = $this->dashboard->resolveBrand($request->user(), $validated['brand_id'] ?? null);
        $from = isset($validated['from']) ? now()->parse($validated['from']) : now();
        $to = isset($validated['to']) ? now()->parse($validated['to'])->endOfDay() : now()->addDays(30)->endOfDay();
        abort_if($to->lt($from), 422, 'The calendar end must not precede its start.');

        $items = $this->dashboard->publishingCalendar(
            $request->user(),
            $brand,
            $from,
            $to,
            $validated['per_page'] ?? 50,
        );

        return response()->json(['data' => $items, 'meta' => ['count' => count($items)]]);
    }

    public function socialAccounts(Request $request)
    {
        $brand = $this->selectedBrand($request);

        return response()->json([
            'data' => $this->dashboard->socialAccounts($request->user(), $brand),
            'providers' => app(SocialProviderCatalog::class)->statuses(),
        ]);
    }

    public function agentActivity(Request $request)
    {
        $brand = $this->selectedBrand($request);

        return response()->json(['data' => $this->dashboard->agentActivity($request->user(), $brand)]);
    }

    private function selectedBrand(Request $request): ?Brand
    {
        $validated = $request->validate(['brand_id' => ['nullable', 'integer']]);

        return $this->dashboard->resolveBrand($request->user(), $validated['brand_id'] ?? null);
    }
}
