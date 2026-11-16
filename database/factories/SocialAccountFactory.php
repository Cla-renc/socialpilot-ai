<?php
namespace Database\Factories;

use App\Models\Brand;
use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class SocialAccountFactory extends Factory
{
    protected $model = SocialAccount::class;

    public function definition(): array
    {
        return [
            'user_id' => function (array $attributes) {
                if (!empty($attributes['brand_id'])) {
                    return Brand::find($attributes['brand_id'])?->user_id ?? User::factory();
                }
                return User::factory();
            },
            'brand_id' => Brand::factory(),
            'provider' => 'instagram',
            'provider_account_id' => $this->faker->uuid,
            'display_name' => $this->faker->company . ' Page',
            'account_type' => 'page',
            'status' => 'connected',
            'metadata' => [],
        ];
    }
}
