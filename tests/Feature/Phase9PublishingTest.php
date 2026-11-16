<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\PublishingAttempt;
use App\Models\PublishingNotification;
use App\Models\ScheduledPost;
use App\Models\SocialAccount;
use App\Models\User;
use App\Services\Publishing\PublishingManager;
use App\Services\Publishing\PublishingStatusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase9PublishingTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Brand $brand;
    private SocialAccount $account;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->brand = Brand::factory()->create(['user_id' => $this->user->id]);
        $this->account = SocialAccount::create([
            'user_id' => $this->user->id,
            'brand_id' => $this->brand->id,
            'provider' => 'facebook',
            'provider_account_id' => 'fb_123456',
            'display_name' => 'Test FB Page',
            'account_type' => 'page',
            'status' => 'connected',
        ]);
    }

    public function test_api_publish_now_creates_attempt(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')->postJson('/api/v1/publishing/publish-now', [
            'brand_id' => $this->brand->id,
            'social_account_id' => $this->account->id,
            'content' => 'Test live publish content from test suite',
            'media_urls' => ['https://example.com/test.jpg'],
        ]);

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'data' => [
                'id',
                'platform',
                'status',
                'created_at',
            ]
        ]);

        $this->assertDatabaseHas('publishing_attempts', [
            'brand_id' => $this->brand->id,
            'social_account_id' => $this->account->id,
            'platform' => 'facebook',
        ]);
    }

    public function test_api_publish_now_denies_unauthorized_user(): void
    {
        $otherUser = User::factory()->create();

        $response = $this->actingAs($otherUser, 'sanctum')->postJson('/api/v1/publishing/publish-now', [
            'brand_id' => $this->brand->id,
            'social_account_id' => $this->account->id,
            'content' => 'Unauthorized attempt',
        ]);

        $response->assertStatus(403);
    }

    public function test_api_attempts_list_paginated_for_authenticated_user(): void
    {
        for ($i = 0; $i < 3; $i++) {
            PublishingAttempt::create([
                'user_id' => $this->user->id,
                'brand_id' => $this->brand->id,
                'social_account_id' => $this->account->id,
                'platform' => 'facebook',
                'status' => 'published',
                'idempotency_key' => "key_test_att_{$i}",
            ]);
        }

        $response = $this->actingAs($this->user, 'sanctum')->getJson('/api/v1/publishing/attempts');

        $response->assertStatus(200);
        $response->assertJsonCount(3, 'data');
    }

    public function test_api_attempt_detail_owner_and_forbidden_checks(): void
    {
        $attempt = PublishingAttempt::create([
            'user_id' => $this->user->id,
            'brand_id' => $this->brand->id,
            'social_account_id' => $this->account->id,
            'platform' => 'facebook',
            'status' => 'failed',
            'is_retryable' => true,
            'attempt_number' => 1,
            'idempotency_key' => 'attempt_detail_test_key_1',
            'error_message' => 'Simulated test error',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')->getJson("/api/v1/publishing/attempts/{$attempt->id}");
        $response->assertStatus(200);
        $response->assertJsonPath('data.id', $attempt->id);

        $otherUser = User::factory()->create();
        $forbiddenResponse = $this->actingAs($otherUser, 'sanctum')->getJson("/api/v1/publishing/attempts/{$attempt->id}");
        $forbiddenResponse->assertStatus(403);
    }

    public function test_api_retry_failed_publishing_attempt(): void
    {
        $attempt = PublishingAttempt::create([
            'user_id' => $this->user->id,
            'brand_id' => $this->brand->id,
            'social_account_id' => $this->account->id,
            'platform' => 'facebook',
            'status' => 'failed',
            'is_retryable' => true,
            'attempt_number' => 1,
            'idempotency_key' => 'retry_test_key_123',
            'error_message' => 'Network error',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')->postJson("/api/v1/publishing/attempts/{$attempt->id}/retry");
        $response->assertStatus(200);
        $response->assertJsonPath('message', 'Retry queued.');
    }

    public function test_api_cancel_pending_publishing_attempt(): void
    {
        $attempt = PublishingAttempt::create([
            'user_id' => $this->user->id,
            'brand_id' => $this->brand->id,
            'social_account_id' => $this->account->id,
            'platform' => 'facebook',
            'status' => 'pending',
            'is_retryable' => false,
            'attempt_number' => 1,
            'idempotency_key' => 'cancel_test_key_456',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')->postJson("/api/v1/publishing/attempts/{$attempt->id}/cancel");
        $response->assertStatus(200);
        $this->assertEquals('cancelled', $attempt->fresh()->status);
    }

    public function test_api_statistics_and_history(): void
    {
        PublishingAttempt::create([
            'user_id' => $this->user->id,
            'brand_id' => $this->brand->id,
            'social_account_id' => $this->account->id,
            'platform' => 'facebook',
            'status' => 'published',
            'is_retryable' => false,
            'attempt_number' => 1,
            'idempotency_key' => 'stat_test_pub_1',
            'published_at' => now(),
        ]);

        PublishingAttempt::create([
            'user_id' => $this->user->id,
            'brand_id' => $this->brand->id,
            'social_account_id' => $this->account->id,
            'platform' => 'facebook',
            'status' => 'failed',
            'is_retryable' => true,
            'attempt_number' => 1,
            'idempotency_key' => 'stat_test_fail_1',
        ]);

        $statsResponse = $this->actingAs($this->user, 'sanctum')->getJson('/api/v1/publishing/statistics');
        $statsResponse->assertStatus(200);
        $statsResponse->assertJsonPath('data.total', 2);
        $statsResponse->assertJsonPath('data.published', 1);
        $statsResponse->assertJsonPath('data.failed', 1);

        $historyResponse = $this->actingAs($this->user, 'sanctum')->getJson('/api/v1/publishing/history');
        $historyResponse->assertStatus(200);
        $historyResponse->assertJsonCount(1, 'data');
    }

    public function test_publishing_status_service_computes_partial_and_full_published(): void
    {
        $statusService = app(PublishingStatusService::class);

        $scheduledPost = ScheduledPost::create([
            'brand_id' => $this->brand->id,
            'user_id' => $this->user->id,
            'title' => 'Multi platform scheduled post',
            'body' => 'Post content for multiple channels',
            'status' => 'publishing',
            'scheduled_at' => now(),
        ]);

        $account2 = SocialAccount::create([
            'user_id' => $this->user->id,
            'brand_id' => $this->brand->id,
            'provider' => 'instagram',
            'provider_account_id' => 'ig_123456',
            'display_name' => 'Test IG Account',
            'account_type' => 'business',
            'status' => 'connected',
        ]);

        $attempt1 = PublishingAttempt::create([
            'user_id' => $this->user->id,
            'brand_id' => $this->brand->id,
            'scheduled_post_id' => $scheduledPost->id,
            'social_account_id' => $this->account->id,
            'platform' => 'facebook',
            'status' => 'published',
            'is_retryable' => false,
            'attempt_number' => 1,
            'idempotency_key' => 'multi_p_1',
            'published_at' => now(),
        ]);

        $attempt2 = PublishingAttempt::create([
            'user_id' => $this->user->id,
            'brand_id' => $this->brand->id,
            'scheduled_post_id' => $scheduledPost->id,
            'social_account_id' => $account2->id,
            'platform' => 'instagram',
            'status' => 'failed',
            'is_retryable' => true,
            'attempt_number' => 1,
            'idempotency_key' => 'multi_p_2',
        ]);

        $overall = $statusService->computeAndUpdateOverallPostStatus($scheduledPost);
        $this->assertEquals('partially_published', $overall);
        $this->assertEquals('partially_published', $scheduledPost->fresh()->status);

        // When second attempt is retried and succeeds:
        $attempt2->update(['status' => 'published']);
        $overall2 = $statusService->computeAndUpdateOverallPostStatus($scheduledPost);
        $this->assertEquals('published', $overall2);
        $this->assertEquals('published', $scheduledPost->fresh()->status);
    }

    public function test_publishing_notifications_flow(): void
    {
        $notification = PublishingNotification::create([
            'user_id' => $this->user->id,
            'brand_id' => $this->brand->id,
            'notification_type' => 'success',
            'platform' => 'facebook',
            'title' => 'Post Published',
            'message' => 'Your post was successfully published to Facebook.',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')->getJson('/api/v1/publishing/notifications');
        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');

        $unreadResponse = $this->actingAs($this->user, 'sanctum')->getJson('/api/v1/publishing/notifications/unread-count');
        $unreadResponse->assertStatus(200);
        $unreadResponse->assertJsonPath('data.unread_count', 1);

        $markReadResponse = $this->actingAs($this->user, 'sanctum')->postJson("/api/v1/publishing/notifications/{$notification->id}/read");
        $markReadResponse->assertStatus(200);
        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_all_five_platform_providers_registered_in_publishing_manager(): void
    {
        $manager = app(PublishingManager::class);

        $platforms = ['facebook', 'instagram', 'tiktok', 'youtube', 'linkedin'];
        foreach ($platforms as $platform) {
            $provider = $manager->providerFor($platform);
            $this->assertNotNull($provider);
            $this->assertEquals($platform, $provider->platform());
        }
    }
}
