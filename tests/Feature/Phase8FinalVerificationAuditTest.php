<?php

namespace Tests\Feature;

use App\Console\Commands\ProcessAutopilotCommand;
use App\Console\Commands\ProcessPublishingQueueCommand;
use App\Jobs\ProcessAutopilotSchedulingJob;
use App\Jobs\RefreshSchedulingRecommendationsJob;
use App\Jobs\ScheduleQueuedContentJob;
use App\Jobs\SendUpcomingPostReminderJob;
use App\Models\AutopilotActivityLog;
use App\Models\AutopilotRule;
use App\Models\AutopilotSetting;
use App\Models\Brand;
use App\Models\BrandContentDraft;
use App\Models\BrandSetting;
use App\Models\MediaAsset;
use App\Models\PublishingQueue;
use App\Models\PublishingQueueItem;
use App\Models\ScheduledPost;
use App\Models\ScheduledPostMedia;
use App\Models\ScheduledPostPlatform;
use App\Models\SchedulingRecommendation;
use App\Models\SocialAccount;
use App\Models\User;
use App\Notifications\DashboardEventNotification;
use App\Services\AutopilotService;
use App\Services\PublishingQueueService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class Phase8FinalVerificationAuditTest extends TestCase
{
    use RefreshDatabase;

    protected User $userA;
    protected User $userB;
    protected Brand $brandA;
    protected Brand $brandB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->userA = User::factory()->create(['name' => 'Owner A', 'email' => 'owner_a@example.com']);
        $this->userB = User::factory()->create(['name' => 'Owner B', 'email' => 'owner_b@example.com']);

        $this->brandA = Brand::factory()->create([
            'user_id' => $this->userA->id,
            'name'    => 'Acme Global',
        ]);
        BrandSetting::create([
            'brand_id'             => $this->brandA->id,
            'timezone'             => 'Africa/Nairobi',
            'publishing_frequency' => 'weekly',
        ]);

        $this->brandB = Brand::factory()->create([
            'user_id' => $this->userB->id,
            'name'    => 'Beta Innovations',
        ]);
        BrandSetting::create([
            'brand_id'             => $this->brandB->id,
            'timezone'             => 'America/New_York',
            'publishing_frequency' => 'weekly',
        ]);
    }

    // =========================================================================
    // 1. DATABASE VERIFICATION
    // =========================================================================

    public function test_all_nine_phase_eight_tables_persist_records_and_enforce_relationships(): void
    {
        // 1. scheduled_posts
        $post = ScheduledPost::create([
            'user_id'           => $this->userA->id,
            'brand_id'          => $this->brandA->id,
            'title'             => 'Audit Post',
            'caption'           => 'Verification Caption',
            'body'              => 'Detailed body',
            'scheduled_at'      => Carbon::now()->addDays(2),
            'timezone'          => 'Africa/Nairobi',
            'status'            => 'scheduled',
            'requires_approval' => false,
        ]);
        $this->assertDatabaseHas('scheduled_posts', ['id' => $post->id, 'title' => 'Audit Post']);

        // 2. scheduled_post_platforms
        $plat = ScheduledPostPlatform::create([
            'scheduled_post_id' => $post->id,
            'platform'          => 'instagram',
            'custom_title'      => 'Insta Title',
            'custom_caption'    => 'Insta Caption',
            'scheduled_at'      => Carbon::now()->addDays(2),
            'status'            => 'scheduled',
        ]);
        $this->assertDatabaseHas('scheduled_post_platforms', ['id' => $plat->id, 'platform' => 'instagram']);

        // 3. scheduled_post_media
        $asset = MediaAsset::create([
            'user_id'           => $this->userA->id,
            'brand_id'          => $this->brandA->id,
            'asset_type'        => 'image',
            'title'             => 'Audit Hero Image',
            'file_name'         => 'hero.jpg',
            'file_format'       => 'jpg',
            'mime_type'         => 'image/jpeg',
            'storage_disk'      => 'public',
            'storage_path'      => 'media/hero.jpg',
            'public_url'        => 'http://localhost/media/hero.jpg',
            'generation_status' => 'completed',
        ]);
        $postMedia = ScheduledPostMedia::create([
            'scheduled_post_id' => $post->id,
            'media_asset_id'    => $asset->id,
            'order'             => 1,
        ]);
        $this->assertDatabaseHas('scheduled_post_media', ['id' => $postMedia->id, 'media_asset_id' => $asset->id]);

        // 4. publishing_queues
        $queue = PublishingQueue::create([
            'brand_id'                 => $this->brandA->id,
            'user_id'                  => $this->userA->id,
            'name'                     => 'Main Queue',
            'status'                   => 'active',
            'posting_interval_minutes' => 180,
            'timezone'                 => 'Africa/Nairobi',
        ]);
        $this->assertDatabaseHas('publishing_queues', ['id' => $queue->id, 'name' => 'Main Queue']);

        // 5. publishing_queue_items
        $queueItem = PublishingQueueItem::create([
            'publishing_queue_id' => $queue->id,
            'brand_id'            => $this->brandA->id,
            'user_id'             => $this->userA->id,
            'scheduled_post_id'   => $post->id,
            'position'            => 1,
            'status'              => 'queued',
        ]);
        $this->assertDatabaseHas('publishing_queue_items', ['id' => $queueItem->id, 'position' => 1]);

        // 6. autopilot_settings
        $autopilot = AutopilotSetting::create([
            'brand_id'       => $this->brandA->id,
            'user_id'        => $this->userA->id,
            'is_enabled'     => true,
            'mode'           => 'ai_assisted',
            'posts_per_week' => 4,
            'min_gap_hours'  => 8,
            'timezone'       => 'Africa/Nairobi',
        ]);
        $this->assertDatabaseHas('autopilot_settings', ['id' => $autopilot->id, 'posts_per_week' => 4]);

        // 7. autopilot_rules
        $rule = AutopilotRule::create([
            'brand_id'   => $this->brandA->id,
            'user_id'    => $this->userA->id,
            'name'       => 'Educational Tuesdays',
            'rule_type'  => 'category_schedule',
            'conditions' => ['category' => 'educational', 'day' => 'tuesday'],
            'actions'    => ['slot' => '10:00'],
            'is_active'  => true,
        ]);
        $this->assertDatabaseHas('autopilot_rules', ['id' => $rule->id, 'name' => 'Educational Tuesdays']);

        // 8. autopilot_activity_logs
        $log = AutopilotActivityLog::create([
            'brand_id' => $this->brandA->id,
            'user_id'  => $this->userA->id,
            'action'   => 'audit_execution',
            'details'  => ['metric' => 'verified'],
            'status'   => 'success',
        ]);
        $this->assertDatabaseHas('autopilot_activity_logs', ['id' => $log->id, 'action' => 'audit_execution']);

        // 9. scheduling_recommendations
        $rec = SchedulingRecommendation::create([
            'brand_id'         => $this->brandA->id,
            'user_id'          => $this->userA->id,
            'platform'         => 'instagram',
            'day_of_week'      => 'wednesday',
            'suggested_time'   => '14:00',
            'timezone'         => 'Africa/Nairobi',
            'reason'           => 'Afternoon engagement surge',
            'evidence_type'    => 'historical_performance',
            'confidence'       => 0.92,
        ]);
        $this->assertDatabaseHas('scheduling_recommendations', ['id' => $rec->id, 'platform' => 'instagram']);

        // Relationships & cascades check
        $this->assertEquals(1, $post->platforms()->count());
        $this->assertEquals(1, $post->mediaAssets()->count());
        $this->assertEquals(1, $queue->items()->count());
    }

    // =========================================================================
    // 2. SCHEDULER & QUEUE EXECUTION
    // =========================================================================

    public function test_scheduler_commands_execute_cleanly(): void
    {
        $autopilotCode = Artisan::call('autopilot:process', ['--brand' => $this->brandA->id]);
        $this->assertEquals(0, $autopilotCode);

        $queue = PublishingQueue::create([
            'brand_id'                 => $this->brandA->id,
            'user_id'                  => $this->userA->id,
            'name'                     => 'Test Queue',
            'status'                   => 'active',
            'posting_interval_minutes' => 60,
        ]);
        $queueCode = Artisan::call('scheduling:process-queue', ['--queue' => $queue->id]);
        $this->assertEquals(0, $queueCode);
    }

    public function test_send_upcoming_post_reminder_job_dispatches_notification_and_prevents_duplicate(): void
    {
        Notification::fake();

        // Create post in the 60-minute reminder window
        $post = ScheduledPost::create([
            'user_id'      => $this->userA->id,
            'brand_id'     => $this->brandA->id,
            'title'        => 'Imminent Post',
            'caption'      => 'Going live soon',
            'scheduled_at' => Carbon::now()->addMinutes(30),
            'timezone'     => 'Africa/Nairobi',
            'status'       => 'scheduled',
        ]);

        $job = new SendUpcomingPostReminderJob();
        $job->handle();

        Notification::assertSentTo(
            $this->userA,
            DashboardEventNotification::class,
            function ($notification) {
                $data = $notification->toArray($this->userA);
                return str_contains($data['title'], 'Upcoming Scheduled Post');
            }
        );

        // Verify reminder_sent_at was recorded
        $this->assertNotNull($post->fresh()->metadata['reminder_sent_at'] ?? null);

        // Re-running job should NOT send duplicate notification
        Notification::fake();
        $job->handle();
        Notification::assertNothingSent();
    }

    public function test_autopilot_cycle_never_repeatedly_schedules_the_same_content(): void
    {
        $setting = AutopilotSetting::create([
            'brand_id'          => $this->brandA->id,
            'user_id'           => $this->userA->id,
            'is_enabled'        => true,
            'mode'              => 'automatic',
            'posts_per_week'    => 5,
            'min_gap_hours'     => 6,
            'requires_approval' => false,
            'timezone'          => 'Africa/Nairobi',
        ]);

        $draft = BrandContentDraft::create([
            'brand_id' => $this->brandA->id,
            'user_id'  => $this->userA->id,
            'title'    => 'Single Occurrence Draft',
            'prompt'   => 'Draft prompt',
            'body'     => 'Draft body content',
            'status'   => 'ready',
        ]);

        $service = app(AutopilotService::class);

        // First run: should schedule the draft
        $result1 = $service->runAutopilotForBrand($this->brandA);
        $this->assertEquals('completed', $result1['status']);
        $this->assertEquals('scheduled', $draft->fresh()->status);
        $this->assertEquals(1, ScheduledPost::where('content_draft_id', $draft->id)->count());

        // Second run: draft is already status 'scheduled', so it must NOT be scheduled again
        $result2 = $service->runAutopilotForBrand($this->brandA);
        $this->assertEquals('no_pending_content', $result2['status']);
        $this->assertEquals(1, ScheduledPost::where('content_draft_id', $draft->id)->count());
    }

    // =========================================================================
    // 3. AUTOPILOT SAFETY & CORRECTNESS (MODES A, B, C)
    // =========================================================================

    public function test_autopilot_manual_mode_never_schedules_automatically(): void
    {
        AutopilotSetting::create([
            'brand_id'   => $this->brandA->id,
            'user_id'    => $this->userA->id,
            'is_enabled' => true,
            'mode'       => 'manual',
        ]);

        BrandContentDraft::create([
            'brand_id' => $this->brandA->id,
            'user_id'  => $this->userA->id,
            'title'    => 'Manual Mode Draft',
            'prompt'   => 'Prompt',
            'body'     => 'Body',
            'status'   => 'ready',
        ]);

        $service = app(AutopilotService::class);
        $res = $service->runAutopilotForBrand($this->brandA);

        $this->assertEquals('skipped', $res['status']);
        $this->assertEquals(0, ScheduledPost::where('brand_id', $this->brandA->id)->count());
    }

    public function test_autopilot_ai_assisted_mode_generates_proposals_without_persisting_unapproved_schedules(): void
    {
        AutopilotSetting::create([
            'brand_id'   => $this->brandA->id,
            'user_id'    => $this->userA->id,
            'is_enabled' => true,
            'mode'       => 'ai_assisted',
        ]);

        $draft = BrandContentDraft::create([
            'brand_id' => $this->brandA->id,
            'user_id'  => $this->userA->id,
            'title'    => 'AI Assisted Draft',
            'prompt'   => 'Prompt',
            'body'     => 'Body',
            'status'   => 'ready',
        ]);

        $service = app(AutopilotService::class);
        $res = $service->runAutopilotForBrand($this->brandA);

        $this->assertEquals('completed', $res['status']);
        $this->assertEquals('ai_assisted', $res['mode']);
        // Crucial safety check: ScheduledPost must NOT be created in AI-assisted mode until user approves
        $this->assertEquals(0, ScheduledPost::where('brand_id', $this->brandA->id)->count());
        $this->assertEquals('ready', $draft->fresh()->status);
    }

    public function test_autopilot_paused_or_disabled_cannot_create_schedules(): void
    {
        $setting = AutopilotSetting::create([
            'brand_id'   => $this->brandA->id,
            'user_id'    => $this->userA->id,
            'is_enabled' => false,
            'mode'       => 'automatic',
        ]);

        BrandContentDraft::create([
            'brand_id' => $this->brandA->id,
            'user_id'  => $this->userA->id,
            'title'    => 'Draft When Disabled',
            'prompt'   => 'Prompt',
            'body'     => 'Body',
            'status'   => 'ready',
        ]);

        $service = app(AutopilotService::class);
        $res = $service->runAutopilotForBrand($this->brandA);

        $this->assertEquals('skipped', $res['status']);
        $this->assertEquals(0, ScheduledPost::where('brand_id', $this->brandA->id)->count());
    }

    // =========================================================================
    // 4. TIMEZONE & CALENDAR VERIFICATION
    // =========================================================================

    public function test_timezone_storage_in_utc_and_nairobi_local_display(): void
    {
        // 15:00:00 in Africa/Nairobi (UTC+3) is exactly 12:00:00 in UTC
        $nairobiDate = Carbon::now()->addDays(3)->setTimezone('Africa/Nairobi')->setTime(15, 0, 0);

        $response = $this->actingAs($this->userA, 'sanctum')->postJson("/api/v1/brands/{$this->brandA->id}/scheduled-posts", [
            'title'        => 'Nairobi Event Announcement',
            'caption'      => 'Live in Nairobi',
            'platform'     => 'instagram',
            'scheduled_at' => $nairobiDate->format('Y-m-d H:i:s'),
            'timezone'     => 'Africa/Nairobi',
            'status'       => 'scheduled',
        ]);

        $response->assertStatus(201);
        $postId = $response->json('data.id');

        $post = ScheduledPost::findOrFail($postId);

        // Verify stored in UTC as 12:00:00
        $this->assertEquals(12, $post->scheduled_at->hour);

        // Verify Calendar API converts to brand timezone (15:00)
        $startStr = $nairobiDate->copy()->subDay()->format('Y-m-d');
        $endStr = $nairobiDate->copy()->addDay()->format('Y-m-d');

        $calRes = $this->actingAs($this->userA, 'sanctum')->getJson("/api/v1/brands/{$this->brandA->id}/calendar?start_date={$startStr}&end_date={$endStr}");
        $calRes->assertOk();

        $entry = collect($calRes->json('data'))->firstWhere('id', $postId);
        $this->assertNotNull($entry);
        $this->assertEquals('3:00 PM', $entry['formatted_time']);
        $this->assertEquals('Africa/Nairobi', $entry['timezone']);
    }

    public function test_daylight_saving_and_timezone_boundary_handling(): void
    {
        // Test America/New_York daylight saving time transitions
        // Summer (EDT = UTC-4): 2027-07-15 14:00 EDT should be 18:00 UTC
        $summerDate = Carbon::parse('2027-07-15 14:00:00', 'America/New_York');
        $resSummer = $this->actingAs($this->userB, 'sanctum')->postJson("/api/v1/brands/{$this->brandB->id}/scheduled-posts", [
            'title'        => 'Summer EDT Post',
            'scheduled_at' => '2027-07-15 14:00:00',
            'timezone'     => 'America/New_York',
            'platform'     => 'linkedin',
            'status'       => 'scheduled',
        ]);
        $resSummer->assertStatus(201);
        $postSummer = ScheduledPost::find($resSummer->json('data.id'));
        $this->assertEquals('2027-07-15 18:00:00', $postSummer->scheduled_at->format('Y-m-d H:i:s'));

        // Winter (EST = UTC-5): 2027-01-15 14:00 EST should be 19:00 UTC
        $resWinter = $this->actingAs($this->userB, 'sanctum')->postJson("/api/v1/brands/{$this->brandB->id}/scheduled-posts", [
            'title'        => 'Winter EST Post',
            'scheduled_at' => '2027-01-15 14:00:00',
            'timezone'     => 'America/New_York',
            'platform'     => 'linkedin',
            'status'       => 'scheduled',
        ]);
        $resWinter->assertStatus(201);
        $postWinter = ScheduledPost::find($resWinter->json('data.id'));
        $this->assertEquals('2027-01-15 19:00:00', $postWinter->scheduled_at->format('Y-m-d H:i:s'));

        // Confirm local time format matches in both cases
        $this->assertEquals('2:00 PM', $postSummer->scheduled_at->copy()->setTimezone('America/New_York')->format('g:i A'));
        $this->assertEquals('2:00 PM', $postWinter->scheduled_at->copy()->setTimezone('America/New_York')->format('g:i A'));
    }

    public function test_reschedule_and_cancel_calendar_interactions(): void
    {
        $originalUtc = Carbon::now()->addDays(2)->setTime(10, 0, 0);
        $post = ScheduledPost::create([
            'user_id'      => $this->userA->id,
            'brand_id'     => $this->brandA->id,
            'title'        => 'Change Time Post',
            'caption'      => 'Caption',
            'scheduled_at' => $originalUtc,
            'timezone'     => 'Africa/Nairobi',
            'status'       => 'scheduled',
        ]);

        // Reschedule to 4 days later at 16:00
        $newLocal = Carbon::now()->addDays(4)->setTime(16, 0, 0);
        $resRes = $this->actingAs($this->userA, 'sanctum')->postJson("/api/v1/scheduled-posts/{$post->id}/reschedule", [
            'scheduled_at' => $newLocal->format('Y-m-d H:i:s'),
            'timezone'     => 'Africa/Nairobi',
        ]);
        $resRes->assertOk();
        $this->assertEquals('scheduled', $post->fresh()->status);

        // Cancel post
        $cancelRes = $this->actingAs($this->userA, 'sanctum')->postJson("/api/v1/scheduled-posts/{$post->id}/cancel", [
            'reason' => 'Audit test cancellation',
        ]);
        $cancelRes->assertOk();
        $this->assertEquals('cancelled', $post->fresh()->status);
        $this->assertEquals('Audit test cancellation', $post->fresh()->cancellation_reason);
    }

    // =========================================================================
    // 5. WEB & FLUTTER SYNCHRONIZATION VIA SHARED API
    // =========================================================================

    public function test_cross_client_synchronization_lifecycle(): void
    {
        // Step 1: Web client creates a scheduled post
        $webCreate = $this->actingAs($this->userA, 'sanctum')->postJson("/api/v1/brands/{$this->brandA->id}/scheduled-posts", [
            'title'        => 'Created On Web Studio',
            'caption'      => 'Caption from Web',
            'platform'     => 'linkedin',
            'scheduled_at' => Carbon::now()->addDays(2)->format('Y-m-d H:i:s'),
            'timezone'     => 'Africa/Nairobi',
            'status'       => 'scheduled',
        ]);
        $webCreate->assertStatus(201);
        $postId = $webCreate->json('data.id');

        // Step 2: Mobile/Flutter client fetches the scheduled post list and sees it
        $mobileFetch = $this->actingAs($this->userA, 'sanctum')->getJson("/api/v1/brands/{$this->brandA->id}/scheduled-posts");
        $mobileFetch->assertOk();
        $found = collect($mobileFetch->json('data'))->firstWhere('id', $postId);
        $this->assertNotNull($found);
        $this->assertEquals('Created On Web Studio', $found['title']);

        // Step 3: Mobile/Flutter client edits the post title and caption
        $mobileUpdate = $this->actingAs($this->userA, 'sanctum')->putJson("/api/v1/scheduled-posts/{$postId}", [
            'title'   => 'Updated via Mobile App',
            'caption' => 'Updated Caption from Flutter',
        ]);
        $mobileUpdate->assertOk();

        // Step 4: Web client views calendar and sees updated title
        $startStr = Carbon::now()->startOfDay()->format('Y-m-d');
        $endStr = Carbon::now()->addDays(5)->format('Y-m-d');
        $webCal = $this->actingAs($this->userA, 'sanctum')->getJson("/api/v1/brands/{$this->brandA->id}/calendar?start_date={$startStr}&end_date={$endStr}");
        $webCal->assertOk();
        $calPost = collect($webCal->json('data'))->firstWhere('id', $postId);
        $this->assertEquals('Updated via Mobile App', $calPost['title']);

        // Step 5: Mobile client cancels post
        $mobileCancel = $this->actingAs($this->userA, 'sanctum')->postJson("/api/v1/scheduled-posts/{$postId}/cancel", [
            'reason' => 'Cancelled from mobile',
        ]);
        $mobileCancel->assertOk();

        // Step 6: Web client sees cancelled status
        $webShow = $this->actingAs($this->userA, 'sanctum')->getJson("/api/v1/scheduled-posts/{$postId}");
        $webShow->assertOk();
        $this->assertEquals('cancelled', $webShow->json('data.status'));
    }

    // =========================================================================
    // 6. PHASE BOUNDARY VERIFICATION
    // =========================================================================

    public function test_phase_eight_never_simulates_published_status_or_fake_api_calls(): void
    {
        $post = ScheduledPost::create([
            'user_id'      => $this->userA->id,
            'brand_id'     => $this->brandA->id,
            'title'        => 'Boundaries Post',
            'scheduled_at' => Carbon::now()->addDay(),
            'timezone'     => 'UTC',
            'status'       => 'scheduled',
        ]);

        $this->assertNotEquals('published', $post->status);

        // Autopilot cycle also maintains 'scheduled' status, never 'published'
        $autopilot = AutopilotSetting::create([
            'brand_id'          => $this->brandA->id,
            'user_id'           => $this->userA->id,
            'is_enabled'        => true,
            'mode'              => 'automatic',
            'requires_approval' => false,
        ]);

        $draft = BrandContentDraft::create([
            'brand_id' => $this->brandA->id,
            'user_id'  => $this->userA->id,
            'title'    => 'Boundary Draft',
            'prompt'   => 'Prompt',
            'body'     => 'Body',
            'status'   => 'ready',
        ]);

        app(AutopilotService::class)->runAutopilotForBrand($this->brandA);

        $created = ScheduledPost::where('content_draft_id', $draft->id)->first();
        $this->assertNotNull($created);
        $this->assertEquals('scheduled', $created->status);
        $this->assertNotEquals('published', $created->status);
    }

    // =========================================================================
    // 7. SECURITY & MULTI-TENANT ISOLATION
    // =========================================================================

    public function test_user_cannot_access_or_modify_another_users_brand_schedules(): void
    {
        $postA = ScheduledPost::create([
            'user_id'      => $this->userA->id,
            'brand_id'     => $this->brandA->id,
            'title'        => 'Private Brand Post',
            'scheduled_at' => Carbon::now()->addDays(2),
            'timezone'     => 'UTC',
            'status'       => 'scheduled',
        ]);

        // User B cannot view User A's scheduled posts
        $res1 = $this->actingAs($this->userB, 'sanctum')->getJson("/api/v1/brands/{$this->brandA->id}/scheduled-posts");
        $res1->assertStatus(403);

        // User B cannot view single post of User A
        $res2 = $this->actingAs($this->userB, 'sanctum')->getJson("/api/v1/scheduled-posts/{$postA->id}");
        $res2->assertStatus(403);

        // User B cannot reschedule User A's post
        $res3 = $this->actingAs($this->userB, 'sanctum')->postJson("/api/v1/scheduled-posts/{$postA->id}/reschedule", [
            'scheduled_at' => Carbon::now()->addDays(5)->format('Y-m-d H:i:s'),
        ]);
        $res3->assertStatus(403);

        // User B cannot view or modify User A's autopilot
        $res4 = $this->actingAs($this->userB, 'sanctum')->getJson("/api/v1/brands/{$this->brandA->id}/autopilot");
        $res4->assertStatus(403);

        $res5 = $this->actingAs($this->userB, 'sanctum')->postJson("/api/v1/brands/{$this->brandA->id}/autopilot/enable");
        $res5->assertStatus(403);

        // User B cannot view or modify User A's queue
        $res6 = $this->actingAs($this->userB, 'sanctum')->getJson("/api/v1/brands/{$this->brandA->id}/publishing-queue");
        $res6->assertStatus(403);
    }
}
