<?php
namespace Database\Factories;

use App\Models\AnalyticsSnapshot;
use Illuminate\Database\Eloquent\Factories\Factory;

class AnalyticsSnapshotFactory extends Factory
{
    protected $model = AnalyticsSnapshot::class;

    public function definition(): array
    {
        return [
            'brand_id' => null,
            'social_account_id' => null,
            'platform' => 'instagram',
            'metric_type' => 'account',
            'metric_name' => 'followers',
            'metric_value' => $this->faker->randomFloat(2, 100, 10000),
            'metric_unit' => 'count',
            'reporting_period' => 'day',
            'period_start' => now()->subDay(),
            'period_end' => now(),
            'collected_at' => now(),
            'source' => 'test',
            'metadata' => [],
        ];
    }
}
