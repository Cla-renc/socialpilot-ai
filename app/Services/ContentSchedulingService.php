<?php

namespace App\Services;

use App\Models\Brand;
use App\Models\BrandContentDraft;
use App\Models\BrandPost;
use App\Models\MediaAsset;
use App\Models\ScheduledPost;
use App\Models\ScheduledPostPlatform;
use App\Models\ScheduledPostMedia;
use App\Models\SocialAccount;
use App\Models\User;
use App\Notifications\DashboardEventNotification;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ContentSchedulingService
{
    public function __construct(
        protected SchedulingConflictService $conflictService
    ) {}

    /**
     * Schedule a post (with single or multi-platform adaptations and attached media).
     */
    public function schedulePost(User $user, Brand $brand, array $data): ScheduledPost
    {
        // 1. Authorize brand
        if ($brand->user_id !== $user->id) {
            throw ValidationException::withMessages(['brand' => 'You do not have access to schedule for this brand.']);
        }

        // 2. Validate content draft if provided
        $contentDraft = null;
        if (!empty($data['content_draft_id'])) {
            $contentDraft = BrandContentDraft::query()
                ->where('id', $data['content_draft_id'])
                ->where('brand_id', $brand->id)
                ->firstOrFail();
        }

        // 3. Timezone & scheduled timestamp
        $tz = $data['timezone'] ?? ($brand->settings?->timezone ?: 'UTC');
        $rawScheduledAt = $data['scheduled_at'] ?? null;

        if (!$rawScheduledAt) {
            throw ValidationException::withMessages(['scheduled_at' => 'Scheduled date and time is required.']);
        }

        try {
            // Parse local scheduled time in specified timezone, then convert to UTC
            $scheduledAtUtc = Carbon::parse($rawScheduledAt, $tz)->setTimezone('UTC');
        } catch (\Throwable $e) {
            throw ValidationException::withMessages(['scheduled_at' => "Invalid date/time format: {$e->getMessage()}"]);
        }

        // Past date check
        if ($scheduledAtUtc->isPast() && empty($data['allow_past'])) {
            throw ValidationException::withMessages(['scheduled_at' => 'Cannot schedule content for a date or time in the past.']);
        }

        // 4. Validate platforms and social accounts
        $platformVersions = $data['platforms'] ?? [];
        if (empty($platformVersions) && !empty($data['platform'])) {
            // Support single platform format
            $platformVersions = [[
                'platform'          => $data['platform'],
                'social_account_id' => $data['social_account_id'] ?? null,
                'custom_caption'    => $data['caption'] ?? null,
                'custom_title'      => $data['title'] ?? null,
            ]];
        }

        if (empty($platformVersions)) {
            throw ValidationException::withMessages(['platforms' => 'At least one target social platform is required.']);
        }

        foreach ($platformVersions as $pv) {
            if (!empty($pv['social_account_id'])) {
                $account = SocialAccount::query()
                    ->where('id', $pv['social_account_id'])
                    ->where('user_id', $user->id)
                    ->first();
                if (!$account) {
                    throw ValidationException::withMessages(['social_account_id' => 'Connected social account is invalid or not owned by user.']);
                }
            }
        }

        // 5. Media attachments & validation
        $mediaAssetIds = $data['media_asset_ids'] ?? [];
        $mediaAssets = collect();
        if (!empty($mediaAssetIds)) {
            $mediaAssets = MediaAsset::query()
                ->whereIn('id', $mediaAssetIds)
                ->where('brand_id', $brand->id)
                ->get();

            if ($mediaAssets->count() !== count($mediaAssetIds)) {
                throw ValidationException::withMessages(['media_asset_ids' => 'One or more media assets could not be found or belong to another brand.']);
            }

            // Media compatibility validation
            foreach ($platformVersions as $pv) {
                $plat = strtolower($pv['platform'] ?? '');
                if (in_array($plat, ['tiktok', 'youtube'])) {
                    $hasVideo = $mediaAssets->contains(fn ($m) => $m->asset_type === 'video');
                    if (!$hasVideo && empty($data['allow_non_video'])) {
                        throw ValidationException::withMessages(['media_asset_ids' => "Target platform '{$plat}' requires a video media asset."]);
                    }
                }
            }
        }

        // 6. Check conflicts
        $firstAccount = $platformVersions[0]['social_account_id'] ?? null;
        $conflictReport = $this->conflictService->check($brand, [
            'scheduled_at'      => $scheduledAtUtc->toIso8601String(),
            'platform'          => $platformVersions[0]['platform'] ?? null,
            'social_account_id' => $firstAccount,
        ]);

        if ($conflictReport['has_conflicts'] && empty($data['force'])) {
            $firstErr = collect($conflictReport['conflicts'])->firstWhere('severity', 'error');
            throw ValidationException::withMessages([
                'scheduled_at' => $firstErr['message'] ?? 'Scheduling conflict detected.',
            ]);
        }

        // 7. Approval requirement
        $requiresApproval = (bool) ($data['requires_approval'] ?? ($brand->autopilotSetting?->requires_approval ?? false));
        $isApproved = (bool) ($data['is_approved'] ?? false);
        $status = $requiresApproval && !$isApproved ? 'awaiting_approval' : 'scheduled';

        return DB::transaction(function () use (
            $user, $brand, $contentDraft, $data, $scheduledAtUtc, $tz, $status,
            $requiresApproval, $isApproved, $platformVersions, $mediaAssets, $conflictReport
        ) {
            $title = $data['title'] ?? ($contentDraft?->title ?: 'Scheduled Post');
            $caption = $data['caption'] ?? ($contentDraft?->caption ?: null);
            $body = $data['body'] ?? ($contentDraft?->body ?: null);
            $hashtags = $data['hashtags'] ?? ($contentDraft?->hashtags ?: []);

            $post = ScheduledPost::create([
                'user_id'             => $user->id,
                'brand_id'            => $brand->id,
                'content_draft_id'    => $contentDraft?->id,
                'title'               => $title,
                'caption'             => $caption,
                'body'                => $body,
                'hashtags'            => $hashtags,
                'scheduled_at'        => $scheduledAtUtc,
                'timezone'            => $tz,
                'status'              => $status,
                'requires_approval'   => $requiresApproval,
                'is_approved'         => $isApproved,
                'approved_by'         => $isApproved ? $user->id : null,
                'approved_at'         => $isApproved ? now() : null,
                'autopilot_managed'   => (bool) ($data['autopilot_managed'] ?? false),
                'autopilot_rule_id'   => $data['autopilot_rule_id'] ?? null,
                'metadata'            => [
                    'source'            => $data['source'] ?? 'web_studio',
                    'conflict_warnings' => $conflictReport['conflicts'] ?? [],
                ],
            ]);

            // Save platform variations
            foreach ($platformVersions as $pv) {
                ScheduledPostPlatform::create([
                    'scheduled_post_id'         => $post->id,
                    'social_account_id'         => $pv['social_account_id'] ?? null,
                    'platform'                  => strtolower($pv['platform']),
                    'custom_title'              => $pv['custom_title'] ?? $title,
                    'custom_caption'            => $pv['custom_caption'] ?? $caption,
                    'custom_body'               => $pv['custom_body'] ?? $body,
                    'custom_hashtags'           => $pv['custom_hashtags'] ?? $hashtags,
                    'scheduled_at'              => isset($pv['scheduled_at']) ? Carbon::parse($pv['scheduled_at'], $tz)->setTimezone('UTC') : $scheduledAtUtc,
                    'status'                    => $status,
                    'platform_specific_options' => $pv['platform_specific_options'] ?? null,
                ]);
            }

            // Attach media
            foreach ($mediaAssets as $idx => $asset) {
                ScheduledPostMedia::create([
                    'scheduled_post_id' => $post->id,
                    'media_asset_id'    => $asset->id,
                    'order'             => $idx,
                    'platform_variant'  => $asset->platform,
                ]);
            }

            // Update content draft
            if ($contentDraft) {
                $contentDraft->update([
                    'scheduled_for' => $scheduledAtUtc,
                    'status'        => 'scheduled',
                ]);
            }

            // Sync with BrandPost for backward compatibility with Phase 4 calendar
            BrandPost::create([
                'user_id'          => $user->id,
                'brand_id'         => $brand->id,
                'content_draft_id' => $contentDraft?->id,
                'provider'         => 'internal',
                'platform'         => strtolower($platformVersions[0]['platform'] ?? 'linkedin'),
                'status'           => 'scheduled',
                'scheduled_for'    => $scheduledAtUtc,
                'content'          => $caption ?: ($body ?: $title),
                'metadata'         => [
                    'scheduled_post_id' => $post->id,
                    'source'            => 'phase8_scheduler',
                ],
            ]);

            // Dispatch notification
            $formattedTime = $scheduledAtUtc->copy()->setTimezone($tz)->format('M d, Y \a\t g:i A');
            $user->notify(new DashboardEventNotification(
                title: "Post Scheduled",
                description: "Your post '{$title}' has been scheduled for {$formattedTime} ({$tz}).",
                actionUrl: "/brands/{$brand->id}/scheduling/{$post->id}",
                brandId: $brand->id,
            ));

            return $post->load(['platforms.socialAccount', 'mediaAssets', 'contentDraft']);
        });
    }

    /**
     * Reschedule a post.
     */
    public function reschedulePost(ScheduledPost $post, string $newScheduledAt, ?string $timezone = null): ScheduledPost
    {
        $tz = $timezone ?: $post->timezone;
        $newUtc = Carbon::parse($newScheduledAt, $tz)->setTimezone('UTC');

        if ($newUtc->isPast()) {
            throw ValidationException::withMessages(['scheduled_at' => 'Cannot reschedule to a date or time in the past.']);
        }

        // Conflict check
        $firstAccount = $post->platforms->first()?->social_account_id;
        $conflictReport = $this->conflictService->check($post->brand, [
            'scheduled_at'      => $newUtc->toIso8601String(),
            'platform'          => $post->platforms->first()?->platform,
            'social_account_id' => $firstAccount,
        ], ignorePostId: $post->id);

        if ($conflictReport['has_conflicts']) {
            $firstErr = collect($conflictReport['conflicts'])->firstWhere('severity', 'error');
            throw ValidationException::withMessages([
                'scheduled_at' => $firstErr['message'] ?? 'Rescheduling conflict detected.',
            ]);
        }

        return DB::transaction(function () use ($post, $newUtc, $tz) {
            $post->update([
                'scheduled_at' => $newUtc,
                'timezone'     => $tz,
                'status'       => 'scheduled',
            ]);

            $post->platforms()->update([
                'scheduled_at' => $newUtc,
                'status'       => 'scheduled',
            ]);

            if ($post->contentDraft) {
                $post->contentDraft->update(['scheduled_for' => $newUtc]);
            }

            // Update legacy BrandPost sync
            BrandPost::query()
                ->where('brand_id', $post->brand_id)
                ->whereJsonContains('metadata->scheduled_post_id', $post->id)
                ->update(['scheduled_for' => $newUtc, 'status' => 'scheduled']);

            return $post->fresh(['platforms.socialAccount', 'mediaAssets', 'contentDraft']);
        });
    }

    /**
     * Cancel a scheduled post.
     */
    public function cancelScheduledPost(ScheduledPost $post, ?string $reason = null): ScheduledPost
    {
        return DB::transaction(function () use ($post, $reason) {
            $post->update([
                'status'              => 'cancelled',
                'cancellation_reason' => $reason ?: 'Cancelled by user.',
            ]);

            $post->platforms()->update(['status' => 'cancelled']);

            if ($post->contentDraft) {
                $post->contentDraft->update(['status' => 'draft']);
            }

            BrandPost::query()
                ->where('brand_id', $post->brand_id)
                ->whereJsonContains('metadata->scheduled_post_id', $post->id)
                ->update(['status' => 'cancelled']);

            return $post->fresh();
        });
    }

    /**
     * Approve a post awaiting approval.
     */
    public function approveScheduledPost(ScheduledPost $post, User $approver): ScheduledPost
    {
        return DB::transaction(function () use ($post, $approver) {
            $post->update([
                'is_approved' => true,
                'approved_by' => $approver->id,
                'approved_at' => now(),
                'status'      => 'scheduled',
            ]);

            $post->platforms()->update(['status' => 'scheduled']);

            return $post->fresh();
        });
    }

    /**
     * Retrieve calendar entries within date range with optional filtering.
     */
    public function getCalendar(Brand $brand, string $start, string $end, array $filters = []): array
    {
        $startUtc = Carbon::parse($start)->startOfDay()->setTimezone('UTC');
        $endUtc = Carbon::parse($end)->endOfDay()->setTimezone('UTC');

        $query = ScheduledPost::query()
            ->where('brand_id', $brand->id)
            ->whereBetween('scheduled_at', [$startUtc, $endUtc])
            ->with(['platforms.socialAccount', 'mediaAssets', 'contentDraft']);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['platform'])) {
            $query->whereHas('platforms', fn ($q) => $q->where('platform', strtolower($filters['platform'])));
        }

        if (!empty($filters['social_account_id'])) {
            $query->whereHas('platforms', fn ($q) => $q->where('social_account_id', $filters['social_account_id']));
        }

        $posts = $query->orderBy('scheduled_at')->get();

        $brandTz = $brand->settings?->timezone ?: 'UTC';

        return $posts->map(function (ScheduledPost $p) use ($brandTz) {
            $localTime = $p->scheduled_at->copy()->setTimezone($brandTz);
            $firstPlatform = $p->platforms->first();
            $firstMedia = $p->mediaAssets->first();

            return [
                'id'                 => $p->id,
                'title'              => $p->title,
                'caption'            => $p->caption,
                'body'               => $p->body,
                'status'             => $p->status,
                'scheduled_at'       => $p->scheduled_at->toIso8601String(),
                'scheduled_local'    => $localTime->format('Y-m-d H:i:s'),
                'formatted_time'     => $localTime->format('g:i A'),
                'formatted_date'     => $localTime->format('M d, Y'),
                'timezone'           => $brandTz,
                'platform'           => $firstPlatform?->platform ?? 'multi_platform',
                'platforms'          => $p->platforms->map(fn ($pl) => [
                    'platform'       => $pl->platform,
                    'account_name'   => $pl->socialAccount?->name,
                    'custom_caption' => $pl->custom_caption,
                ]),
                'media_preview_url'  => $firstMedia?->public_url,
                'media_type'         => $firstMedia?->asset_type,
                'media_count'        => $p->mediaAssets->count(),
                'requires_approval'  => $p->requires_approval,
                'is_approved'        => $p->is_approved,
                'autopilot_managed'  => $p->autopilot_managed,
                'content_draft_id'   => $p->content_draft_id,
            ];
        })->toArray();
    }

    /**
     * Get monthly calendar summary (post counts per day).
     */
    public function getCalendarSummary(Brand $brand, ?string $month = null): array
    {
        $targetDate = $month ? Carbon::parse($month) : now();
        $start = $targetDate->copy()->startOfMonth()->setTimezone('UTC');
        $end = $targetDate->copy()->endOfMonth()->setTimezone('UTC');

        $posts = ScheduledPost::query()
            ->where('brand_id', $brand->id)
            ->whereBetween('scheduled_at', [$start, $end])
            ->get();

        $brandTz = $brand->settings?->timezone ?: 'UTC';
        $byDay = [];

        foreach ($posts as $p) {
            $dayKey = $p->scheduled_at->copy()->setTimezone($brandTz)->format('Y-m-d');
            if (!isset($byDay[$dayKey])) {
                $byDay[$dayKey] = [
                    'date'              => $dayKey,
                    'total'             => 0,
                    'scheduled'         => 0,
                    'awaiting_approval' => 0,
                    'cancelled'         => 0,
                ];
            }
            $byDay[$dayKey]['total']++;
            if ($p->status === 'scheduled') $byDay[$dayKey]['scheduled']++;
            if ($p->status === 'awaiting_approval') $byDay[$dayKey]['awaiting_approval']++;
            if ($p->status === 'cancelled') $byDay[$dayKey]['cancelled']++;
        }

        return [
            'month'        => $targetDate->format('Y-m'),
            'total_posts'  => $posts->count(),
            'scheduled'    => $posts->where('status', 'scheduled')->count(),
            'pending'      => $posts->where('status', 'awaiting_approval')->count(),
            'days'         => array_values($byDay),
        ];
    }
}
