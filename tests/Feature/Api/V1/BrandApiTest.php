<?php

namespace Tests\Feature\Api\V1;

use App\Models\Brand;
use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BrandApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_can_login_and_receive_a_token(): void
    {
        $user = User::factory()->create([
            'email' => 'api@example.com',
            'password' => 'password123',
        ]);

        $response = $this->postJson('/api/v1/login', [
            'email' => 'api@example.com',
            'password' => 'password123',
        ]);

        $response->assertOk()
            ->assertJsonPath('user.email', 'api@example.com')
            ->assertJsonPath('user.name', $user->name)
            ->assertJsonStructure([
                'message',
                'token',
                'user',
            ]);
    }

    public function test_mobile_registration_creates_a_user_and_returns_a_working_token(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Mobile Test User',
            'email' => 'mobile-test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'terms' => true,
        ]);

        $response->assertCreated()
            ->assertJsonStructure(['message', 'token', 'user' => ['id', 'name', 'email']])
            ->assertJsonPath('user.email', 'mobile-test@example.com');

        $this->assertDatabaseHas('users', [
            'email' => 'mobile-test@example.com',
        ]);

        $this->getJson('/api/v1/auth/user', [
            'Authorization' => 'Bearer '.$response->json('token'),
        ])->assertOk()
            ->assertJsonPath('user.email', 'mobile-test@example.com');
    }

    public function test_authenticated_user_can_list_their_brands_via_api(): void
    {
        $user = User::factory()->create();
        Brand::factory()->create([
            'user_id' => $user->id,
            'name' => 'Brand API One',
        ]);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/v1/brands');

        $response->assertOk()
            ->assertJsonPath('data.0.name', 'Brand API One');
    }

    public function test_authenticated_user_can_create_a_brand_via_api(): void
    {
        $user = User::factory()->create();

        $payload = [
            'name' => 'Campaign Studio',
            'description' => 'A new product brand for API testing.',
            'audience' => 'Marketing teams',
            'language' => 'en',
            'brand_voice' => 'friendly and concise',
            'content_formats' => 'blog,carousels',
            'brand_colours' => '#0ea5e9,#d946ef',
            'publishing_frequency' => 'weekly',
            'timezone' => 'UTC',
        ];

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/v1/brands', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'Campaign Studio')
            ->assertJsonPath('data.settings.brand_voice', 'friendly and concise');

        $this->assertDatabaseHas('brands', [
            'user_id' => $user->id,
            'name' => 'Campaign Studio',
        ]);
    }

    public function test_brand_profiles_and_settings_are_saved_and_searchable(): void
    {
        $user = User::factory()->create();

        $created = $this->actingAs($user, 'sanctum')->postJson('/api/v1/brands', [
            'name' => 'Northstar Studio',
            'industry' => 'Design',
            'niche' => 'Brand strategy',
            'website_url' => 'https://northstar.example',
            'audience' => 'Independent founders',
            'target_age_group' => '25-34',
            'audience_interests' => ['design', 'startups'],
            'region' => 'East Africa',
            'language' => 'en',
            'tone' => 'confident',
            'brand_personality' => ['helpful', 'bold'],
            'brand_values' => ['clarity'],
            'brand_keywords' => ['launch', 'growth'],
            'topics_to_avoid' => ['politics'],
            'marketing_objectives' => ['brand_awareness', 'engagement'],
            'content_preferences' => ['educational' => 5, 'community' => 3],
        ]);

        $created->assertCreated()
            ->assertJsonPath('data.industry', 'Design')
            ->assertJsonPath('data.niche', 'Brand strategy')
            ->assertJsonPath('data.audience_interests.0', 'design')
            ->assertJsonPath('data.settings.brand_personality.1', 'bold')
            ->assertJsonPath('data.settings.marketing_objectives.0', 'brand_awareness')
            ->assertJsonPath('data.settings.content_preferences.educational', 5);

        $brandId = $created->json('data.id');
        $this->putJson('/api/v1/brands/'.$brandId, [
            'name' => 'Northstar Studio',
            'website_url' => null,
            'status' => 'active',
        ])->assertOk()
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.website_url', null);

        $this->getJson('/api/v1/brands?search=Northstar&status=active&per_page=5')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.name', 'Northstar Studio');

        $this->getJson('/api/v1/brands/'.$brandId.'/overview')
            ->assertOk()
            ->assertJsonPath('data.brand_id', $brandId)
            ->assertJsonPath('data.connected_accounts', 0);
    }

    public function test_brand_and_social_account_endpoints_are_owner_scoped(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $brand = Brand::factory()->create(['user_id' => $owner->id]);

        $this->actingAs($otherUser, 'sanctum')
            ->getJson('/api/v1/brands/'.$brand->id)
            ->assertForbidden();

        $this->getJson('/api/v1/brands/'.$brand->id.'/social-accounts')
            ->assertForbidden();

        $this->getJson('/api/v1/brands')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_social_provider_status_and_failed_oauth_do_not_create_accounts(): void
    {
        // Null out social credentials so the provider is treated as not configured.
        Config::set('services.social.facebook.client_id', null);
        Config::set('services.social.facebook.client_secret', null);

        $user = User::factory()->create();
        $brand = Brand::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user, 'sanctum')->getJson('/api/v1/social-providers')
            ->assertOk()
            ->assertJsonPath('data.0.status', 'configuration_required')
            ->assertJsonPath('data.0.connection_available', false);

        $this->postJson('/api/v1/brands/'.$brand->id.'/social-providers/facebook/authorize')
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'configuration_required');

        $this->assertDatabaseCount('social_accounts', 0);
    }

    public function test_social_tokens_are_encrypted_at_rest_and_hidden_from_json(): void
    {
        $user = User::factory()->create();
        $brand = Brand::factory()->create(['user_id' => $user->id]);
        $account = SocialAccount::query()->create([
            'user_id' => $user->id,
            'brand_id' => $brand->id,
            'provider' => 'facebook',
            'provider_account_id' => 'page-001',
            'display_name' => 'Northstar Page',
            'status' => 'connected',
        ]);
        $account->token()->create([
            'provider' => 'facebook',
            'access_token' => 'verified-test-access-token',
            'refresh_token' => 'verified-test-refresh-token',
            'scopes' => ['pages_show_list'],
        ]);

        $storedToken = DB::table('social_account_tokens')
            ->where('social_account_id', $account->id)
            ->value('access_token');

        $this->assertNotSame('verified-test-access-token', $storedToken);
        $this->assertSame('verified-test-access-token', $account->fresh()->token->access_token);
        $this->assertArrayNotHasKey('access_token', $account->fresh()->token->toArray());
        $this->assertArrayNotHasKey('refresh_token', $account->fresh()->token->toArray());
    }

    public function test_social_account_status_exposes_expiry_without_exposing_tokens(): void
    {
        $user = User::factory()->create();
        $brand = Brand::factory()->create(['user_id' => $user->id]);
        $expiresAt = now()->subDay()->startOfSecond();
        $account = SocialAccount::query()->create([
            'user_id' => $user->id,
            'brand_id' => $brand->id,
            'provider' => 'facebook',
            'provider_account_id' => 'page-001',
            'display_name' => 'Northstar Page',
            'status' => 'connected',
            'metadata' => [
                'last_synced_at' => '2026-11-10T12:00:00Z',
                'access_token' => 'do-not-serialize-this-token',
                'provider_data' => ['refresh_token' => 'do-not-serialize-this-refresh-token'],
            ],
        ]);
        $account->token()->create([
            'provider' => 'facebook',
            'access_token' => 'verified-test-access-token',
            'refresh_token' => 'verified-test-refresh-token',
            'expires_at' => $expiresAt,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/brands/'.$brand->id.'/social-accounts')
            ->assertOk()
            ->assertJsonPath('data.0.status', 'token_expired')
            ->assertJsonPath('data.0.provider_account_id', 'page-001')
            ->assertJsonPath('data.0.last_synced_at', '2026-11-10T12:00:00Z')
            ->assertJsonPath('data.0.token_expires_at', $expiresAt->toISOString())
            ->assertJsonMissingPath('data.0.access_token')
            ->assertJsonMissingPath('data.0.refresh_token')
            ->assertJsonMissingPath('data.0.metadata.access_token')
            ->assertJsonMissingPath('data.0.metadata.provider_data.refresh_token');
        $this->assertStringNotContainsString('do-not-serialize-this-token', $response->getContent());
        $this->assertStringNotContainsString('do-not-serialize-this-refresh-token', $response->getContent());

        $this->getJson('/api/v1/brands')
            ->assertOk()
            ->assertJsonPath('data.0.connected_accounts_count', 0);
    }

    public function test_brand_compatibility_endpoints_activate_and_sort_owner_brands(): void
    {
        $user = User::factory()->create();
        $alpha = Brand::factory()->create([
            'user_id' => $user->id,
            'name' => 'Alpha',
            'status' => 'draft',
        ]);
        Brand::factory()->create(['user_id' => $user->id, 'name' => 'Zulu']);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/brands/'.$alpha->id.'/activate')
            ->assertOk()
            ->assertJsonPath('data.status', 'active');

        $this->getJson('/api/v1/brands?sort=name&direction=asc')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Alpha')
            ->assertJsonPath('data.1.name', 'Zulu');

        $this->putJson('/api/v1/brands/'.$alpha->id.'/settings', [
            'brand_voice' => 'Warm and direct',
        ])->assertOk()
            ->assertJsonPath('data.settings.brand_voice', 'Warm and direct');
    }

    public function test_social_account_status_alias_and_connect_route_preserve_ownership(): void
    {
        // Null out social credentials so the provider is treated as not configured.
        Config::set('services.social.facebook.client_id', null);
        Config::set('services.social.facebook.client_secret', null);
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $brand = Brand::factory()->create(['user_id' => $owner->id]);
        $account = SocialAccount::query()->create([
            'user_id' => $owner->id,
            'brand_id' => $brand->id,
            'provider' => 'facebook',
            'provider_account_id' => 'page-status-1',
            'display_name' => 'Status Page',
            'status' => 'connection_error',
        ]);

        $this->actingAs($owner, 'sanctum')
            ->getJson('/api/v1/social-accounts/'.$account->id.'/status')
            ->assertOk()
            ->assertJsonPath('data.status', 'connection_error')
            ->assertJsonPath('data.provider_account_id', 'page-status-1');

        $this->postJson('/api/v1/brands/'.$brand->id.'/social-accounts/connect', [
            'provider' => 'facebook',
        ])->assertStatus(409)
            ->assertJsonPath('error.code', 'configuration_required');

        $this->actingAs($otherUser, 'sanctum')
            ->getJson('/api/v1/social-accounts/'.$account->id.'/status')
            ->assertForbidden();

        $this->assertDatabaseCount('social_accounts', 1);
    }
}
