<?php

namespace App\Services\Social\Providers;

use App\Models\SocialAccount;
use App\Services\Social\AbstractSocialProvider;
use App\Services\Social\ProviderException;
use Illuminate\Support\Facades\Http;

class MetaSocialProvider extends AbstractSocialProvider
{
    public function __construct(private readonly string $provider) {}

    public function key(): string
    {
        return $this->provider;
    }

    public function name(): string
    {
        return $this->provider === 'facebook' ? 'Facebook Pages' : 'Instagram Professional';
    }

    public function authorizationUrl(string $state): string
    {
        $settings = $this->requireConfigured();

        return $this->authorizationUrlFor('https://www.facebook.com/'.($settings['graph_version'] ?? 'v22.0').'/dialog/oauth', [
            'client_id' => $settings['client_id'],
            'redirect_uri' => $settings['redirect'],
            'response_type' => 'code',
            'scope' => implode(',', $settings['scopes'] ?? ['pages_show_list', 'instagram_basic']),
            'state' => $state,
        ]);
    }

    public function exchangeCode(string $code): array
    {
        $settings = $this->requireConfigured();
        $base = 'https://graph.facebook.com/'.($settings['graph_version'] ?? 'v22.0').'/oauth/access_token';
        $token = $this->get($base, [
            'client_id' => $settings['client_id'],
            'client_secret' => $settings['client_secret'],
            'redirect_uri' => $settings['redirect'],
            'code' => $code,
        ]);

        $shortLived = $this->token($token);
        $longLived = $this->get($base, [
            'client_id' => $settings['client_id'],
            'client_secret' => $settings['client_secret'],
            'grant_type' => 'fb_exchange_token',
            'fb_exchange_token' => $shortLived['access_token'],
        ]);

        return $this->token($longLived);
    }

    public function accounts(string $accessToken): array
    {
        $settings = $this->settings();
        $version = $settings['graph_version'] ?? 'v22.0';
        $accounts = [];
        $after = null;
        $hasNextPage = true;

        while ($hasNextPage) {
            $query = [
                'fields' => 'id,name,access_token,instagram_business_account{id,username}',
                'limit' => 100,
            ];
            if ($after !== null) {
                $query['after'] = $after;
            }
            $pages = $this->get("https://graph.facebook.com/{$version}/me/accounts", $query, $accessToken);

            foreach ($pages['data'] ?? [] as $page) {
                if (! is_array($page) || ! isset($page['id'], $page['name'], $page['access_token'])) {
                    continue;
                }

                if ($this->provider === 'facebook') {
                    $accounts[] = [
                        'id' => (string) $page['id'],
                        'name' => (string) $page['name'],
                        'username' => null,
                        'account_type' => 'page',
                        'access_token' => (string) $page['access_token'],
                        'refresh_token' => null,
                        'expires_in' => null,
                        'scopes' => [],
                        'metadata' => ['platform' => 'facebook'],
                    ];

                    continue;
                }

                $instagram = $page['instagram_business_account'] ?? null;
                if (! is_array($instagram) || ! isset($instagram['id'])) {
                    continue;
                }

                $username = $instagram['username'] ?? null;
                $accounts[] = [
                    'id' => (string) $instagram['id'],
                    'name' => is_string($username) && $username !== '' ? $username : (string) $page['name'],
                    'username' => is_string($username) ? $username : null,
                    'account_type' => 'professional',
                    'access_token' => (string) $page['access_token'],
                    'refresh_token' => null,
                    'expires_in' => null,
                    'scopes' => [],
                    'metadata' => ['platform' => 'instagram', 'facebook_page_id' => (string) $page['id']],
                ];
            }

            $nextAfter = $pages['paging']['cursors']['after'] ?? null;
            $hasNextPage = isset($pages['paging']['next'])
                && is_string($nextAfter)
                && $nextAfter !== ''
                && $nextAfter !== $after;
            $after = $hasNextPage ? $nextAfter : null;
        }

        if ($accounts === []) {
            throw new ProviderException($this->provider === 'instagram'
                ? 'No Instagram Professional account is linked to the authorized Facebook Pages.'
                : 'No Facebook Pages were available for the authorized account.');
        }

        return $accounts;
    }

    public function refresh(string $refreshToken): ?array
    {
        return null;
    }

    public function isValid(SocialAccount $account, string $accessToken): bool
    {
        $version = $this->settings()['graph_version'] ?? 'v22.0';
        $profile = $this->get("https://graph.facebook.com/{$version}/{$account->provider_account_id}", [
            'fields' => 'id',
        ], $accessToken);

        return (string) ($profile['id'] ?? '') === (string) $account->provider_account_id;
    }

    public function revoke(string $accessToken): bool
    {
        $version = $this->settings()['graph_version'] ?? 'v22.0';

        return Http::withToken($accessToken)
            ->timeout(15)
            ->delete("https://graph.facebook.com/{$version}/me/permissions")
            ->successful();
    }
}
