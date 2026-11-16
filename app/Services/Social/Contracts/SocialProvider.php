<?php

namespace App\Services\Social\Contracts;

use App\Models\SocialAccount;

interface SocialProvider
{
    public function key(): string;

    public function name(): string;

    public function configured(): bool;

    public function authorizationUrl(string $state): string;

    public function exchangeCode(string $code): array;

    public function accounts(string $accessToken): array;

    public function refresh(string $refreshToken): ?array;

    public function isValid(SocialAccount $account, string $accessToken): bool;

    public function revoke(string $accessToken): bool;
}
