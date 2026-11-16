<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\PublishingAttempt;
use App\Models\PublishingNotification;
use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublishingStudioWebTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Brand $brand;
    protected SocialAccount $socialAccount;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->brand = Brand::factory()->create(['user_id' => $this->user->id]);
        $this->socialAccount = SocialAccount::create([
            'user_id' => $this->user->id,
            'brand_id' => $this->brand->id,
            'provider' => 'facebook',
            'provider_account_id' => 'fb_acc_123',
            'display_name' => 'Test FB Account',
            'status' => 'connected',
        ]);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get(route('publishing.dashboard'));
        $response->assertRedirect(route('login'));
    }

    public function test_publishing_dashboard_renders_successfully(): void
    {
        PublishingAttempt::create([
            'user_id' => $this->user->id,
            'brand_id' => $this->brand->id,
            'social_account_id' => $this->socialAccount->id,
            'platform' => 'facebook',
            'status' => 'published',
            'external_post_url' => 'https://facebook.com/posts/12345',
        ]);

        $response = $this->actingAs($this->user)->get(route('publishing.dashboard'));
        $response->assertOk();
        $response->assertSee('Publishing Studio');
        $response->assertSee('Total Posts');
        $response->assertSee('Published');
        $response->assertSee('Failed');
        $response->assertSee('Processing');
        $response->assertSee('https://facebook.com/posts/12345');
    }

    public function test_publishing_history_renders_successfully(): void
    {
        PublishingAttempt::create([
            'user_id' => $this->user->id,
            'brand_id' => $this->brand->id,
            'social_account_id' => $this->socialAccount->id,
            'platform' => 'instagram',
            'status' => 'published',
            'attempt_number' => 1,
            'external_post_url' => 'https://instagram.com/p/abc123xyz',
        ]);

        $response = $this->actingAs($this->user)->get(route('publishing.history'));
        $response->assertOk();
        $response->assertSee('Publishing History');
        $response->assertSee('instagram');
        $response->assertSee('https://instagram.com/p/abc123xyz');
    }

    public function test_publishing_failed_renders_successfully(): void
    {
        PublishingAttempt::create([
            'user_id' => $this->user->id,
            'brand_id' => $this->brand->id,
            'social_account_id' => $this->socialAccount->id,
            'platform' => 'tiktok',
            'status' => 'failed',
            'error_code' => 'RATE_LIMIT_EXCEEDED',
            'error_message' => 'Too many publishing requests in 1 minute',
            'is_retryable' => true,
        ]);

        $response = $this->actingAs($this->user)->get(route('publishing.failed'));
        $response->assertOk();
        $response->assertSee('Failed Publishing Attempts');
        $response->assertSee('RATE_LIMIT_EXCEEDED');
        $response->assertSee('Too many publishing requests in 1 minute');
        $response->assertSee('Retry');
    }

    public function test_publishing_publish_now_renders_successfully(): void
    {
        $response = $this->actingAs($this->user)->get(route('publishing.publish-now'));
        $response->assertOk();
        $response->assertSee('Publish Now');
        $response->assertSee('Post Content');
        $response->assertSee($this->brand->name);
    }

    public function test_publishing_notifications_renders_successfully(): void
    {
        PublishingNotification::create([
            'user_id' => $this->user->id,
            'brand_id' => $this->brand->id,
            'notification_type' => 'success',
            'platform' => 'linkedin',
            'title' => 'Post Published Successfully',
            'message' => 'Your LinkedIn post was published without issues.',
        ]);

        $response = $this->actingAs($this->user)->get(route('publishing.notifications'));
        $response->assertOk();
        $response->assertSee('Publishing Notifications');
        $response->assertSee('Post Published Successfully');
        $response->assertSee('Your LinkedIn post was published without issues.');
    }

    public function test_publishing_show_renders_for_owner(): void
    {
        $attempt = PublishingAttempt::create([
            'user_id' => $this->user->id,
            'brand_id' => $this->brand->id,
            'social_account_id' => $this->socialAccount->id,
            'platform' => 'youtube',
            'status' => 'published',
            'external_post_id' => 'yt_video_9999',
            'external_post_url' => 'https://youtube.com/watch?v=9999',
        ]);

        $response = $this->actingAs($this->user)->get(route('publishing.show', $attempt));
        $response->assertOk();
        $response->assertSee('Publishing Attempt Details');
        $response->assertSee('yt_video_9999');
        $response->assertSee('https://youtube.com/watch?v=9999');
    }

    public function test_publishing_show_denies_access_to_other_users(): void
    {
        $otherUser = User::factory()->create();
        $otherAccount = SocialAccount::create([
            'user_id' => $otherUser->id,
            'provider' => 'facebook',
            'provider_account_id' => 'fb_acc_other',
            'display_name' => 'Other FB Account',
            'status' => 'connected',
        ]);
        $attempt = PublishingAttempt::create([
            'user_id' => $otherUser->id,
            'brand_id' => $this->brand->id,
            'social_account_id' => $otherAccount->id,
            'platform' => 'facebook',
            'status' => 'published',
        ]);

        $response = $this->actingAs($this->user)->get(route('publishing.show', $attempt));
        $response->assertForbidden();
    }

    public function test_navigation_contains_publishing_link(): void
    {
        $response = $this->actingAs($this->user)->get(route('dashboard'));
        $response->assertOk();
        $response->assertSee(route('publishing.dashboard'));
        $response->assertSee('Publishing');
    }
}
