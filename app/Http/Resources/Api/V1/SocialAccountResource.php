<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SocialAccountResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $account = $this->resource;

        return [
            'id' => $account->id,
            'brand_id' => $account->brand_id,
            'brand_name' => $account->brand?->name,
            'provider' => $account->provider,
            'display_name' => $account->display_name,
            'provider_account_id' => $account->provider_account_id,
            'username' => $account->metadata['username'] ?? null,
            'account_type' => $account->account_type,
            'status' => $account->status,
            'connected_at' => $account->metadata['connected_at'] ?? $account->created_at?->toISOString(),
            'last_synced_at' => $account->metadata['last_synced_at'] ?? null,
            'token_expires_at' => $account->token?->expires_at?->toISOString(),
            'metadata' => $this->safeMetadata($account->metadata ?? []),
        ];
    }

    private function safeMetadata(array $metadata): array
    {
        $safe = [];
        foreach ($metadata as $key => $value) {
            $normalizedKey = strtolower(preg_replace('/[^a-z]/i', '', (string) $key));
            if (str_contains($normalizedKey, 'token')
                || str_contains($normalizedKey, 'secret')
                || $normalizedKey === 'authorization') {
                continue;
            }

            $safe[$key] = is_array($value) ? $this->safeMetadata($value) : $value;
        }

        return $safe;
    }
}
