<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SchedulingSettingsApiController extends Controller
{
    /**
     * Get scheduling settings for a brand.
     */
    public function show(Request $request, Brand $brand): JsonResponse
    {
        if ($brand->user_id !== $request->user()->id) {
            abort(403, 'Unauthorized access.');
        }

        $settings = $brand->settings;
        $autopilot = $brand->autopilotSetting;

        return response()->json([
            'brand_id'                   => $brand->id,
            'brand_name'                 => $brand->name,
            'timezone'                   => $settings?->timezone ?: 'UTC',
            'publishing_frequency'       => $settings?->publishing_frequency ?: 'weekly',
            'preferred_publishing_times' => $settings?->preferred_publishing_times ?: ['09:00', '14:00', '18:00'],
            'preferred_days'             => $autopilot?->preferred_days ?: ['monday', 'wednesday', 'friday'],
            'posts_per_week'             => $autopilot?->posts_per_week ?: 3,
            'min_gap_hours'              => $autopilot?->min_gap_hours ?: 12,
            'requires_approval'          => $autopilot?->requires_approval ?? true,
            'blackout_periods'           => $autopilot?->blackout_periods ?: [],
        ]);
    }

    /**
     * Update scheduling settings for a brand.
     */
    public function update(Request $request, Brand $brand): JsonResponse
    {
        if ($brand->user_id !== $request->user()->id) {
            abort(403, 'Unauthorized access.');
        }

        $validated = $request->validate([
            'timezone'                   => ['nullable', 'string', 'max:64'],
            'publishing_frequency'       => ['nullable', 'string', 'in:daily,weekly,biweekly,monthly'],
            'preferred_publishing_times' => ['nullable', 'array'],
            'preferred_days'             => ['nullable', 'array'],
            'posts_per_week'             => ['nullable', 'integer', 'min:1', 'max:21'],
            'min_gap_hours'              => ['nullable', 'integer', 'min:1', 'max:72'],
            'requires_approval'          => ['nullable', 'boolean'],
            'blackout_periods'           => ['nullable', 'array'],
        ]);

        $settings = $brand->settings ?: $brand->settings()->create();
        $settings->update(array_filter([
            'timezone'                   => $validated['timezone'] ?? null,
            'publishing_frequency'       => $validated['publishing_frequency'] ?? null,
            'preferred_publishing_times' => $validated['preferred_publishing_times'] ?? null,
        ], fn ($v) => !is_null($v)));

        $autopilot = $brand->autopilotSetting ?: $brand->autopilotSetting()->create([
            'user_id' => $brand->user_id,
        ]);

        $autopilot->update(array_filter([
            'timezone'          => $validated['timezone'] ?? null,
            'preferred_days'    => $validated['preferred_days'] ?? null,
            'posts_per_week'    => $validated['posts_per_week'] ?? null,
            'min_gap_hours'     => $validated['min_gap_hours'] ?? null,
            'requires_approval' => $validated['requires_approval'] ?? null,
            'blackout_periods'  => $validated['blackout_periods'] ?? null,
        ], fn ($v) => !is_null($v)));

        return response()->json([
            'message'  => 'Scheduling settings updated.',
            'settings' => $this->show($request, $brand)->getData(),
        ]);
    }
}
