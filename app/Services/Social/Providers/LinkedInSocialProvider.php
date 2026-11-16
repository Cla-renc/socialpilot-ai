<?php

namespace App\Services\Social\Providers;

use App\Models\SocialAccount;
use App\Services\Social\AbstractSocialProvider;
use App\Services\Social\ProviderException;
use Illuminate\Support\Facades\Http;

class LinkedInSocialProvider extends AbstractSocialProvider
{
    public function key(): string
    {
        return 'linkedin';
    }

    public function name(): string
    {
        return 'LinkedIn';
    }

    public function authorizationUrl(string $state): string
    {
        $settings = $this->requireConfigured();

        return $this->authorizationUrlFor('https://www.linkedin.com/oauth/v2/authorization', [
            'response_type' => 'code',
            'client_id' => $settings['client_id'],
            'redirect_uri' => $settings['redirect'],
            'state' => $state,
            'scope' => implode(' ', $settings['scopes'] ?? ['openid', 'profile', 'email', 'w_member_social']),
        ]);
    }

    public function exchangeCode(string $code): array
    {
        $settings = $this->requireConfigured();
        $response = $this->postForm('https://www.linkedin.com/oauth/v2/accessToken', [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $settings['redirect'],
            'client_id' => $settings['client_id'],
            'client_secret' => $settings['client_secret'],
        ]);

        return $this->token($response);
    }

    public function accounts(string $accessToken): array
    {
        // 1. Fetch userinfo via OpenID Connect endpoint
        $profile = $this->get('https://api.linkedin.com/v2/userinfo', [], $accessToken);
        $personId = $profile['sub'] ?? null;
        if (! $personId) {
            throw new ProviderException('LinkedIn did not return an authenticated user profile.');
        }

        $accounts = [];
        $displayName = $profile['name'] ?? trim(($profile['given_name'] ?? '').' '.($profile['family_name'] ?? '')) ?: 'LinkedIn Member';

        // Member personal profile
        $accounts[] = [
            'id' => (string) $personId,
            'name' => $displayName,
            'username' => $profile['email'] ?? null,
            'account_type' => 'personal',
            'access_token' => $accessToken,
            'refresh_token' => null,
            'expires_in' => 5184000, // 60 days standard
            'scopes' => ['w_member_social', 'openid', 'profile'],
            'metadata' => [
                'avatar_url' => $profile['picture'] ?? null,
                'urn' => 'urn:li:person:'.$personId,
                'author_urn' => 'urn:li:person:'.$personId,
            ],
        ];

        // 2. Discover administered organization pages if organization permission is granted
        try {
            $orgResponse = Http::withToken($accessToken)
                ->acceptJson()
                ->timeout(10)
                ->get('https://api.linkedin.com/v2/organizationalEntityAcls', [
                    'q' => 'roleAssignee',
                    'role' => 'ADMINISTRATOR',
                    'state' => 'APPROVED',
                ]);

            if ($orgResponse->successful()) {
                $elements = $orgResponse->json('elements', []);
                foreach ($elements as $element) {
                    $orgUrn = $element['organizationalTarget'] ?? null;
                    if ($orgUrn && str_starts_with($orgUrn, 'urn:li:organization:')) {
                        $orgId = str_replace('urn:li:organization:', '', $orgUrn);
                        $accounts[] = [
                            'id' => (string) $orgId,
                            'name' => 'LinkedIn Company ('.$orgId.')',
                            'username' => null,
                            'account_type' => 'organization',
                            'access_token' => $accessToken,
                            'refresh_token' => null,
                            'expires_in' => 5184000,
                            'scopes' => ['w_organization_social'],
                            'metadata' => [
                                'urn' => $orgUrn,
                                'author_urn' => $orgUrn,
                                'is_organization' => true,
                            ],
                        ];
                    }
                }
            }
        } catch (\Throwable) {
            // Organization discovery is optional, proceed with personal member profile
        }

        return $accounts;
    }

    public function checkOrRefresh(SocialAccount $account): array
    {
        $token = $account->token;
        if (! $token) {
            return [
                'status' => 'not_connected',
                'connected' => false,
                'message' => 'No access token stored.',
            ];
        }

        // If refresh token exists and token is expired or expiring within 1 day
        if ($token->refresh_token && ($token->expires_at?->isPast() || $token->expires_at?->diffInDays(now()) < 1)) {
            try {
                $settings = $this->requireConfigured();
                $response = $this->postForm('https://www.linkedin.com/oauth/v2/accessToken', [
                    'grant_type' => 'refresh_token',
                    'refresh_token' => $token->refresh_token,
                    'client_id' => $settings['client_id'],
                    'client_secret' => $settings['client_secret'],
                ]);

                $fresh = $this->token($response);
                $token->update([
                    'access_token' => $fresh['access_token'],
                    'refresh_token' => $fresh['refresh_token'] ?? $token->refresh_token,
                    'expires_at' => $fresh['expires_at'],
                ]);
            } catch (\Throwable $e) {
                $account->update(['status' => 'token_expired']);
                return [
                    'status' => 'token_expired',
                    'connected' => false,
                    'message' => 'LinkedIn token refresh failed: '.$e->getMessage(),
                ];
            }
        }

        // Verify token with userinfo call
        try {
            $this->get('https://api.linkedin.com/v2/userinfo', [], $token->access_token);
            $account->update(['status' => 'connected']);
            return [
                'status' => 'connected',
                'connected' => true,
                'message' => 'LinkedIn connection active.',
            ];
        } catch (\Throwable) {
            $account->update(['status' => 'connection_error']);
            return [
                'status' => 'connection_error',
                'connected' => false,
                'message' => 'LinkedIn credentials invalid or revoked.',
            ];
        }
    }

    public function revoke(string $accessToken): bool
    {
        try {
            $settings = $this->requireConfigured();
            $resp = Http::asForm()->timeout(10)->post('https://www.linkedin.com/oauth/v2/revoke', [
                'client_id' => $settings['client_id'],
                'client_secret' => $settings['client_secret'],
                'token' => $accessToken,
            ]);

            return $resp->successful();
        } catch (\Throwable) {
            return false;
        }
    }

    public function refresh(string $refreshToken): ?array
    {
        try {
            $settings = $this->requireConfigured();
            $response = $this->postForm('https://www.linkedin.com/oauth/v2/accessToken', [
                'grant_type' => 'refresh_token',
                'refresh_token' => $refreshToken,
                'client_id' => $settings['client_id'],
                'client_secret' => $settings['client_secret'],
            ]);

            return $this->token($response);
        } catch (\Throwable) {
            return null;
        }
    }

    public function isValid(SocialAccount $account, string $accessToken): bool
    {
        try {
            $profile = $this->get('https://api.linkedin.com/v2/userinfo', [], $accessToken);

            return isset($profile['sub']) && (string) $profile['sub'] === (string) $account->provider_account_id;
        } catch (\Throwable) {
            return false;
        }
    }
}
