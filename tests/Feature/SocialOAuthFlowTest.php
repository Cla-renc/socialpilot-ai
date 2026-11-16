<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\SocialAccount;
use App\Models\User;
use App\Services\Social\SocialOAuthService;
use App\Services\Social\SocialProviderManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SocialOAuthFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_four_provider_adapters_build_authorization_urls(): void
    {
        foreach (['facebook', 'instagram', 'tiktok', 'youtube'] as $provider) {
            config([
                "services.social.{$provider}.client_id" => "{$provider}-client",
                "services.social.{$provider}.client_secret" => "{$provider}-secret",
                "services.social.{$provider}.redirect" => "https://socialpilot.test/social/oauth/{$provider}/callback",
            ]);
        }

        $adapters = app(SocialProviderManager::class);
        foreach (['facebook', 'instagram', 'tiktok', 'youtube'] as $provider) {
            $adapter = $adapters->provider($provider);
            $this->assertTrue($adapter->configured());
            $this->assertStringContainsString('state=state-token', $adapter->authorizationUrl('state-token'));
        }
    }

    public function test_brand_social_account_screen_shows_provider_readiness_and_account_columns(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $brand = Brand::factory()->create(['user_id' => $user->id]);
        foreach (['facebook', 'instagram', 'tiktok', 'youtube'] as $provider) {
            config([
                "services.social.{$provider}.client_id" => null,
                "services.social.{$provider}.client_secret" => null,
            ]);
        }

        $this->actingAs($user)
            ->get(route('brands.social-accounts.index', $brand))
            ->assertOk()
            ->assertSee('Facebook Pages')
            ->assertSee('Instagram Professional')
            ->assertSee('TikTok')
            ->assertSee('YouTube')
            ->assertSee('Configuration required')
            ->assertSee('Accounts associated with '.$brand->name);
    }

    public function test_facebook_callback_creates_an_encrypted_owner_scoped_account(): void
    {
        $user = User::factory()->create();
        $brand = Brand::factory()->create(['user_id' => $user->id]);
        config([
            'services.social.facebook.client_id' => 'facebook-client',
            'services.social.facebook.client_secret' => 'facebook-secret',
            'services.social.facebook.redirect' => route('social.oauth.callback', 'facebook'),
        ]);
        Http::fake(function ($request) {
            if (str_contains($request->url(), '/oauth/access_token')) {
                return Http::response([
                    'access_token' => 'oauth-user-token',
                    'expires_in' => 5184000,
                ]);
            }
            if (str_contains($request->url(), '/me/accounts')) {
                return Http::response([
                    'data' => [[
                        'id' => 'page-100',
                        'name' => 'Example Page',
                        'access_token' => 'page-access-token',
                    ]],
                ]);
            }

            return Http::response([], 404);
        });

        $start = $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/brands/{$brand->id}/social-providers/facebook/authorize")
            ->assertOk();
        $authorizationUrl = $start->json('data.authorization_url');
        parse_str((string) parse_url($authorizationUrl, PHP_URL_QUERY), $query);
        $state = $query['state'];

        $this->get(route('social.oauth.callback', 'facebook').'?'.http_build_query([
            'state' => $state,
            'code' => 'authorized-code',
        ]))->assertRedirect('socialpilot://oauth/callback?'.http_build_query([
            'status' => 'connected',
            'brand_id' => $brand->id,
            'message' => 'Social account connected.',
        ], '', '&', PHP_QUERY_RFC3986));

        $account = SocialAccount::query()->where('provider_account_id', 'page-100')->firstOrFail();
        $this->assertSame($user->id, $account->user_id);
        $this->assertSame($brand->id, $account->brand_id);
        $this->assertSame('Example Page', $account->display_name);
        $this->assertSame('page-access-token', $account->token->access_token);
        $this->assertNotSame('page-access-token', $account->token->getRawOriginal('access_token'));

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/brands/{$brand->id}/social-accounts")
            ->assertOk()
            ->assertJsonPath('data.0.provider_account_id', 'page-100')
            ->assertJsonMissingPath('data.0.access_token')
            ->assertJsonMissingPath('data.0.refresh_token');
    }

    public function test_oauth_state_is_one_time_and_cannot_be_used_for_another_provider(): void
    {
        $user = User::factory()->create();
        $brand = Brand::factory()->create(['user_id' => $user->id]);
        config([
            'services.social.facebook.client_id' => 'facebook-client',
            'services.social.facebook.client_secret' => 'facebook-secret',
            'services.social.facebook.redirect' => route('social.oauth.callback', 'facebook'),
        ]);

        $start = $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/brands/{$brand->id}/social-providers/facebook/authorize")
            ->assertOk();
        parse_str((string) parse_url($start->json('data.authorization_url'), PHP_URL_QUERY), $query);
        $callback = '/social/oauth/facebook/callback?'.http_build_query([
            'state' => $query['state'],
            'code' => 'unused-code',
        ]);

        $this->getJson($callback)->assertRedirect();
        $this->getJson($callback)->assertBadRequest();
        $this->assertDatabaseCount('social_accounts', 0);
    }

    public function test_instagram_tiktok_and_youtube_adapters_discover_real_account_profiles(): void
    {
        Http::fake(function ($request) {
            if (str_contains($request->url(), '/me/accounts')) {
                return Http::response(['data' => [[
                    'id' => 'page-200',
                    'name' => 'Instagram Page',
                    'access_token' => 'page-token',
                    'instagram_business_account' => [
                        'id' => 'ig-200',
                        'username' => 'studio',
                    ],
                ]]]);
            }
            if (str_contains($request->url(), '/v2/user/info/')) {
                return Http::response([
                    'data' => ['user' => [
                        'open_id' => 'tt-200',
                        'display_name' => 'TikTok Studio',
                        'username' => 'studio',
                    ]],
                    'error' => ['code' => 'ok', 'message' => ''],
                ]);
            }
            if (str_contains($request->url(), '/youtube/v3/channels')) {
                return Http::response(['items' => [[
                    'id' => 'yt-200',
                    'snippet' => ['title' => 'Studio Channel'],
                ]]]);
            }

            return Http::response([], 404);
        });

        $providers = app(SocialProviderManager::class);
        $instagram = $providers->provider('instagram')->accounts('meta-user-token');
        $tiktok = $providers->provider('tiktok')->accounts('tiktok-access-token');
        $youtube = $providers->provider('youtube')->accounts('google-access-token');

        $this->assertSame('ig-200', $instagram[0]['id']);
        $this->assertSame('studio', $instagram[0]['username']);
        $this->assertSame('tt-200', $tiktok[0]['id']);
        $this->assertSame('studio', $tiktok[0]['username']);
        $this->assertSame('yt-200', $youtube[0]['id']);
        $this->assertSame('Studio Channel', $youtube[0]['name']);
    }

    public function test_meta_and_youtube_adapters_follow_account_pagination(): void
    {
        Http::fake(function ($request) {
            if (str_contains($request->url(), '/me/accounts')) {
                if ($request['after'] ?? false) {
                    return Http::response(['data' => [[
                        'id' => 'page-2',
                        'name' => 'Second Page',
                        'access_token' => 'page-token-2',
                    ]]]);
                }

                return Http::response([
                    'data' => [[
                        'id' => 'page-1',
                        'name' => 'First Page',
                        'access_token' => 'page-token-1',
                    ]],
                    'paging' => [
                        'next' => 'https://graph.facebook.com/next',
                        'cursors' => ['after' => 'next-cursor'],
                    ],
                ]);
            }
            if (str_contains($request->url(), '/youtube/v3/channels')) {
                if (isset($request['pageToken'])) {
                    return Http::response(['items' => [[
                        'id' => 'channel-2',
                        'snippet' => ['title' => 'Second Channel'],
                    ]]]);
                }

                return Http::response([
                    'items' => [[
                        'id' => 'channel-1',
                        'snippet' => ['title' => 'First Channel'],
                    ]],
                    'nextPageToken' => 'next-page',
                ]);
            }

            return Http::response([], 404);
        });

        $providers = app(SocialProviderManager::class);
        $pages = $providers->provider('facebook')->accounts('meta-user-token');
        $channels = $providers->provider('youtube')->accounts('google-access-token');

        $this->assertCount(2, $pages);
        $this->assertCount(2, $channels);
        $this->assertSame('page-2', $pages[1]['id']);
        $this->assertSame('channel-2', $channels[1]['id']);
    }

    public function test_expired_tiktok_token_refreshes_before_validity_check(): void
    {
        $user = User::factory()->create();
        $brand = Brand::factory()->create(['user_id' => $user->id]);
        $account = SocialAccount::query()->create([
            'user_id' => $user->id,
            'brand_id' => $brand->id,
            'provider' => 'tiktok',
            'provider_account_id' => 'tt-300',
            'display_name' => 'TikTok Studio',
            'status' => 'token_expired',
        ]);
        $account->token()->create([
            'provider' => 'tiktok',
            'access_token' => 'expired-token',
            'refresh_token' => 'valid-refresh-token',
            'expires_at' => now()->subMinute(),
        ]);
        config([
            'services.social.tiktok.client_id' => 'tiktok-key',
            'services.social.tiktok.client_secret' => 'tiktok-secret',
            'services.social.tiktok.redirect' => 'https://socialpilot.test/social/oauth/tiktok/callback',
        ]);
        Http::fake(function ($request) {
            if (str_contains($request->url(), '/v2/oauth/token/')) {
                return Http::response(['data' => [
                    'access_token' => 'fresh-access-token',
                    'refresh_token' => 'fresh-refresh-token',
                    'expires_in' => 3600,
                ]]);
            }
            if (str_contains($request->url(), '/v2/user/info/')) {
                return Http::response([
                    'data' => ['user' => ['open_id' => 'tt-300']],
                    'error' => ['code' => 'ok', 'message' => ''],
                ]);
            }

            return Http::response([], 404);
        });

        $result = app(SocialOAuthService::class)->checkOrRefresh($user, $account);

        $this->assertSame('connected', $result['status']);
        $this->assertSame('fresh-access-token', $account->fresh()->token->access_token);
        $this->assertSame('fresh-refresh-token', $account->fresh()->token->refresh_token);
        $this->assertTrue($account->fresh()->token->expires_at->isFuture());
    }

    public function test_provider_account_already_owned_by_another_user_is_never_reassigned(): void
    {
        $firstUser = User::factory()->create();
        $firstBrand = Brand::factory()->create(['user_id' => $firstUser->id]);
        $otherUser = User::factory()->create();
        $otherBrand = Brand::factory()->create(['user_id' => $otherUser->id]);
        $existing = SocialAccount::query()->create([
            'user_id' => $otherUser->id,
            'brand_id' => $otherBrand->id,
            'provider' => 'facebook',
            'provider_account_id' => 'page-locked',
            'display_name' => 'Private Page',
            'status' => 'connected',
        ]);
        $existing->token()->create([
            'provider' => 'facebook',
            'access_token' => 'original-private-token',
        ]);
        config([
            'services.social.facebook.client_id' => 'facebook-client',
            'services.social.facebook.client_secret' => 'facebook-secret',
            'services.social.facebook.redirect' => route('social.oauth.callback', 'facebook'),
        ]);
        Http::fake(function ($request) {
            if (str_contains($request->url(), '/oauth/access_token')) {
                return Http::response(['access_token' => 'new-user-token']);
            }
            if (str_contains($request->url(), '/me/accounts')) {
                return Http::response(['data' => [[
                    'id' => 'page-locked',
                    'name' => 'Private Page',
                    'access_token' => 'attacker-page-token',
                ]]]);
            }

            return Http::response([], 404);
        });

        $start = $this->actingAs($firstUser, 'sanctum')
            ->postJson("/api/v1/brands/{$firstBrand->id}/social-providers/facebook/authorize")
            ->assertOk();
        parse_str((string) parse_url($start->json('data.authorization_url'), PHP_URL_QUERY), $query);

        $this->get(route('social.oauth.callback', 'facebook').'?'.http_build_query([
            'state' => $query['state'],
            'code' => 'authorized-code',
        ]))->assertRedirectContains('status=error');

        $this->assertDatabaseCount('social_accounts', 1);
        $this->assertSame($otherUser->id, $existing->fresh()->user_id);
        $this->assertSame($otherBrand->id, $existing->fresh()->brand_id);
        $this->assertSame('original-private-token', $existing->fresh()->token->access_token);
    }

    public function test_disconnect_revokes_provider_token_and_is_owner_scoped(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $brand = Brand::factory()->create(['user_id' => $user->id]);
        $account = SocialAccount::query()->create([
            'user_id' => $user->id,
            'brand_id' => $brand->id,
            'provider' => 'youtube',
            'provider_account_id' => 'channel-disconnect',
            'display_name' => 'My Channel',
            'status' => 'connected',
        ]);
        $account->token()->create([
            'provider' => 'youtube',
            'access_token' => 'channel-access-token',
            'refresh_token' => 'channel-refresh-token',
        ]);
        config([
            'services.social.youtube.client_id' => 'youtube-client',
            'services.social.youtube.client_secret' => 'youtube-secret',
            'services.social.youtube.redirect' => 'https://socialpilot.test/social/oauth/youtube/callback',
        ]);
        Http::fake([
            'https://oauth2.googleapis.com/revoke' => Http::response([], 200),
        ]);

        $this->actingAs($otherUser, 'sanctum')
            ->deleteJson("/api/v1/social-accounts/{$account->id}")
            ->assertForbidden();
        $this->assertDatabaseHas('social_account_tokens', ['social_account_id' => $account->id]);

        $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/v1/social-accounts/{$account->id}")
            ->assertOk()
            ->assertJsonPath('provider_revoked', true);
        $this->assertDatabaseHas('social_accounts', [
            'id' => $account->id,
            'status' => 'disconnected',
        ]);
        $this->assertDatabaseMissing('social_account_tokens', ['social_account_id' => $account->id]);
    }
}
