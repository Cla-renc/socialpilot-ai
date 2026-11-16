<?php

namespace App\Services;

use App\Models\Brand;
use App\Models\Campaign;
use App\Models\User;

class CampaignService
{
    public function createCampaign(User $user, Brand $brand, array $data): Campaign
    {
        return Campaign::query()->create([
            'user_id' => $user->id,
            'brand_id' => $brand->id,
            'name' => $data['name'],
            'objective' => $data['objective'] ?? null,
            'status' => $data['status'] ?? 'draft',
            'start_date' => $data['start_date'] ?? now()->toDateString(),
            'end_date' => $data['end_date'] ?? null,
            'timezone' => $data['timezone'] ?? 'UTC',
            'metadata' => $data['metadata'] ?? [],
        ]);
    }
}
