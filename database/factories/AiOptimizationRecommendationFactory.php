<?php
namespace Database\Factories;

use App\Models\AiOptimizationRecommendation;
use Illuminate\Database\Eloquent\Factories\Factory;

class AiOptimizationRecommendationFactory extends Factory
{
    protected $model = AiOptimizationRecommendation::class;

    public function definition(): array
    {
        return [
            'user_id' => null,
            'brand_id' => null,
            'platform' => 'instagram',
            'recommendation_type' => 'posting_time',
            'title' => 'Test recommendation',
            'description' => 'This is a test recommendation.',
            'supporting_metrics' => [],
            'confidence_level' => 'medium',
            'recommendation_status' => 'pending',
            'generated_at' => now(),
            'expires_at' => now()->addDays(30),
            'metadata' => [],
        ];
    }
}
