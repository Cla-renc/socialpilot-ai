<?php

namespace Tests\Feature\Api\V1;

use App\Models\AgentExecution;
use App\Models\Brand;
use App\Models\BrandAnalytics;
use App\Models\BrandContentDraft;
use App\Models\BrandPost;
use App\Models\SocialAccount;
use App\Models\User;
use App\Notifications\DashboardEventNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OperationalDashboardApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_routes_require_authentication(): void
    {
        $this->getJson('/api/v1/dashboard/overview')->assertUnauthorized();
        $this->getJson('/api/v1/notifications')->assertUnauthorized();
    }

    public function test_overview_uses_owned_records_and_filters_by_brand(): void
    {
        $user = User::factory()->create();
        $brand = Brand::factory()->for($user)->create(['status' => 'active']);
        $otherBrand = Brand::factory()->for($user)->create(['status' => 'draft']);
        $foreignBrand = Brand::factory()->create();

        $this->createPost($brand, $user, ['status' => 'published']);
        $this->createPost($otherBrand, $user, ['status' => 'failed']);
        $this->createPost($foreignBrand, $foreignBrand->user, ['status' => 'published']);
        $this->createPost($brand, $user, [
            'status' => 'scheduled',
            'scheduled_for' => now()->addDay(),
        ]);
        BrandContentDraft::query()->create([
            'user_id' => $user->id,
            'brand_id' => $brand->id,
            'title' => 'Known AI draft',
            'prompt' => 'Write a post',
            'body' => 'Generated copy',
            'status' => 'draft',
            'is_ai_generated' => true,
        ]);
        BrandContentDraft::query()->create([
            'user_id' => $user->id,
            'brand_id' => $brand->id,
            'title' => 'Manual draft',
            'prompt' => 'Write a post',
            'body' => 'Manually written copy',
            'status' => 'draft',
            'is_ai_generated' => false,
        ]);
        SocialAccount::query()->create([
            'user_id' => $user->id,
            'brand_id' => $brand->id,
            'provider' => 'facebook',
            'provider_account_id' => 'page-1',
            'display_name' => 'Brand page',
            'status' => 'connected',
        ]);
        BrandAnalytics::query()->create([
            'brand_id' => $brand->id,
            'metric_date' => now()->toDateString(),
            'engagements' => 17,
        ]);

        $this->actingAs($user)
            ->getJson('/api/v1/dashboard/overview')
            ->assertOk()
            ->assertJsonPath('data.brands', 2)
            ->assertJsonPath('data.active_brands', 1)
            ->assertJsonPath('data.connected_accounts', 1)
            ->assertJsonPath('data.published_posts', 1)
            ->assertJsonPath('data.scheduled_posts', 1)
            ->assertJsonPath('data.draft_posts', 2)
            ->assertJsonPath('data.failed_posts', 1)
            ->assertJsonPath('data.ai_generated_content', 1)
            ->assertJsonPath('data.engagements', 17);

        $this->getJson('/api/v1/dashboard/overview?brand_id='.$brand->id)
            ->assertOk()
            ->assertJsonPath('data.brands', 1)
            ->assertJsonPath('data.active_brands', 1)
            ->assertJsonPath('data.published_posts', 1)
            ->assertJsonPath('data.scheduled_posts', 1)
            ->assertJsonPath('data.failed_posts', 0);

        $this->getJson('/api/v1/dashboard/overview?brand_id='.$foreignBrand->id)
            ->assertForbidden();
    }

    public function test_content_calendar_accounts_and_agent_activity_are_real_and_owner_scoped(): void
    {
        $user = User::factory()->create();
        $brand = Brand::factory()->for($user)->create();
        $foreignBrand = Brand::factory()->create();
        $this->createPost($brand, $user, [
            'content' => 'Scheduled item',
            'status' => 'scheduled',
            'scheduled_for' => now()->addDay(),
        ]);
        $this->createPost($foreignBrand, $foreignBrand->user, [
            'content' => 'Foreign item',
            'status' => 'scheduled',
            'scheduled_for' => now()->addDay(),
        ]);
        SocialAccount::query()->create([
            'user_id' => $user->id,
            'brand_id' => $brand->id,
            'provider' => 'instagram',
            'provider_account_id' => 'account-1',
            'display_name' => 'Brand Instagram',
            'status' => 'connected',
        ]);
        AgentExecution::query()->create([
            'user_id' => $user->id,
            'brand_id' => $brand->id,
            'agent_key' => 'trend_research',
            'status' => 'completed',
        ]);

        $this->actingAs($user)
            ->getJson('/api/v1/dashboard/content-activity?brand_id='.$brand->id)
            ->assertOk()
            ->assertJsonFragment(['title' => 'Scheduled item'])
            ->assertJsonMissing(['title' => 'Foreign item']);

        $this->getJson('/api/v1/dashboard/publishing-calendar?brand_id='.$brand->id)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Scheduled item');

        $this->getJson('/api/v1/dashboard/social-accounts?brand_id='.$brand->id)
            ->assertOk()
            ->assertJsonPath('data.0.display_name', 'Brand Instagram')
            ->assertJsonMissingPath('data.0.metadata.access_token');

        $this->getJson('/api/v1/dashboard/agent-activity?brand_id='.$brand->id)
            ->assertOk()
            ->assertJsonPath('data.0.status', 'completed');
    }

    public function test_notifications_are_private_paginated_and_mark_read_persistently(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $brand = Brand::factory()->for($user)->create();
        $secondBrand = Brand::factory()->for($user)->create();
        $foreignBrand = Brand::factory()->create();
        $user->notify(new DashboardEventNotification('Owned', 'Own notification', null, $brand->id));
        $user->notify(new DashboardEventNotification('Other owned brand', 'Filtered out', null, $secondBrand->id));
        $otherUser->notify(new DashboardEventNotification('Private', 'Other notification'));
        $notification = $user->notifications()->firstOrFail();

        $this->actingAs($user)
            ->getJson('/api/v1/notifications')
            ->assertOk()
            ->assertJsonPath('data.0.title', 'Owned')
            ->assertJsonPath('data.0.is_read', false)
            ->assertJsonMissing(['title' => 'Private']);

        $this->getJson('/api/v1/notifications?brand_id='.$brand->id)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Owned');
        $this->getJson('/api/v1/notifications?brand_id='.$foreignBrand->id)
            ->assertForbidden();

        $this->postJson('/api/v1/notifications/'.$notification->id.'/read')
            ->assertOk()
            ->assertJsonPath('data.is_read', true);

        $this->assertNotNull($user->notifications()->findOrFail($notification->id)->read_at);
        $this->actingAs($otherUser)
            ->postJson('/api/v1/notifications/'.$notification->id.'/read')
            ->assertNotFound();
    }

    private function createPost(Brand $brand, User $user, array $attributes = []): BrandPost
    {
        return BrandPost::query()->create([
            'user_id' => $user->id,
            'brand_id' => $brand->id,
            'content' => 'Post content',
            'status' => 'draft',
            ...$attributes,
        ]);
    }
}