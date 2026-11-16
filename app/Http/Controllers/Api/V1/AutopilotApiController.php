<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AutopilotActivityLog;
use App\Models\AutopilotRule;
use App\Models\Brand;
use App\Services\AutopilotService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AutopilotApiController extends Controller
{
    public function __construct(
        protected AutopilotService $autopilotService
    ) {}

    /**
     * Get autopilot configuration for a brand.
     */
    public function show(Request $request, Brand $brand): JsonResponse
    {
        $this->authorizeBrandOwner($request, $brand);

        $settings = $this->autopilotService->getOrCreateSettings($brand);
        $rules = $brand->autopilotRules;
        $recentLogs = $brand->autopilotActivityLogs()->take(10)->get();

        return response()->json([
            'settings'    => $settings,
            'rules'       => $rules,
            'recent_logs' => $recentLogs,
        ]);
    }

    /**
     * Update autopilot settings.
     */
    public function update(Request $request, Brand $brand): JsonResponse
    {
        $this->authorizeBrandOwner($request, $brand);

        $validated = $request->validate([
            'is_enabled'           => ['nullable', 'boolean'],
            'mode'                 => ['nullable', 'string', 'in:manual,ai_assisted,automatic'],
            'posts_per_week'       => ['nullable', 'integer', 'min:1', 'max:21'],
            'min_gap_hours'        => ['nullable', 'integer', 'min:1', 'max:72'],
            'preferred_days'       => ['nullable', 'array'],
            'preferred_hours'      => ['nullable', 'array'],
            'preferred_platforms'  => ['nullable', 'array'],
            'preferred_categories' => ['nullable', 'array'],
            'requires_approval'    => ['nullable', 'boolean'],
            'blackout_periods'     => ['nullable', 'array'],
            'timezone'             => ['nullable', 'string', 'max:64'],
        ]);

        $settings = $this->autopilotService->updateSettings($brand, $validated);

        return response()->json([
            'message'  => 'Autopilot settings updated.',
            'settings' => $settings,
        ]);
    }

    public function enable(Request $request, Brand $brand): JsonResponse
    {
        $this->authorizeBrandOwner($request, $brand);
        $settings = $this->autopilotService->enable($brand);
        return response()->json(['message' => 'Autopilot enabled.', 'settings' => $settings]);
    }

    public function pause(Request $request, Brand $brand): JsonResponse
    {
        $this->authorizeBrandOwner($request, $brand);
        $settings = $this->autopilotService->pause($brand);
        return response()->json(['message' => 'Autopilot paused.', 'settings' => $settings]);
    }

    public function resume(Request $request, Brand $brand): JsonResponse
    {
        $this->authorizeBrandOwner($request, $brand);
        $settings = $this->autopilotService->resume($brand);
        return response()->json(['message' => 'Autopilot resumed.', 'settings' => $settings]);
    }

    public function disable(Request $request, Brand $brand): JsonResponse
    {
        $this->authorizeBrandOwner($request, $brand);
        $settings = $this->autopilotService->disable($brand);
        return response()->json(['message' => 'Autopilot disabled.', 'settings' => $settings]);
    }

    /**
     * Manually trigger autopilot execution.
     */
    public function trigger(Request $request, Brand $brand): JsonResponse
    {
        $this->authorizeBrandOwner($request, $brand);
        $result = $this->autopilotService->runAutopilotForBrand($brand);
        return response()->json([
            'message' => 'Autopilot cycle executed.',
            'result'  => $result,
        ]);
    }

    /**
     * Activity log for brand.
     */
    public function activity(Request $request, Brand $brand): JsonResponse
    {
        $this->authorizeBrandOwner($request, $brand);

        $perPage = min(50, max(5, (int) $request->query('per_page', 20)));
        $logs = AutopilotActivityLog::query()
            ->where('brand_id', $brand->id)
            ->latest()
            ->paginate($perPage);

        return response()->json([
            'data' => $logs->items(),
            'meta' => [
                'current_page' => $logs->currentPage(),
                'last_page'    => $logs->lastPage(),
                'per_page'     => $logs->perPage(),
                'total'        => $logs->total(),
            ],
        ]);
    }

    public function storeRule(Request $request, Brand $brand): JsonResponse
    {
        $this->authorizeBrandOwner($request, $brand);

        $validated = $request->validate([
            'name'       => ['required', 'string', 'max:120'],
            'rule_type'  => ['required', 'string', 'max:64'],
            'conditions' => ['nullable', 'array'],
            'actions'    => ['nullable', 'array'],
            'is_active'  => ['nullable', 'boolean'],
            'priority'   => ['nullable', 'integer'],
        ]);

        $rule = $this->autopilotService->addRule($brand, $validated);

        return response()->json([
            'message' => 'Autopilot rule created.',
            'rule'    => $rule,
        ], 201);
    }

    public function updateRule(Request $request, Brand $brand, int $ruleId): JsonResponse
    {
        $this->authorizeBrandOwner($request, $brand);

        $rule = AutopilotRule::where('brand_id', $brand->id)->findOrFail($ruleId);

        $validated = $request->validate([
            'name'       => ['nullable', 'string', 'max:120'],
            'rule_type'  => ['nullable', 'string', 'max:64'],
            'conditions' => ['nullable', 'array'],
            'actions'    => ['nullable', 'array'],
            'is_active'  => ['nullable', 'boolean'],
            'priority'   => ['nullable', 'integer'],
        ]);

        $updated = $this->autopilotService->updateRule($rule, $validated);

        return response()->json([
            'message' => 'Autopilot rule updated.',
            'rule'    => $updated,
        ]);
    }

    public function destroyRule(Request $request, Brand $brand, int $ruleId): JsonResponse
    {
        $this->authorizeBrandOwner($request, $brand);

        $rule = AutopilotRule::where('brand_id', $brand->id)->findOrFail($ruleId);
        $this->autopilotService->deleteRule($rule);

        return response()->json(['message' => 'Autopilot rule deleted.']);
    }

    protected function authorizeBrandOwner(Request $request, Brand $brand): void
    {
        if ($brand->user_id !== $request->user()->id) {
            abort(403, 'Unauthorized access to brand autopilot.');
        }
    }
}
