<?php

namespace App\Services\Social;

use App\Models\SocialAccount;
use App\Services\Social\Contracts\SocialProvider;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

abstract class AbstractSocialProvider implements SocialProvider
{
    protected function settings(): array
    {
        return config('services.social.'.$this->key(), []);
    }

    public function configured(): bool
    {
        $settings = $this->settings();

        return filled($settings['client_id'] ?? null)
            && filled($settings['client_secret'] ?? null)
            && filled($settings['redirect'] ?? null);
    }

    protected function requireConfigured(): array
    {
        if (! $this->configured()) {
            throw new ProviderException('This provider is not configured.');
        }

        return $this->settings();
    }

    protected function authorizationUrlFor(string $url, array $parameters): string
    {
        return $url.'?'.http_build_query($parameters, '', '&', PHP_QUERY_RFC3986);
    }

    protected function get(string $url, array $query = [], ?string $accessToken = null): array
    {
        $request = Http::acceptJson()->timeout(20);
        if ($accessToken !== null) {
            $request = $request->withToken($accessToken);
        }

        try {
            return $this->responseData($request->get($url, $query));
        } catch (ConnectionException) {
            throw new ProviderException('The social provider could not be reached. Try again later.');
        }
    }

    protected function postForm(string $url, array $parameters): array
    {
        try {
            return $this->responseData(
                Http::asForm()->acceptJson()->timeout(20)->post($url, $parameters)
            );
        } catch (ConnectionException) {
            throw new ProviderException('The social provider could not be reached. Try again later.');
        }
    }

    protected function postJson(string $url, array $parameters, ?string $accessToken = null): array
    {
        $request = Http::acceptJson()->timeout(20);
        if ($accessToken !== null) {
            $request = $request->withToken($accessToken);
        }

        try {
            return $this->responseData($request->post($url, $parameters));
        } catch (ConnectionException) {
            throw new ProviderException('The social provider could not be reached. Try again later.');
        }
    }

    protected function responseData(Response $response): array
    {
        $data = $response->json();
        if (! $response->successful() || ! is_array($data)) {
            throw new ProviderException('The social provider request failed. Check provider configuration and permissions.');
        }

        $error = $data['error'] ?? null;
        $providerSuccess = is_array($error) && ($error['code'] ?? null) === 'ok';
        if ($error !== null && $error !== false && $error !== '' && ! $providerSuccess) {
            throw new ProviderException('The social provider rejected the request. Check provider configuration and permissions.');
        }

        return $data;
    }

    protected function token(array $response): array
    {
        $accessToken = $response['access_token'] ?? null;
        if (! is_string($accessToken) || $accessToken === '') {
            throw new ProviderException('The provider did not return an access token.');
        }

        $expiresIn = filter_var($response['expires_in'] ?? null, FILTER_VALIDATE_INT);
        $scope = $response['scope'] ?? $response['scopes'] ?? [];
        if (is_string($scope)) {
            $scope = preg_split('/[\s,]+/', trim($scope), -1, PREG_SPLIT_NO_EMPTY);
        }

        return [
            'access_token' => $accessToken,
            'refresh_token' => is_string($response['refresh_token'] ?? null) ? $response['refresh_token'] : null,
            'expires_in' => $expiresIn === false ? null : $expiresIn,
            'scopes' => is_array($scope) ? array_values(array_filter($scope, 'is_string')) : [],
        ];
    }

    protected function apiBase(): string
    {
        return rtrim((string) ($this->settings()['api_base'] ?? ''), '/');
    }

    protected function jsonRevoke(string $url, array $payload): bool
    {
        return Http::acceptJson()->timeout(15)->post($url, $payload)->successful();
    }

    abstract public function isValid(SocialAccount $account, string $accessToken): bool;
}
