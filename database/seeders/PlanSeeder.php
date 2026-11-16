<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'name' => 'Free',
                'slug' => 'free',
                'description' => 'For individuals testing the SocialPilot AI workflow.',
                'price_monthly' => 0,
                'brand_limit' => 1,
                'social_account_limit' => 2,
                'ai_generation_limit' => 50,
                'status' => 'active',
            ],
            [
                'name' => 'Starter',
                'slug' => 'starter',
                'description' => 'For new creators and small teams launching consistent campaigns.',
                'price_monthly' => 29,
                'brand_limit' => 3,
                'social_account_limit' => 6,
                'ai_generation_limit' => 250,
                'status' => 'active',
            ],
            [
                'name' => 'Professional',
                'slug' => 'professional',
                'description' => 'For brands running daily content and multi-channel publishing.',
                'price_monthly' => 79,
                'brand_limit' => 10,
                'social_account_limit' => 25,
                'ai_generation_limit' => 1200,
                'status' => 'active',
            ],
            [
                'name' => 'Enterprise',
                'slug' => 'enterprise',
                'description' => 'For teams with advanced automation, review workflows and governance.',
                'price_monthly' => 199,
                'brand_limit' => 50,
                'social_account_limit' => 100,
                'ai_generation_limit' => 5000,
                'status' => 'active',
            ],
        ];

        foreach ($plans as $plan) {
            Plan::query()->updateOrCreate(
                ['slug' => $plan['slug']],
                $plan
            );
        }
    }
}
