<?php

namespace Tests\Feature;

use App\Models\AutopilotActivityLog;
use App\Models\AutopilotRule;
use App\Models\AutopilotSetting;
use App\Models\Brand;
use App\Models\BrandContentDraft;
use App\Models\MediaAsset;
use App\Models\PublishingQueue;
use App\Models\PublishingQueueItem;
use App\Models\ScheduledPost;
use App\Models\SocialAccount;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase8SchedulingTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Brand $brand;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->brand = Brand::factory()->create(['user_id' => $this->user->id]);
    }

    public function test_authenticated_user_can_create_a_scheduled_post(): void
    {
        $draft = BrandContentDraft::create([
            'brand_id' => $this->brand->id,
            'user_id' => $this->user->id,
            'title' => 'Product Launch Teaser',
            'prompt' => 'Write a launch teaser',
            'body' => 'Exciting announcement coming soon!',
            'content' => 'Exciting announcement coming soon!',
            'status' => 'draft',
        ]);

        $futureDate = Carbon::now()->addDays(2)->format('Y-m-d H:i:s');

        $response = $this->actingAs($this->user, 'sanctum')->postJson("/api/v1/brands/{$this->brand->id}/scheduled-posts", [
            'content_draft_id' => $draft->id,
            'platform' => 'instagram',
            'title' => 'Instagram Teaser',
            'content' => 'Exciting announcement coming soon! #teaser',
            'scheduled_at' => $futureDate,
            'timezone' => 'UTC',
            'status' => 'scheduled',
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.title', 'Instagram Teaser');
        $response->assertJsonPath('data.platforms.0.platform', 'instagram');
        $response->assertJsonPath('data.status', 'scheduled');

        $this->assertDatabaseHas('scheduled_posts', [
            'brand_id' => $this->brand->id,
            'title' => 'Instagram Teaser',
            'status' => 'scheduled',
        ]);

        // Content draft should be marked as scheduled
        $this->assertEquals('scheduled', $draft->fresh()->status);
    }

    public function test_user_cannot_schedule_for_another_users_brand(): void
    {
        $otherUser = User::factory()->create();
        $otherBrand = Brand::factory()->create(['user_id' => $otherUser->id]);

        $futureDate = Carbon::now()->addDays(2)->format('Y-m-d H:i:s');

        $response = $this->actingAs($this->user, 'sanctum')->postJson("/api/v1/brands/{$otherBrand->id}/scheduled-posts", [
            'platform' => 'linkedin',
            'title' => 'Unauthorized Post',
            'content' => 'Should fail',
            'scheduled_at' => $futureDate,
        ]);

        $response->assertStatus(403);
    }

    public function test_scheduling_validates_past_dates(): void
    {
        $pastDate = Carbon::now()->subDay()->format('Y-m-d H:i:s');

        $response = $this->actingAs($this->user, 'sanctum')->postJson("/api/v1/brands/{$this->brand->id}/scheduled-posts", [
            'platform' => 'facebook',
            'title' => 'Past Post',
            'content' => 'Content in the past',
            'scheduled_at' => $pastDate,
        ]);

        $response->assertStatus(422);
    }

    public function test_content_calendar_returns_persisted_records(): void
    {
        $date1 = Carbon::now()->addDays(1)->setHour(10)->setMinute(0)->setSecond(0);
        $date2 = Carbon::now()->addDays(3)->setHour(14)->setMinute(0)->setSecond(0);

        ScheduledPost::create([
            'brand_id' => $this->brand->id,
            'user_id' => $this->user->id,
            'platform' => 'facebook',
            'title' => 'Post One',
            'content' => 'Content One',
            'scheduled_at' => $date1,
            'timezone' => 'UTC',
            'status' => 'scheduled',
        ]);

        ScheduledPost::create([
            'brand_id' => $this->brand->id,
            'user_id' => $this->user->id,
            'platform' => 'instagram',
            'title' => 'Post Two',
            'content' => 'Content Two',
            'scheduled_at' => $date2,
            'timezone' => 'UTC',
            'status' => 'scheduled',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')->getJson(
            "/api/v1/brands/{$this->brand->id}/calendar?start_date=" . Carbon::now()->startOfDay()->format('Y-m-d') . "&end_date=" . Carbon::now()->addDays(7)->format('Y-m-d')
        );

        $response->assertOk();
        $posts = $response->json('data');
        $this->assertCount(2, $posts);
    }

    public function test_reschedule_post_updates_and_persists_time(): void
    {
        $originalDate = Carbon::now()->addDays(2)->setHour(9)->setMinute(0)->setSecond(0);
        $newDate = Carbon::now()->addDays(5)->setHour(16)->setMinute(30)->setSecond(0);

        $post = ScheduledPost::create([
            'brand_id' => $this->brand->id,
            'user_id' => $this->user->id,
            'platform' => 'linkedin',
            'title' => 'Original Post',
            'content' => 'Content',
            'scheduled_at' => $originalDate,
            'timezone' => 'UTC',
            'status' => 'scheduled',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')->postJson("/api/v1/scheduled-posts/{$post->id}/reschedule", [
            'scheduled_at' => $newDate->format('Y-m-d H:i:s'),
            'timezone' => 'UTC',
        ]);

        $response->assertOk();
        $this->assertEquals($newDate->toDateTimeString(), $post->fresh()->scheduled_at->toDateTimeString());
    }

    public function test_cancel_post_changes_status(): void
    {
        $post = ScheduledPost::create([
            'brand_id' => $this->brand->id,
            'user_id' => $this->user->id,
            'platform' => 'tiktok',
            'title' => 'Cancel Me',
            'content' => 'Content',
            'scheduled_at' => Carbon::now()->addDays(2),
            'timezone' => 'UTC',
            'status' => 'scheduled',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')->postJson("/api/v1/scheduled-posts/{$post->id}/cancel", [
            'reason' => 'Change of strategy',
        ]);

        $response->assertOk();
        $this->assertEquals('cancelled', $post->fresh()->status);
        $this->assertEquals('Change of strategy', $post->fresh()->cancellation_reason);
    }

    public function test_approval_flow_for_pending_post(): void
    {
        $post = ScheduledPost::create([
            'brand_id' => $this->brand->id,
            'user_id' => $this->user->id,
            'platform' => 'youtube',
            'title' => 'Approval Post',
            'content' => 'Video description',
            'scheduled_at' => Carbon::now()->addDays(3),
            'timezone' => 'UTC',
            'status' => 'pending_approval',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')->postJson("/api/v1/scheduled-posts/{$post->id}/approve");

        $response->assertOk();
        $this->assertEquals('scheduled', $post->fresh()->status);
        $this->assertNotNull($post->fresh()->approved_at);
        $this->assertEquals($this->user->id, $post->fresh()->approved_by);
    }

    public function test_publishing_queue_creation_and_pause_resume(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')->postJson("/api/v1/brands/{$this->brand->id}/publishing-queue", [
            'name' => 'Weekly Content Queue',
            'posting_interval_minutes' => 120,
        ]);

        $response->assertStatus(200);
        $queueId = $response->json('data.id');

        // Pause queue
        $pauseRes = $this->actingAs($this->user, 'sanctum')->postJson("/api/v1/publishing-queue/{$queueId}/pause");
        $pauseRes->assertOk();
        $this->assertEquals('paused', PublishingQueue::find($queueId)->status);

        // Resume queue
        $resumeRes = $this->actingAs($this->user, 'sanctum')->postJson("/api/v1/publishing-queue/{$queueId}/resume");
        $resumeRes->assertOk();
        $this->assertEquals('active', PublishingQueue::find($queueId)->status);
    }

    public function test_queue_item_reordering(): void
    {
        $queue = PublishingQueue::create([
            'brand_id' => $this->brand->id,
            'user_id' => $this->user->id,
            'name' => 'Reorder Queue',
            'status' => 'active',
        ]);

        $post1 = ScheduledPost::create([
            'brand_id' => $this->brand->id,
            'user_id' => $this->user->id,
            'platform' => 'instagram',
            'title' => 'Post 1',
            'content' => '1',
            'scheduled_at' => Carbon::now()->addDays(1),
            'timezone' => 'UTC',
            'status' => 'queued',
        ]);

        $post2 = ScheduledPost::create([
            'brand_id' => $this->brand->id,
            'user_id' => $this->user->id,
            'platform' => 'instagram',
            'title' => 'Post 2',
            'content' => '2',
            'scheduled_at' => Carbon::now()->addDays(2),
            'timezone' => 'UTC',
            'status' => 'queued',
        ]);

        $item1 = PublishingQueueItem::create([
            'brand_id' => $this->brand->id,
            'user_id' => $this->user->id,
            'publishing_queue_id' => $queue->id,
            'scheduled_post_id' => $post1->id,
            'position' => 1,
            'status' => 'queued',
        ]);

        $item2 = PublishingQueueItem::create([
            'brand_id' => $this->brand->id,
            'user_id' => $this->user->id,
            'publishing_queue_id' => $queue->id,
            'scheduled_post_id' => $post2->id,
            'position' => 2,
            'status' => 'queued',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')->postJson("/api/v1/publishing-queue/{$queue->id}/reorder", [
            'ordered_item_ids' => [$item2->id, $item1->id],
        ]);

        $response->assertOk();
        $this->assertEquals(1, $item2->fresh()->position);
        $this->assertEquals(2, $item1->fresh()->position);
    }

    public function test_autopilot_settings_lifecycle_and_activity_logging(): void
    {
        // 1. Enable autopilot
        $enableRes = $this->actingAs($this->user, 'sanctum')->postJson("/api/v1/brands/{$this->brand->id}/autopilot/enable");
        $enableRes->assertOk();

        $setting = AutopilotSetting::where('brand_id', $this->brand->id)->first();
        $this->assertNotNull($setting);
        $this->assertTrue($setting->is_enabled);

        // 2. Update autopilot config
        $updateRes = $this->actingAs($this->user, 'sanctum')->putJson("/api/v1/brands/{$this->brand->id}/autopilot", [
            'mode' => 'ai_assisted',
            'posts_per_week' => 5,
            'min_gap_hours' => 6,
            'preferred_days' => ['tuesday', 'thursday', 'saturday'],
            'use_ai_optimal_times' => true,
            'requires_approval' => true,
        ]);

        $updateRes->assertOk();
        $this->assertEquals('ai_assisted', $setting->fresh()->mode);
        $this->assertEquals(5, $setting->fresh()->posts_per_week);

        // 3. Pause autopilot
        $pauseRes = $this->actingAs($this->user, 'sanctum')->postJson("/api/v1/brands/{$this->brand->id}/autopilot/pause");
        $pauseRes->assertOk();
        $this->assertFalse($setting->fresh()->is_enabled);

        // 4. Resume autopilot
        $resumeRes = $this->actingAs($this->user, 'sanctum')->postJson("/api/v1/brands/{$this->brand->id}/autopilot/resume");
        $resumeRes->assertOk();
        $this->assertTrue($setting->fresh()->is_enabled);

        // 5. Disable autopilot
        $disableRes = $this->actingAs($this->user, 'sanctum')->postJson("/api/v1/brands/{$this->brand->id}/autopilot/disable");
        $disableRes->assertOk();
        $this->assertFalse($setting->fresh()->is_enabled);

        // 6. Verify activity log records were created
        $activityRes = $this->actingAs($this->user, 'sanctum')->getJson("/api/v1/brands/{$this->brand->id}/autopilot/activity");
        $activityRes->assertOk();
        $logs = $activityRes->json('data');
        $this->assertNotEmpty($logs);
    }

    public function test_autopilot_rules_crud(): void
    {
        // Create rule
        $createRes = $this->actingAs($this->user, 'sanctum')->postJson("/api/v1/brands/{$this->brand->id}/autopilot/rules", [
            'name' => 'Educational Content on Tuesdays',
            'rule_type' => 'content_category',
            'conditions' => ['category' => 'educational', 'day' => 'tuesday'],
            'actions' => ['schedule_at' => '10:00'],
            'priority' => 10,
            'is_active' => true,
        ]);

        $createRes->assertStatus(201);
        $ruleId = $createRes->json('rule.id');
        $this->assertNotNull($ruleId);

        // Update rule
        $updateRes = $this->actingAs($this->user, 'sanctum')->putJson("/api/v1/brands/{$this->brand->id}/autopilot/rules/{$ruleId}", [
            'priority' => 20,
        ]);
        $updateRes->assertOk();
        $this->assertEquals(20, AutopilotRule::find($ruleId)->priority);

        // Delete rule
        $deleteRes = $this->actingAs($this->user, 'sanctum')->deleteJson("/api/v1/brands/{$this->brand->id}/autopilot/rules/{$ruleId}");
        $deleteRes->assertOk();
        $this->assertNull(AutopilotRule::find($ruleId));
    }

    public function test_best_times_recommendation_endpoint(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')->getJson("/api/v1/brands/{$this->brand->id}/scheduling/best-times?platform=instagram");

        $response->assertOk();
        $response->assertJsonStructure([
            'brand_id',
            'platform',
            'data',
        ]);
    }

    public function test_scheduling_settings_update(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')->putJson("/api/v1/brands/{$this->brand->id}/scheduling-settings", [
            'timezone' => 'America/New_York',
            'publishing_frequency' => 'weekly',
            'preferred_publishing_times' => ['09:00', '15:00'],
            'blackout_periods' => [
                ['start_hour' => 22, 'end_hour' => 6],
            ],
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('brand_settings', [
            'brand_id' => $this->brand->id,
            'timezone' => 'America/New_York',
            'publishing_frequency' => 'weekly',
        ]);
    }
}
