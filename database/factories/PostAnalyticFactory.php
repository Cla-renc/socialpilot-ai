<?php
namespace Database\Factories;

use App\Models\Brand;
use App\Models\PostAnalytic;
use App\Models\SocialAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

class PostAnalyticFactory extends Factory
{
    protected $model = PostAnalytic::class;

    public function definition(): array
    {
        return [
            'brand_id' => function (array $attributes) {
                if (!empty($attributes['social_account_id'])) {
                    return SocialAccount::find($attributes['social_account_id'])?->brand_id ?? Brand::factory();
                }
                return Brand::factory();
            },
            'social_account_id' => SocialAccount::factory(),
            'publishing_attempt_id' => null,
            'scheduled_post_id' => null,
            'platform' => 'instagram',
            'external_post_id' => $this->faker->uuid,
            'metric_name' => 'likes',
            'metric_value' => $this->faker->randomFloat(2, 0, 1000),
            'metric_unit' => 'count',
            'reporting_period' => 'lifetime',
            'collected_at' => now(),
            'metadata' => [],
        ];
    }
}
