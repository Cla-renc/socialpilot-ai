<?php

namespace App\Services\Social\Providers;

use App\Models\SocialAccount;
use App\Services\Social\AbstractSocialProvider;
use App\Services\Social\ProviderException;

class TikTokSocialProvider extends AbstractSocialProvider
{
    public function key(): string
    {
        return 'tiktok';
    }

    public function name(): string
    {
        return 'TikTok';
    }

    public function authorizationUrl(string $state): string
    {
        $settings = $this->requireConfigured();

        return $this->authorizationUrlFor('https://www.tiktok.com/v2/auth/authorize/', [
            'client_key' => $settings['client_id'],
            'redirect_uri' => $settings['redirect'],
            'response_type' => 'code',
            'scope' => implode(',', $settings['scopes'] ?? ['user.info.basic']),
            'state' => $state,
        ]);
    }

    public function exchangeCode(string $code): array
    {
        $settings = $this->requireConfigured();
        $response = $this->postForm('https://open.tiktokapis.com/v2/oauth/token/', [
            'client_key' => $settings['client_id'],
            'client_secret' => $settings['client_secret'],
            'code' => $code,
            'grant_type' => 'authorization_code',
            'redirect_uri' => $settings['redirect'],
        ]);

        return $this->token($response['data'] ?? $response);
    }

    public function accounts(string $accessToken): array
    {
        $response = $this->get('https://open.tiktokapis.com/v2/user/info/', [
            'fields' => 'open_id,display_name,avatar_url,username',
        ], $accessToken);
        $user = $response['data']['user'] ?? null;
        if (! is_array($user) || ! isset($user['open_id'])) {
            throw new ProviderException('TikTok did not return an account profile.');
        }

        $token = $this->token(['access_token' => $accessToken]);

        return [[
            'id' => (string) $user['open_id'],
            'name' => (string) ($user['display_name'] ?? $user['username'] ?? 'TikTok account'),
            'username' => is_string($user['username'] ?? null) ? $user['username'] : null,
            'account_type' => 'creator',
            'access_token' => $token['access_token'],
            'refresh_token' => null,
            'expires_in' => null,
            'scopes' => [],
            'metadata' => ['avatar_url' => $user['avatar_url'] ?? null],
        ]];
    }

    public function refresh(string $refreshToken): ?array
    {
        $settings = $this->requireConfigured();

        return $this->token($this->postForm('https://open.tiktokapis.com/v2/oauth/token/', [
            'client_key' => $settings['client_id'],
            'client_secret' => $settings['client_secret'],
            'grant_type' => 'refresh_token',
            'refresh_token' => $refreshToken,
        ])['data'] ?? []);
    }

    public function isValid(SocialAccount $account, string $accessToken): bool
    {
        $response = $this->get('https://open.tiktokapis.com/v2/user/info/', [
            'fields' => 'open_id',
        ], $accessToken);

        return (string) ($response['data']['user']['open_id'] ?? '') === (string) $account->provider_account_id;
    }

    public function revoke(string $accessToken): bool
    {
        return $this->jsonRevoke('https://open.tiktokapis.com/v2/oauth/revoke/', [
            'client_key' => $this->settings()['client_id'] ?? '',
            'client_secret' => $this->settings()['client_secret'] ?? '',
            'token' => $accessToken,
        ]);
    }
}
