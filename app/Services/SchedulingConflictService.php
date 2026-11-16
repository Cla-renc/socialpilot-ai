<?php

namespace App\Services;

use App\Models\Brand;
use App\Models\ScheduledPost;
use Carbon\Carbon;

class SchedulingConflictService
{
    public function __construct(
        protected SchedulingIntelligenceService $intelligence
    ) {}

    /**
     * Check if a proposed post schedule conflicts with existing posts or rules.
     */
    public function check(Brand $brand, array $proposedSlot, ?int $ignorePostId = null): array
    {
        $existingPosts = ScheduledPost::query()
            ->where('brand_id', $brand->id)
            ->whereIn('status', ['scheduled', 'in_queue', 'awaiting_approval'])
            ->where('scheduled_at', '>=', now())
            ->when($ignorePostId, fn ($q) => $q->where('id', '!=', $ignorePostId))
            ->with('platforms')
            ->get()
            ->map(fn (ScheduledPost $p) => [
                'id'                => $p->id,
                'scheduled_at'      => $p->scheduled_at->toIso8601String(),
                'platform'          => $p->platforms->first()?->platform,
                'social_account_id' => $p->platforms->first()?->social_account_id,
                'status'            => $p->status,
            ])
            ->toArray();

        $brandSettings = [
            'timezone'         => $brand->settings?->timezone ?: 'UTC',
            'blackout_periods' => $brand->autopilotSetting?->blackout_periods ?: [],
            'min_gap_hours'    => $brand->autopilotSetting?->min_gap_hours ?: 12,
        ];

        return $this->intelligence->checkConflicts(
            $proposedSlot,
            $existingPosts,
            $brandSettings,
            minGapMinutes: 60
        );
    }

    /**
     * Propose the next conflict-free available slot.
     */
    public function findNextAvailableSlot(Brand $brand, ?string $platform = null, ?Carbon $after = null): Carbon
    {
        $start = $after ? $after->copy() : now()->addHours(2);
        $minGapHours = $brand->autopilotSetting?->min_gap_hours ?: 12;

        $lastScheduled = ScheduledPost::query()
            ->where('brand_id', $brand->id)
            ->whereIn('status', ['scheduled', 'in_queue'])
            ->where('scheduled_at', '>=', $start)
            ->latest('scheduled_at')
            ->first();

        if ($lastScheduled && $lastScheduled->scheduled_at->isAfter($start)) {
            $candidate = $lastScheduled->scheduled_at->copy()->addHours($minGapHours);
        } else {
            $candidate = $start->copy()->addHours(2);
        }

        // Avoid late night hours (between 23:00 and 06:00 in brand timezone)
        $brandTz = $brand->settings?->timezone ?: 'UTC';
        $localCandidate = $candidate->copy()->setTimezone($brandTz);
        if ($localCandidate->hour >= 23 || $localCandidate->hour < 6) {
            $localCandidate->setTime(9, 0, 0);
            if ($localCandidate->isPast()) {
                $localCandidate->addDay();
            }
            $candidate = $localCandidate->setTimezone('UTC');
        }

        return $candidate;
    }
}
