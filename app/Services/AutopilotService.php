<?php

namespace App\Services;

use App\Models\AutopilotActivityLog;
use App\Models\AutopilotRule;
use App\Models\AutopilotSetting;
use App\Models\Brand;
use App\Models\BrandContentDraft;
use App\Models\ScheduledPost;
use App\Notifications\DashboardEventNotification;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AutopilotService
{
    public function __construct(
        protected SchedulingIntelligenceService $intelligence,
        protected ContentSchedulingService $schedulingService
    ) {}

    /**
     * Get or initialize autopilot settings for a brand.
     */
    public function getOrCreateSettings(Brand $brand): AutopilotSetting
    {
        return AutopilotSetting::firstOrCreate(
            ['brand_id' => $brand->id],
            [
                'user_id'              => $brand->user_id,
                'is_enabled'           => false,
                'mode'                 => 'manual',
                'posts_per_week'       => 3,
                'min_gap_hours'        => 12,
                'preferred_days'       => ['monday', 'wednesday', 'friday'],
                'preferred_hours'      => ['10:00', '15:00'],
                'preferred_platforms'  => ['linkedin', 'instagram'],
                'preferred_categories' => ['educational', 'thought_leadership'],
                'requires_approval'    => true,
                'blackout_periods'     => [
                    ['start_hour' => 22, 'end_hour' => 6], // overnight
                ],
                'timezone'             => $brand->settings?->timezone ?: 'UTC',
            ]
        );
    }

    /**
     * Update autopilot settings.
     */
    public function updateSettings(Brand $brand, array $data): AutopilotSetting
    {
        $settings = $this->getOrCreateSettings($brand);

        $settings->update([
            'is_enabled'           => $data['is_enabled'] ?? $settings->is_enabled,
            'mode'                 => $data['mode'] ?? $settings->mode,
            'posts_per_week'       => $data['posts_per_week'] ?? $settings->posts_per_week,
            'min_gap_hours'        => $data['min_gap_hours'] ?? $settings->min_gap_hours,
            'preferred_days'       => $data['preferred_days'] ?? $settings->preferred_days,
            'preferred_hours'      => $data['preferred_hours'] ?? $settings->preferred_hours,
            'preferred_platforms'  => $data['preferred_platforms'] ?? $settings->preferred_platforms,
            'preferred_categories' => $data['preferred_categories'] ?? $settings->preferred_categories,
            'requires_approval'    => $data['requires_approval'] ?? $settings->requires_approval,
            'blackout_periods'     => $data['blackout_periods'] ?? $settings->blackout_periods,
            'timezone'             => $data['timezone'] ?? $settings->timezone,
        ]);

        $this->logActivity($brand, 'settings_updated', [
            'mode'       => $settings->mode,
            'is_enabled' => $settings->is_enabled,
        ], 'info');

        return $settings->fresh();
    }

    public function enable(Brand $brand): AutopilotSetting
    {
        $settings = $this->getOrCreateSettings($brand);
        $settings->update(['is_enabled' => true]);
        $this->logActivity($brand, 'autopilot_enabled', ['mode' => $settings->mode], 'success');
        return $settings;
    }

    public function pause(Brand $brand): AutopilotSetting
    {
        $settings = $this->getOrCreateSettings($brand);
        $settings->update(['is_enabled' => false]);
        $this->logActivity($brand, 'autopilot_paused', [], 'warning');
        return $settings;
    }

    public function resume(Brand $brand): AutopilotSetting
    {
        return $this->enable($brand);
    }

    public function disable(Brand $brand): AutopilotSetting
    {
        $settings = $this->getOrCreateSettings($brand);
        $settings->update(['is_enabled' => false, 'mode' => 'manual']);
        $this->logActivity($brand, 'autopilot_disabled', [], 'info');
        return $settings;
    }

    public function addRule(Brand $brand, array $data): AutopilotRule
    {
        $rule = AutopilotRule::create([
            'brand_id'   => $brand->id,
            'user_id'    => $brand->user_id,
            'name'       => $data['name'],
            'rule_type'  => $data['rule_type'],
            'conditions' => $data['conditions'] ?? [],
            'actions'    => $data['actions'] ?? [],
            'is_active'  => $data['is_active'] ?? true,
            'priority'   => $data['priority'] ?? 0,
        ]);

        $this->logActivity($brand, 'rule_created', ['rule_id' => $rule->id, 'rule_name' => $rule->name], 'info');

        return $rule;
    }

    public function updateRule(AutopilotRule $rule, array $data): AutopilotRule
    {
        $rule->update([
            'name'       => $data['name'] ?? $rule->name,
            'rule_type'  => $data['rule_type'] ?? $rule->rule_type,
            'conditions' => $data['conditions'] ?? $rule->conditions,
            'actions'    => $data['actions'] ?? $rule->actions,
            'is_active'  => $data['is_active'] ?? $rule->is_active,
            'priority'   => $data['priority'] ?? $rule->priority,
        ]);

        return $rule->fresh();
    }

    public function deleteRule(AutopilotRule $rule): void
    {
        $rule->delete();
    }

    /**
     * Run an autopilot execution cycle for a specific brand.
     */
    public function runAutopilotForBrand(Brand $brand): array
    {
        $settings = $this->getOrCreateSettings($brand);

        if (!$settings->is_enabled || $settings->mode === 'manual') {
            return [
                'status'  => 'skipped',
                'message' => 'Autopilot is disabled or in manual mode.',
            ];
        }

        // Fetch draft content ready for scheduling
        $pendingDrafts = BrandContentDraft::query()
            ->where('brand_id', $brand->id)
            ->whereIn('status', ['draft', 'ready'])
            ->latest()
            ->limit(10)
            ->get();

        if ($pendingDrafts->isEmpty()) {
            return [
                'status'  => 'no_pending_content',
                'message' => 'No content drafts available for scheduling.',
            ];
        }

        $existingPosts = ScheduledPost::query()
            ->where('brand_id', $brand->id)
            ->whereIn('status', ['scheduled', 'in_queue', 'awaiting_approval'])
            ->where('scheduled_at', '>=', now())
            ->get()
            ->map(fn ($p) => [
                'id'           => $p->id,
                'scheduled_at' => $p->scheduled_at->toIso8601String(),
                'status'       => $p->status,
                'platform'     => $p->platforms->first()?->platform,
            ])
            ->toArray();

        $rules = $brand->autopilotRules()->where('is_active', true)->get()->toArray();

        $plan = $this->intelligence->evaluateAutopilotPlan(
            brand: $brand,
            settings: $settings,
            rules: $rules,
            pendingItems: $pendingDrafts->map(fn ($d) => [
                'id'               => $d->id,
                'content_draft_id' => $d->id,
                'title'            => $d->title,
                'platform'         => $d->platform ?: 'linkedin',
                'is_approved'      => (bool) ($d->metadata['is_approved'] ?? false),
            ])->toArray(),
            existingScheduledPosts: $existingPosts,
        );

        $executedActions = [];

        // In automatic mode, persist ready actions
        if ($settings->mode === 'automatic') {
            foreach ($plan['actions'] ?? [] as $action) {
                if (($action['status'] ?? '') === 'ready_to_persist') {
                    $draft = $pendingDrafts->firstWhere('id', $action['content_draft_id'] ?? null);
                    if ($draft) {
                        try {
                            $scheduledPost = $this->schedulingService->schedulePost(
                                user: $brand->user,
                                brand: $brand,
                                data: [
                                    'content_draft_id'  => $draft->id,
                                    'title'             => $draft->title,
                                    'caption'           => $draft->caption,
                                    'body'              => $draft->body,
                                    'platform'          => $action['target_platform'] ?? ($draft->platform ?: 'linkedin'),
                                    'scheduled_at'      => $action['scheduled_at'] ?? now()->addDay()->format('Y-m-d 10:00:00'),
                                    'timezone'          => $action['timezone'] ?? $settings->timezone,
                                    'autopilot_managed' => true,
                                    'is_approved'       => true,
                                    'source'            => 'autopilot_agent',
                                ]
                            );

                            $this->logActivity($brand, 'auto_scheduled_post', [
                                'scheduled_post_id' => $scheduledPost->id,
                                'title'             => $scheduledPost->title,
                                'scheduled_at'      => $scheduledPost->scheduled_at->toIso8601String(),
                            ], 'success');

                            $executedActions[] = $action;
                        } catch (\Throwable $e) {
                            $this->logActivity($brand, 'auto_schedule_failed', [
                                'draft_id' => $draft->id,
                                'error'    => $e->getMessage(),
                            ], 'failed');
                        }
                    }
                }
            }
        } elseif ($settings->mode === 'ai_assisted') {
            $this->logActivity($brand, 'ai_assisted_proposals_generated', [
                'proposal_count' => count($plan['actions'] ?? []),
            ], 'info');
        }

        return [
            'status'           => 'completed',
            'mode'             => $settings->mode,
            'plan'             => $plan,
            'executed_actions' => $executedActions,
        ];
    }

    /**
     * Run autopilot cycle across all active brands.
     */
    public function runAutopilotForAllActiveBrands(): int
    {
        $activeSettings = AutopilotSetting::query()
            ->where('is_enabled', true)
            ->whereIn('mode', ['ai_assisted', 'automatic'])
            ->with('brand.user')
            ->get();

        $processed = 0;
        foreach ($activeSettings as $setting) {
            if ($setting->brand) {
                try {
                    $this->runAutopilotForBrand($setting->brand);
                    $processed++;
                } catch (\Throwable $e) {
                    Log::error("Autopilot execution failed for brand #{$setting->brand_id}: {$e->getMessage()}");
                }
            }
        }

        return $processed;
    }

    protected function logActivity(Brand $brand, string $action, array $details = [], string $status = 'info'): void
    {
        AutopilotActivityLog::create([
            'brand_id' => $brand->id,
            'user_id'  => $brand->user_id,
            'action'   => $action,
            'details'  => $details,
            'status'   => $status,
        ]);
    }
}
