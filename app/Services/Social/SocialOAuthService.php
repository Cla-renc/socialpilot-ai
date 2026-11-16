<?php

namespace App\Services\Social;

use App\Models\Brand;
use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SocialOAuthService
{
    public function __construct(private readonly SocialProviderManager $providers) {}

    public function begin(User $user, Brand $brand, string $providerKey, string $mode): array
    {
        abort_unless($brand->user_id === $user->id, 403, 'Unauthorized brand access.');
        $provider = $this->providers->provider($providerKey);
        if (! $provider->configured()) {
            throw new ProviderException('Provider credentials are not configured.');
        }

        $state = Str::random(64);
        Cache::put($this->stateKey($state), [
            'user_id' => $user->id,
            'brand_id' => $brand->id,
            'provider' => $provider->key(),
            'mode' => $mode,
        ], now()->addMinutes(10));

        return [
            'authorization_url' => $provider->authorizationUrl($state),
            'state' => $state,
        ];
    }

    public function complete(string $providerKey, ?string $state, ?string $code, ?string $providerError): array
    {
        if (! is_string($state) || $state === '') {
            throw new InvalidOAuthStateException('Missing OAuth state.');
        }

        $stateData = Cache::pull($this->stateKey($state));
        if (! is_array($stateData) || ($stateData['provider'] ?? null) !== strtolower($providerKey)) {
            throw new InvalidOAuthStateException('The OAuth state is invalid or expired.');
        }

        $brand = Brand::query()
            ->whereKey($stateData['brand_id'])
            ->where('user_id', $stateData['user_id'])
            ->first();
        if (! $brand) {
            return [
                'status' => 'error',
                'message' => 'The brand is no longer available to this account.',
                'brand_id' => $stateData['brand_id'],
                'mode' => $stateData['mode'] ?? 'web',
            ];
        }
        if ($providerError !== null || ! is_string($code) || $code === '') {
            return [
                'status' => 'error',
                'message' => 'Authorization was declined or failed at the provider.',
                'brand_id' => $brand->id,
                'brand_name' => $brand->name,
                'mode' => $stateData['mode'] ?? 'web',
            ];
        }

        $provider = $this->providers->provider($providerKey);
        try {
            $tokens = $provider->exchangeCode($code);
            $accounts = $provider->accounts($tokens['access_token']);
            $connected = DB::transaction(function () use ($accounts, $brand, $provider, $tokens): array {
                $saved = [];

                foreach ($accounts as $accountData) {
                    $providerAccountId = (string) ($accountData['id'] ?? '');
                    $accessToken = $accountData['access_token'] ?? $tokens['access_token'];
                    if ($providerAccountId === '' || ! is_string($accessToken) || $accessToken === '') {
                        throw new ProviderException('The provider returned an incomplete account profile.');
                    }

                    $account = SocialAccount::query()
                        ->where('provider', $provider->key())
                        ->where('provider_account_id', $providerAccountId)
                        ->first();
                    if ($account && (
                        $account->user_id !== $brand->user_id
                        || ($account->brand_id !== null && $account->brand_id !== $brand->id)
                    )) {
                        throw new ProviderException('This social account is already associated with another owner or brand.');
                    }

                    $metadata = array_merge(
                        $account?->metadata ?? [],
                        is_array($accountData['metadata'] ?? null) ? $accountData['metadata'] : [],
                        [
                            'username' => $accountData['username'] ?? null,
                            'connected_at' => now()->toISOString(),
                            'last_synced_at' => now()->toISOString(),
                        ]
                    );

                    if (! $account) {
                        $account = SocialAccount::query()->create([
                            'user_id' => $brand->user_id,
                            'brand_id' => $brand->id,
                            'provider' => $provider->key(),
                            'provider_account_id' => $providerAccountId,
                            'display_name' => (string) ($accountData['name'] ?? $provider->name()),
                            'account_type' => (string) ($accountData['account_type'] ?? 'account'),
                            'status' => 'connected',
                            'metadata' => $metadata,
                        ]);
                    } else {
                        $account->update([
                            'brand_id' => $brand->id,
                            'display_name' => (string) ($accountData['name'] ?? $account->display_name),
                            'account_type' => (string) ($accountData['account_type'] ?? $account->account_type),
                            'status' => 'connected',
                            'metadata' => $metadata,
                        ]);
                    }

                    $expiresIn = $accountData['expires_in'] ?? $tokens['expires_in'] ?? null;
                    $refreshToken = $accountData['refresh_token'] ?? $tokens['refresh_token'] ?? null;
                    $scopes = $accountData['scopes'] ?? [];
                    $account->token()->updateOrCreate(
                        ['social_account_id' => $account->id],
                        [
                            'provider' => $provider->key(),
                            'access_token' => $accessToken,
                            'refresh_token' => $refreshToken,
                            'expires_at' => is_int($expiresIn) && $expiresIn > 0 ? now()->addSeconds($expiresIn) : null,
                            'scopes' => $scopes !== [] ? $scopes : ($tokens['scopes'] ?? []),
                        ]
                    );
                    $saved[] = $account->display_name;
                }

                return $saved;
            });
        } catch (ProviderException $exception) {
            app(\App\Services\DashboardNotificationService::class)->notify(
                $brand->user,
                'Social account connection failed',
                'The '.$provider->name().' authorization did not complete. Check provider configuration and try again.',
                $brand->id,
                '/brands/'.$brand->id.'/social-accounts',
            );

            return [
                'status' => 'error',
                'message' => $exception->getMessage(),
                'brand_id' => $brand->id,
                'brand_name' => $brand->name,
                'mode' => $stateData['mode'] ?? 'web',
            ];
        }

        if ($connected !== []) {
            app(\App\Services\DashboardNotificationService::class)->notify(
                $brand->user,
                'Social account connected',
                implode(', ', $connected).' connected to '.$brand->name.'.',
                $brand->id,
                '/brands/'.$brand->id.'/social-accounts',
            );
        }

        return [
            'status' => 'connected',
            'brand_id' => $brand->id,
            'brand_name' => $brand->name,
            'account_names' => $connected,
            'mode' => $stateData['mode'] ?? 'web',
        ];
    }

    public function checkOrRefresh(User $user, SocialAccount $account): array
    {
        abort_unless(
            $account->user_id === $user->id
                && ($account->brand === null || $account->brand->user_id === $user->id),
            403,
            'Unauthorized social account access.'
        );
        $provider = $this->providers->provider($account->provider);
        $token = $account->token;
        if (! $token) {
            $account->update(['status' => 'connection_error']);

            return ['status' => 'connection_error', 'message' => 'No stored provider token is available. Reconnect this account.'];
        }

        try {
            if ($token->expires_at?->isPast()) {
                if (! $token->refresh_token) {
                    $account->update(['status' => 'token_expired']);

                    return ['status' => 'token_expired', 'message' => 'This provider does not offer a refresh token. Reconnect the account.'];
                }
                $refreshed = $provider->refresh($token->refresh_token);
                if (! $refreshed) {
                    $account->update(['status' => 'token_expired']);

                    return ['status' => 'token_expired', 'message' => 'This provider does not support token refresh. Reconnect the account.'];
                }
                $token->update([
                    'access_token' => $refreshed['access_token'],
                    'refresh_token' => $refreshed['refresh_token'] ?? $token->refresh_token,
                    'expires_at' => isset($refreshed['expires_in']) && is_int($refreshed['expires_in'])
                        ? now()->addSeconds($refreshed['expires_in'])
                        : $token->expires_at,
                    'scopes' => $refreshed['scopes'] ?: $token->scopes,
                ]);
                $token->refresh();
            }

            if (! $provider->isValid($account, $token->access_token)) {
                $account->update(['status' => 'connection_error']);

                return ['status' => 'connection_error', 'message' => 'The provider could not verify this account. Reconnect it.'];
            }

            $account->update([
                'status' => 'connected',
                'metadata' => array_merge($account->metadata ?? [], ['last_synced_at' => now()->toISOString()]),
            ]);

            return ['status' => 'connected', 'message' => 'Account is valid and synchronized.'];
        } catch (ProviderException) {
            $account->update(['status' => 'connection_error']);

            return ['status' => 'connection_error', 'message' => 'The provider could not verify this connection. Retry or reconnect the account.'];
        }
    }

    public function disconnect(User $user, SocialAccount $account): array
    {
        abort_unless(
            $account->user_id === $user->id
                && ($account->brand === null || $account->brand->user_id === $user->id),
            403,
            'Unauthorized social account access.'
        );
        $providerRevoked = false;
        $token = $account->token;

        if ($token) {
            try {
                $providerRevoked = $this->providers->provider($account->provider)->revoke($token->access_token);
            } catch (ConnectionException) {
                $providerRevoked = false;
            }
        }

        $account->token()->delete();
        $account->update(['status' => 'disconnected']);
        app(\App\Services\DashboardNotificationService::class)->notify(
            $user,
            'Social account disconnected',
            $account->display_name.' was disconnected from SocialPilot.',
            $account->brand_id,
            $account->brand_id ? '/brands/'.$account->brand_id.'/social-accounts' : null,
        );

        return ['provider_revoked' => $providerRevoked];
    }

    private function stateKey(string $state): string
    {
        return 'social-oauth-state:'.hash('sha256', $state);
    }
}
