<?php

namespace Tests\Feature;

use App\Models\AutopilotActivityLog;
use App\Models\AutopilotSetting;
use App\Models\Brand;
use App\Models\BrandContentDraft;
use App\Models\PublishingQueue;
use App\Models\ScheduledPost;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SchedulingStudioWebTest extends TestCase
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

    public function test_scheduling_dashboard_renders_successfully(): void
    {
        $response = $this->actingAs($this->user)->get(route('scheduling.dashboard'));
        $response->assertOk();
        $response->assertSee('Scheduling Studio');
        $response->assertSee('Scheduled Posts');
        $response->assertSee('Autopilot Active');
    }

    public function test_calendar_page_renders_successfully(): void
    {
        $response = $this->actingAs($this->user)->get(route('scheduling.calendar'));
        $response->assertOk();
        $response->assertSee('Social Media Calendar');
        $response->assertSee($this->brand->name);
    }

    public function test_schedule_post_page_renders_for_owner(): void
    {
        BrandContentDraft::create([
            'brand_id' => $this->brand->id,
            'user_id'  => $this->user->id,
            'title'    => 'Draft For Web Schedule',
            'prompt'   => 'A prompt',
            'body'     => 'Draft body for scheduling',
            'status'   => 'draft',
        ]);

        $response = $this->actingAs($this->user)->get(route('brands.scheduling.schedule', $this->brand));
        $response->assertOk();
        $response->assertSee('Schedule Content for');
        $response->assertSee('Draft For Web Schedule');
    }

    public function test_best_times_page_renders_for_owner(): void
    {
        $response = $this->actingAs($this->user)->get(route('brands.scheduling.best-times', $this->brand));
        $response->assertOk();
        $response->assertSee('Best Time to Post Recommendations');
    }

    public function test_publishing_queue_page_renders_for_owner(): void
    {
        $queue = PublishingQueue::create([
            'brand_id' => $this->brand->id,
            'user_id'  => $this->user->id,
            'name'     => 'Instagram Daily Queue',
            'status'   => 'active',
        ]);

        $response = $this->actingAs($this->user)->get(route('brands.scheduling.queue', $this->brand));
        $response->assertOk();
        $response->assertSee('Publishing Queue for');
        $response->assertSee('Instagram Daily Queue');
    }

    public function test_autopilot_page_renders_for_owner(): void
    {
        AutopilotSetting::create([
            'brand_id'   => $this->brand->id,
            'user_id'    => $this->user->id,
            'is_enabled' => true,
            'mode'       => 'ai_assisted',
        ]);

        $response = $this->actingAs($this->user)->get(route('brands.scheduling.autopilot', $this->brand));
        $response->assertOk();
        $response->assertSee('Autopilot Studio for');
        $response->assertSee('ACTIVE');
    }

    public function test_activity_log_page_renders_for_owner(): void
    {
        AutopilotActivityLog::create([
            'brand_id'   => $this->brand->id,
            'user_id'    => $this->user->id,
            'action'     => 'autopilot_enabled',
            'status'     => 'success',
            'details'    => ['message' => 'Autopilot enabled for brand'],
        ]);

        $response = $this->actingAs($this->user)->get(route('brands.scheduling.activity', $this->brand));
        $response->assertOk();
        $response->assertSee('Autopilot Activity Log for');
        $response->assertSee('Autopilot Enabled');
    }

    public function test_foreign_user_cannot_view_scheduling_pages_of_another_brand(): void
    {
        $otherUser = User::factory()->create();
        $response = $this->actingAs($otherUser)->get(route('brands.scheduling.schedule', $this->brand));
        $response->assertStatus(403);
    }
}
