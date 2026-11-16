<?php

namespace Database\Factories;

use App\Models\Brand;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class BrandFactory extends Factory
{
    protected $model = Brand::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => $this->faker->company(),
            'description' => $this->faker->sentence(),
            'category' => 'personal development',
            'audience' => 'Young adults',
            'region' => 'Kenya',
            'language' => 'en',
            'tone' => 'Bold and practical',
            'status' => 'draft',
        ];
    }
}
