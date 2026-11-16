<?php

namespace App\Services\Analytics\Providers;

use App\Models\SocialAccount;
use Illuminate\Support\Facades\Log;

abstract class AbstractAnalyticsProvider implements SocialAnalyticsProviderInterface
{
    /**
     * Retrieve the decrypted OAuth access token for the given account.
     */
    protected function getAccessToken(SocialAccount $account): ?string
    {
        return $account->token?->access_token;
    }

    /**
     * Helper to redact sensitive tokens/headers from debug logs or metadata.
     */
    protected function redactSensitiveData(array $data): array
    {
        $sensitiveKeys = ['access_token', 'refresh_token', 'client_secret', 'authorization', 'bearer'];
        array_walk_recursive($data, function (&$value, $key) use ($sensitiveKeys) {
            if (is_string($key) && in_array(strtolower($key), $sensitiveKeys, true)) {
                $value = '[REDACTED]';
            }
        });

        return $data;
    }

    /**
     * Build standard normalized response array.
     */
    protected function standardResult(
        bool $success,
        array $data = [],
        ?string $errorCode = null,
        ?string $errorMessage = null,
        array $metadata = []
    ): array {
        return [
            'platform'      => $this->platform(),
            'success'       => $success,
            'data'          => $data,
            'error_code'    => $errorCode,
            'error_message' => $errorMessage,
            'metadata'      => $this->redactSensitiveData($metadata),
            'timestamp'     => now()->toIso8601String(),
        ];
    }

    /**
     * Check if the account token is present and not expired.
     */
    protected function checkTokenHealth(SocialAccount $account, array $requiredScopes = []): array
    {
        if ($account->status === 'token_expired' || ($account->token?->expires_at && $account->token->expires_at->isPast())) {
            return [
                'valid'          => false,
                'scopes_present' => $account->token?->scopes ?? [],
                'scopes_missing' => $requiredScopes,
                'reason'         => 'OAuth access token has expired. Please reauthorize your account.',
                'error_code'     => 'TOKEN_EXPIRED',
            ];
        }

        if ($account->status !== 'connected') {
            return [
                'valid'          => false,
                'scopes_present' => $account->token?->scopes ?? [],
                'scopes_missing' => $requiredScopes,
                'reason'         => "Social account is not connected (current status: {$account->status}).",
                'error_code'     => 'ACCOUNT_DISCONNECTED',
            ];
        }

        $token = $this->getAccessToken($account);
        if (empty($token)) {
            return [
                'valid'          => false,
                'scopes_present' => [],
                'scopes_missing' => $requiredScopes,
                'reason'         => 'No OAuth access token found for account.',
                'error_code'     => 'TOKEN_MISSING',
            ];
        }

        $presentScopes = (array) ($account->token?->scopes ?? []);
        $missingScopes = [];
        foreach ($requiredScopes as $scope) {
            if (!in_array($scope, $presentScopes, true)) {
                $missingScopes[] = $scope;
            }
        }

        if (!empty($missingScopes)) {
            return [
                'valid'          => false,
                'scopes_present' => $presentScopes,
                'scopes_missing' => $missingScopes,
                'reason'         => 'Missing required platform analytics permissions: ' . implode(', ', $missingScopes),
                'error_code'     => 'PERMISSION_REQUIRED',
            ];
        }

        return [
            'valid'          => true,
            'scopes_present' => $presentScopes,
            'scopes_missing' => [],
            'reason'         => null,
            'error_code'     => null,
        ];
    }
}
