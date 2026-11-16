<?php

namespace App\Services\Social\Providers;

use App\Models\SocialAccount;
use App\Services\Social\AbstractSocialProvider;
use App\Services\Social\ProviderException;
use Illuminate\Support\Facades\Http;

class YouTubeSocialProvider extends AbstractSocialProvider
{
    public function key(): string
    {
        return 'youtube';
    }

    public function name(): string
    {
        return 'YouTube';
    }

    public function authorizationUrl(string $state): string
    {
        $settings = $this->requireConfigured();

        return $this->authorizationUrlFor('https://accounts.google.com/o/oauth2/v2/auth', [
            'client_id' => $settings['client_id'],
            'redirect_uri' => $settings['redirect'],
            'response_type' => 'code',
            'scope' => implode(' ', $settings['scopes'] ?? ['https://www.googleapis.com/auth/youtube.readonly']),
            'access_type' => 'offline',
            'include_granted_scopes' => 'true',
            'prompt' => 'consent',
            'state' => $state,
        ]);
    }

    public function exchangeCode(string $code): array
    {
        $settings = $this->requireConfigured();

        return $this->token($this->postForm('https://oauth2.googleapis.com/token', [
            'client_id' => $settings['client_id'],
            'client_secret' => $settings['client_secret'],
            'redirect_uri' => $settings['redirect'],
            'grant_type' => 'authorization_code',
            'code' => $code,
        ]));
    }

    public function accounts(string $accessToken): array
    {
        $channels = [];
        $pageToken = null;

        do {
            $query = [
                'part' => 'snippet',
                'mine' => 'true',
                'maxResults' => 50,
            ];
            if ($pageToken !== null) {
                $query['pageToken'] = $pageToken;
            }
            $response = $this->get('https://www.googleapis.com/youtube/v3/channels', $query, $accessToken);

            foreach ($response['items'] ?? [] as $item) {
                if (! is_array($item) || ! isset($item['id'], $item['snippet']['title'])) {
                    continue;
                }

                $channels[] = [
                    'id' => (string) $item['id'],
                    'name' => (string) $item['snippet']['title'],
                    'username' => isset($item['snippet']['customUrl']) ? (string) $item['snippet']['customUrl'] : null,
                    'account_type' => 'channel',
                    'access_token' => $accessToken,
                    'refresh_token' => null,
                    'expires_in' => null,
                    'scopes' => [],
                    'metadata' => ['thumbnail_url' => $item['snippet']['thumbnails']['default']['url'] ?? null],
                ];
            }

            $nextPageToken = $response['nextPageToken'] ?? null;
            $pageToken = is_string($nextPageToken) && $nextPageToken !== '' && $nextPageToken !== $pageToken
                ? $nextPageToken
                : null;
        } while ($pageToken !== null);

        if ($channels === []) {
            throw new ProviderException('Google did not return a YouTube channel for this account.');
        }

        return $channels;
    }

    public function refresh(string $refreshToken): ?array
    {
        $settings = $this->requireConfigured();

        return $this->token($this->postForm('https://oauth2.googleapis.com/token', [
            'client_id' => $settings['client_id'],
            'client_secret' => $settings['client_secret'],
            'grant_type' => 'refresh_token',
            'refresh_token' => $refreshToken,
        ]));
    }

    public function isValid(SocialAccount $account, string $accessToken): bool
    {
        $response = $this->get('https://www.googleapis.com/youtube/v3/channels', [
            'part' => 'id',
            'mine' => 'true',
        ], $accessToken);

        foreach ($response['items'] ?? [] as $item) {
            if ((string) ($item['id'] ?? '') === (string) $account->provider_account_id) {
                return true;
            }
        }

        return false;
    }

    public function revoke(string $accessToken): bool
    {
        return Http::asForm()
            ->timeout(15)
            ->post('https://oauth2.googleapis.com/revoke', ['token' => $accessToken])
            ->successful();
    }
}
