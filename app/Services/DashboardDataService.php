<?php

namespace App\Services;

use App\Http\Resources\Api\V1\SocialAccountResource;
use App\Models\AgentExecution;
use App\Models\Brand;
use App\Models\BrandAnalytics;
use App\Models\BrandApprovalRequest;
use App\Models\BrandContentDraft;
use App\Models\BrandPost;
use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class DashboardDataService
{
    public const AGENTS = [
        'trend_research' => 'Trend Research Agent',
        'content_strategy' => 'Content Strategy Agent',
        'content_writing' => 'Content Writing Agent',
        'image_generation' => 'Image Generation Agent',
        'video_production' => 'Video Production Agent',
        'quality_assurance' => 'Quality Assurance Agent',
        'publishing' => 'Publishing Agent',
        'analytics' => 'Analytics Agent',
    ];

    public function resolveBrand(User $user, ?int $brandId): ?Brand
    {
        if ($brandId === null) {
            return null;
        }

        $brand = Brand::query()->findOrFail($brandId);
        abort_unless($brand->user_id === $user->id, 403, 'Unauthorized brand access.');

        return $brand;
    }

    public function brands(User $user): Collection
    {
        return $user->brands()->orderBy('name')->get(['id', 'name', 'industry', 'status'])
            ->map(fn (Brand $brand) => [
                'id' => $brand->id,
                'name' => $brand->name,
                'industry' => $brand->industry,
                'status' => $brand->status,
            ]);
    }

    public function overview(User $user, ?Brand $brand): array
    {
        $brands = $user->brands();
        $accounts = SocialAccount::query()->where('user_id', $user->id);
        $posts = $this->posts($user, $brand);
        $drafts = $this->drafts($user, $brand);
        $analytics = BrandAnalytics::query()->whereHas('brand', fn (Builder $query) => $query
            ->where('user_id', $user->id)
            ->when($brand, fn (Builder $query) => $query->whereKey($brand->id)));

        if ($brand) {
            $brands->whereKey($brand->id);
            $accounts->where('brand_id', $brand->id);
        } else {
            $accounts->where(function (Builder $query) use ($user): void {
                $query->whereNull('brand_id')
                    ->orWhereHas('brand', fn (Builder $brands) => $brands->where('user_id', $user->id));
            });
        }

        $unknownAiDrafts = (clone $drafts)->whereNull('is_ai_generated')->exists();
        $engagementAvailable = (clone $analytics)->exists();

        return [
            'brand_id' => $brand?->id,
            'brands' => (clone $brands)->count(),
            'active_brands' => (clone $brands)->where('status', 'active')->count(),
            'connected_accounts' => (clone $accounts)->connected()->count(),
            'published_posts' => (clone $posts)->where('status', 'published')->count(),
            'scheduled_posts' => (clone $posts)->whereIn('status', ['scheduled', 'queued'])
                ->whereNotNull('scheduled_for')->where('scheduled_for', '>=', now())->count(),
            'draft_posts' => (clone $drafts)->where('status', 'draft')->count(),
            'failed_posts' => (clone $posts)->where('status', 'failed')->count(),
            'ai_generated_content' => $unknownAiDrafts
                ? null
                : (clone $drafts)->where('is_ai_generated', true)->count(),
            'ai_generated_content_available' => ! $unknownAiDrafts,
            'engagements' => $engagementAvailable ? (int) (clone $analytics)->sum('engagements') : null,
            'engagement_available' => $engagementAvailable,
        ];
    }

    public function contentActivity(User $user, ?Brand $brand, int $limit = 50): array
    {
        $items = collect();

        $this->drafts($user, $brand)->with('brand:id,name')->latest()
            ->limit($limit)->get()->each(function (BrandContentDraft $draft) use ($items): void {
                $items->push([
                    'id' => 'draft-'.$draft->id,
                    'source_type' => 'draft',
                    'title' => $draft->title ?: Str::limit($draft->body, 90),
                    'preview' => Str::limit($draft->body, 180),
                    'brand_id' => $draft->brand_id,
                    'brand_name' => $draft->brand?->name,
                    'platform' => $draft->platform,
                    'format' => $draft->content_type,
                    'status' => $draft->status,
                    'scheduled_for' => $draft->scheduled_for?->toISOString(),
                    'created_at' => $draft->created_at?->toISOString(),
                    'action_url' => '/brands/'.$draft->brand_id,
                ]);
            });

        $this->posts($user, $brand)->with('brand:id,name')->latest()
            ->limit($limit)->get()->each(function (BrandPost $post) use ($items): void {
                $items->push([
                    'id' => 'post-'.$post->id,
                    'source_type' => 'post',
                    'title' => Str::limit($post->content, 90),
                    'preview' => Str::limit($post->content, 180),
                    'brand_id' => $post->brand_id,
                    'brand_name' => $post->brand?->name,
                    'platform' => $post->platform,
                    'format' => $post->metadata['content_type'] ?? 'post',
                    'status' => $post->status,
                    'scheduled_for' => $post->scheduled_for?->toISOString(),
                    'created_at' => $post->created_at?->toISOString(),
                    'action_url' => '/brands/'.$post->brand_id,
                ]);
            });

        $approvals = BrandApprovalRequest::query()
            ->where('status', 'pending')
            ->whereHas('brand', fn (Builder $query) => $query->where('user_id', $user->id)
                ->when($brand, fn (Builder $query) => $query->whereKey($brand->id)))
            ->with('brand:id,name')->latest()->limit($limit)->get();

        foreach ($approvals as $approval) {
            $items->push([
                'id' => 'approval-'.$approval->id,
                'source_type' => 'approval',
                'title' => 'Approval request #'.$approval->id,
                'preview' => Str::limit($approval->notes ?: 'Content is awaiting approval.', 180),
                'brand_id' => $approval->brand_id,
                'brand_name' => $approval->brand?->name,
                'platform' => null,
                'format' => $approval->resource_type,
                'status' => 'pending_approval',
                'scheduled_for' => null,
                'created_at' => $approval->created_at?->toISOString(),
                'action_url' => '/brands/'.$approval->brand_id,
            ]);
        }

        return $items->sortByDesc('created_at')->take($limit)->values()->all();
    }

    public function publishingCalendar(User $user, ?Brand $brand, $from, $to, int $limit = 50): array
    {
        return $this->posts($user, $brand)
            ->whereIn('status', ['scheduled', 'queued'])
            ->whereNotNull('scheduled_for')
            ->whereBetween('scheduled_for', [$from, $to])
            ->with('brand:id,name')
            ->orderBy('scheduled_for')
            ->limit($limit)
            ->get()
            ->map(fn (BrandPost $post) => [
                'id' => $post->id,
                'title' => Str::limit($post->content, 90),
                'preview' => Str::limit($post->content, 180),
                'brand_id' => $post->brand_id,
                'brand_name' => $post->brand?->name,
                'platform' => $post->platform,
                'status' => $post->status,
                'scheduled_for' => $post->scheduled_for?->toISOString(),
            ])->all();
    }

    public function socialAccounts(User $user, ?Brand $brand): array
    {
        return SocialAccount::query()
            ->where('user_id', $user->id)
            ->when(
                $brand,
                fn (Builder $query) => $query->where('brand_id', $brand->id),
                fn (Builder $query) => $query->where(function (Builder $query) use ($user): void {
                    $query->whereNull('brand_id')
                        ->orWhereHas('brand', fn (Builder $brands) => $brands->where('user_id', $user->id));
                })
            )
            ->with(['brand:id,name', 'token:id,social_account_id,expires_at'])
            ->latest()
            ->get()
            ->map(fn (SocialAccount $account) => SocialAccountResource::make($account)->resolve(request()))
            ->all();
    }

    public function agentActivity(User $user, ?Brand $brand): array
    {
        $latestExecutions = AgentExecution::query()
            ->where('user_id', $user->id)
            ->whereIn('agent_key', array_keys(self::AGENTS))
            ->when($brand, fn (Builder $query) => $query->where('brand_id', $brand->id))
            ->latest()
            ->get()
            ->unique('agent_key')
            ->keyBy('agent_key');

        return collect(self::AGENTS)->map(function (string $name, string $key) use ($latestExecutions): array {
            $execution = $latestExecutions->get($key);

            return [
                'agent_key' => $key,
                'name' => $name,
                'status' => $execution?->status ?? 'waiting_for_configuration',
                'message' => $execution?->message,
                'started_at' => $execution?->started_at?->toISOString(),
                'completed_at' => $execution?->completed_at?->toISOString(),
            ];
        })->values()->all();
    }

    public function posts(User $user, ?Brand $brand): Builder
    {
        return BrandPost::query()
            ->where('user_id', $user->id)
            ->whereHas('brand', fn (Builder $query) => $query->where('user_id', $user->id)
                ->when($brand, fn (Builder $query) => $query->whereKey($brand->id)))
            ->when($brand, fn (Builder $query) => $query->where('brand_id', $brand->id));
    }

    public function drafts(User $user, ?Brand $brand): Builder
    {
        return BrandContentDraft::query()
            ->where('user_id', $user->id)
            ->whereHas('brand', fn (Builder $query) => $query->where('user_id', $user->id)
                ->when($brand, fn (Builder $query) => $query->whereKey($brand->id)))
            ->when($brand, fn (Builder $query) => $query->where('brand_id', $brand->id));
    }
}
