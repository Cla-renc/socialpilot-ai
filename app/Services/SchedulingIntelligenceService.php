<?php

namespace App\Services;

use App\Models\Brand;
use App\Models\AutopilotSetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SchedulingIntelligenceService
{
    protected string $engineUrl;
    protected string $internalToken;

    public function __construct()
    {
        $this->engineUrl = rtrim(config('services.trend_engine.url', env('TREND_ENGINE_URL', 'http://127.0.0.1:8080')), '/');
        $this->internalToken = (string) (config('services.trend_engine.token', env('TREND_ENGINE_INTERNAL_TOKEN', '')));
    }

    /**
     * Get tailored scheduling strategy from AI engine.
     */
    public function getStrategy(Brand $brand): array
    {
        $brandPayload = $this->buildBrandPayload($brand);

        try {
            $response = Http::withToken($this->internalToken)
                ->timeout(10)
                ->post("{$this->engineUrl}/internal/v1/scheduling/recommend", [
                    'brand' => $brandPayload,
                ]);

            if ($response->successful()) {
                return $response->json();
            }
        } catch (\Throwable $e) {
            Log::warning("Scheduling AI Engine unavailable: {$e->getMessage()}. Using fallback heuristic strategy.");
        }

        return $this->fallbackStrategy($brand);
    }

    /**
     * Get Best Time to Post recommendations.
     */
    public function getBestTimes(Brand $brand, ?string $platform = null, ?string $timezone = null, ?array $historicalRecords = null): array
    {
        $brandPayload = $this->buildBrandPayload($brand);
        $tz = $timezone ?: ($brand->settings?->timezone ?: 'UTC');

        try {
            $response = Http::withToken($this->internalToken)
                ->timeout(10)
                ->post("{$this->engineUrl}/internal/v1/scheduling/best-times", [
                    'brand'              => $brandPayload,
                    'platform'           => $platform,
                    'timezone'           => $tz,
                    'historical_records' => $historicalRecords,
                ]);

            if ($response->successful()) {
                return $response->json()['recommendations'] ?? [];
            }
        } catch (\Throwable $e) {
            Log::warning("Best Times AI Engine unavailable: {$e->getMessage()}. Using fallback heuristic recommendations.");
        }

        return $this->fallbackBestTimes($brand, $platform, $tz);
    }

    /**
     * Distribute content drafts over calendar slots.
     */
    public function planDistribution(Brand $brand, array $drafts, ?string $startDate = null, int $daysSpan = 14, int $minGapHours = 12): array
    {
        $brandPayload = $this->buildBrandPayload($brand);
        $tz = $brand->settings?->timezone ?: 'UTC';

        try {
            $response = Http::withToken($this->internalToken)
                ->timeout(10)
                ->post("{$this->engineUrl}/internal/v1/scheduling/distribute", [
                    'brand'         => $brandPayload,
                    'drafts'        => $drafts,
                    'start_date'    => $startDate,
                    'days_span'     => $daysSpan,
                    'timezone'      => $tz,
                    'min_gap_hours' => $minGapHours,
                ]);

            if ($response->successful()) {
                return $response->json();
            }
        } catch (\Throwable $e) {
            Log::warning("Distribution AI Engine unavailable: {$e->getMessage()}. Using fallback distributor.");
        }

        return $this->fallbackDistribution($drafts, $tz, $minGapHours);
    }

    /**
     * Evaluate autopilot plan for brand.
     */
    public function evaluateAutopilotPlan(
        Brand $brand,
        AutopilotSetting $settings,
        array $rules,
        array $pendingItems,
        array $existingScheduledPosts,
        ?string $targetWeekStart = null
    ): array {
        $brandPayload = $this->buildBrandPayload($brand);

        try {
            $response = Http::withToken($this->internalToken)
                ->timeout(10)
                ->post("{$this->engineUrl}/internal/v1/scheduling/autopilot/plan", [
                    'brand'                    => $brandPayload,
                    'autopilot_settings'       => $settings->toArray(),
                    'rules'                    => $rules,
                    'pending_items'            => $pendingItems,
                    'existing_scheduled_posts' => $existingScheduledPosts,
                    'target_week_start'        => $targetWeekStart,
                ]);

            if ($response->successful()) {
                return $response->json();
            }
        } catch (\Throwable $e) {
            Log::warning("Autopilot AI Engine unavailable: {$e->getMessage()}. Using fallback plan evaluator.");
        }

        return $this->fallbackAutopilotPlan($settings, $pendingItems);
    }

    /**
     * Check conflicts between proposed post and existing posts.
     */
    public function checkConflicts(array $proposedSlot, array $existingPosts, ?array $brandSettings = null, int $minGapMinutes = 60): array
    {
        try {
            $response = Http::withToken($this->internalToken)
                ->timeout(5)
                ->post("{$this->engineUrl}/internal/v1/scheduling/conflicts", [
                    'proposed_slot'   => $proposedSlot,
                    'existing_posts'  => $existingPosts,
                    'brand_settings'  => $brandSettings,
                    'min_gap_minutes' => $minGapMinutes,
                ]);

            if ($response->successful()) {
                return $response->json();
            }
        } catch (\Throwable $e) {
            Log::warning("Conflict detector engine call failed: {$e->getMessage()}. Using fallback conflict checker.");
        }

        return $this->fallbackConflictCheck($proposedSlot, $existingPosts, $minGapMinutes);
    }

    // -------------------------------------------------------------------------
    // Helpers & Fallbacks
    // -------------------------------------------------------------------------

    protected function buildBrandPayload(Brand $brand): array
    {
        $settings = $brand->settings;
        $connectedPlatforms = $brand->socialAccounts()->pluck('provider')->unique()->values()->all();

        return [
            'id'                  => $brand->id,
            'name'                => $brand->name,
            'industry'            => $brand->industry ?: $brand->category,
            'category'            => $brand->category,
            'niche'               => $brand->niche,
            'audience'            => $brand->audience,
            'tone'                => $brand->tone,
            'timezone'            => $settings?->timezone ?: 'UTC',
            'connected_platforms' => $connectedPlatforms ?: ['linkedin', 'instagram'],
            'settings'            => [
                'publishing_frequency'       => $settings?->publishing_frequency ?: 'weekly',
                'preferred_publishing_times' => $settings?->preferred_publishing_times,
                'content_formats'            => $settings?->content_formats,
                'marketing_objectives'       => $settings?->marketing_objectives,
                'timezone'                   => $settings?->timezone ?: 'UTC',
            ],
        ];
    }

    protected function fallbackStrategy(Brand $brand): array
    {
        $tz = $brand->settings?->timezone ?: 'UTC';
        $industry = strtolower($brand->industry ?: $brand->category ?: '');
        $isDiscipline = str_contains($industry, 'discipline') || str_contains($industry, 'fitness');

        return [
            'brand_name'                 => $brand->name,
            'archetype'                  => $isDiscipline ? 'discipline_coach' : 'general',
            'timezone'                   => $tz,
            'recommended_posts_per_week' => $isDiscipline ? 5 : 3,
            'min_gap_hours'              => $isDiscipline ? 18 : 24,
            'recommended_publishing_days'=> ['monday', 'wednesday', 'friday'],
            'category_distribution'      => [
                ['category' => 'educational', 'percentage' => 40, 'description' => 'Educational content'],
                ['category' => 'authority', 'percentage' => 30, 'description' => 'Thought leadership'],
                ['category' => 'engagement', 'percentage' => 20, 'description' => 'Community discussion'],
                ['category' => 'promotional', 'percentage' => 10, 'description' => 'Offers and updates'],
            ],
            'platform_priorities'        => [
                ['platform' => 'linkedin', 'priority_score' => 90],
                ['platform' => 'instagram', 'priority_score' => 80],
            ],
            'best_times'                 => $this->fallbackBestTimes($brand, null, $tz),
            'rationale'                  => "Heuristic distribution strategy generated for {$brand->name} based on brand category and {$tz} timezone.",
        ];
    }

    public function fallbackBestTimes(Brand $brand, ?string $platform, string $tz): array
    {
        $platforms = $platform ? [strtolower($platform)] : ['facebook', 'instagram', 'tiktok', 'youtube', 'linkedin'];
        $recs = [];

        $slots = [
            'facebook'  => ['day' => 'wednesday', 'time' => '09:00', 'reason' => 'Mid-morning weekday active window.'],
            'instagram' => ['day' => 'friday', 'time' => '18:30', 'reason' => 'Peak evening weekend warmup.'],
            'tiktok'    => ['day' => 'thursday', 'time' => '19:30', 'reason' => 'High algorithmic discovery and evening browsing.'],
            'youtube'   => ['day' => 'thursday', 'time' => '15:00', 'reason' => 'Afternoon upload window allowing prime-time indexing.'],
            'linkedin'  => ['day' => 'tuesday', 'time' => '08:30', 'reason' => 'Morning commute and professional briefing window.'],
        ];

        foreach ($platforms as $p) {
            $slot = $slots[$p] ?? ['day' => 'wednesday', 'time' => '10:00', 'reason' => 'Standard engagement slot.'];
            $recs[] = [
                'platform'       => $p,
                'day_of_week'    => $slot['day'],
                'suggested_time' => $slot['time'],
                'timezone'       => $tz,
                'reason'         => "{$slot['reason']} Preliminary recommendation based on industry heuristics.",
                'confidence'     => 0.70,
                'evidence_type'  => 'heuristic_preliminary',
                'data_basis'     => 'Configurable platform heuristics. No historical engagement data recorded yet.',
                'is_preliminary' => true,
            ];
        }

        return $recs;
    }

    protected function fallbackDistribution(array $drafts, string $tz, int $minGapHours): array
    {
        $distributed = [];
        $cursor = now()->addHours(3);

        foreach ($drafts as $idx => $d) {
            $cursor = $cursor->copy()->addHours($minGapHours);
            $distributed[] = [
                'draft_id'      => $d['id'] ?? null,
                'title'         => $d['title'] ?? 'Draft',
                'platform'      => $d['platform'] ?? 'linkedin',
                'scheduled_at'  => $cursor->format('Y-m-d H:i:s'),
                'timezone'      => $tz,
                'evidence_type' => 'heuristic_preliminary',
                'reason'        => 'Spaced according to minimum gap heuristic.',
            ];
        }

        return [
            'total_items'   => count($distributed),
            'start_date'    => now()->toIso8601String(),
            'timezone'      => $tz,
            'min_gap_hours' => $minGapHours,
            'distribution'  => $distributed,
        ];
    }

    protected function fallbackAutopilotPlan(AutopilotSetting $settings, array $pendingItems): array
    {
        $mode = $settings->mode ?: 'manual';
        if ($mode === 'manual' || !$settings->is_enabled) {
            return [
                'status'          => $settings->is_enabled ? 'manual_mode' : 'disabled',
                'mode'            => $mode,
                'actions'         => [],
                'scheduled_count' => 0,
            ];
        }

        $actions = [];
        $cursor = now()->addDays(1);

        foreach ($pendingItems as $item) {
            $approved = (bool) ($item['is_approved'] ?? false);
            if ($settings->requires_approval && !$approved) {
                $actions[] = [
                    'item_id'          => $item['id'] ?? null,
                    'content_draft_id' => $item['content_draft_id'] ?? $item['id'] ?? null,
                    'action'           => 'queued_awaiting_approval',
                    'reason'           => 'Brand requires explicit approval before scheduling.',
                    'status'           => 'awaiting_approval',
                ];
                continue;
            }

            $actions[] = [
                'item_id'          => $item['id'] ?? null,
                'content_draft_id' => $item['content_draft_id'] ?? $item['id'] ?? null,
                'action'           => $mode === 'automatic' ? 'auto_schedule' : 'recommend_slot',
                'target_platform'  => $item['platform'] ?? 'linkedin',
                'scheduled_at'     => $cursor->format('Y-m-d 10:00:00'),
                'timezone'         => $settings->timezone ?: 'UTC',
                'status'           => $mode === 'automatic' ? 'ready_to_persist' : 'proposed_pending_user_confirmation',
                'reason'           => 'Fallback autopilot slot assignment.',
            ];
            $cursor->addHours($settings->min_gap_hours ?: 12);
        }

        return [
            'status'               => 'success',
            'mode'                 => $mode,
            'quota_posts_per_week' => $settings->posts_per_week ?: 3,
            'quota_remaining'      => max(0, ($settings->posts_per_week ?: 3) - count($actions)),
            'actions'              => $actions,
            'activity_logs'        => [],
            'scheduled_count'      => count($actions),
        ];
    }

    protected function fallbackConflictCheck(array $proposedSlot, array $existingPosts, int $minGapMinutes): array
    {
        $conflicts = [];
        $proposedTime = isset($proposedSlot['scheduled_at']) ? strtotime($proposedSlot['scheduled_at']) : null;

        if (!$proposedTime) {
            return ['has_conflicts' => true, 'conflicts' => [['code' => 'MISSING_TIMESTAMP', 'severity' => 'error', 'message' => 'Timestamp missing.']]];
        }

        if ($proposedTime < time()) {
            $conflicts[] = [
                'code'                 => 'PAST_DATE_ERROR',
                'severity'             => 'error',
                'message'              => 'Cannot schedule a post for a time in the past.',
                'suggested_resolution' => 'Select a future date and time.',
            ];
        }

        $proposedAccount = $proposedSlot['social_account_id'] ?? null;
        foreach ($existingPosts as $ep) {
            $epTime = isset($ep['scheduled_at']) ? strtotime($ep['scheduled_at']) : null;
            if (!$epTime) continue;

            $diff = abs($proposedTime - $epTime);
            if ($proposedAccount && isset($ep['social_account_id']) && (string) $proposedAccount === (string) $ep['social_account_id']) {
                if ($diff < ($minGapMinutes * 60)) {
                    $conflicts[] = [
                        'code'                 => 'ACCOUNT_COLLISION',
                        'severity'             => $diff < 300 ? 'error' : 'warning',
                        'message'              => 'Account already has a scheduled post within this time window.',
                        'suggested_resolution' => "Maintain at least {$minGapMinutes} minutes gap.",
                    ];
                }
            }
        }

        return [
            'has_conflicts' => count(array_filter($conflicts, fn ($c) => ($c['severity'] ?? '') === 'error')) > 0,
            'conflicts'     => $conflicts,
        ];
    }
}
