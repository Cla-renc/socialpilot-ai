<?php

namespace Tests\Feature\Api\V1;

use App\Models\Brand;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PhaseFiveApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_queue_a_brand_post_for_publishing(): void
    {
        $user = User::factory()->create();
        $brand = Brand::factory()->create(['user_id' => $user->id, 'name' => 'Northstar Labs']);

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/v1/brands/'.$brand->id.'/posts', [
            'provider' => 'internal',
            'platform' => 'linkedin',
            'content' => 'Launching a calmer product process for teams who want better focus and fewer scattered priorities.',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.brand_id', $brand->id)
            ->assertJsonPath('data.platform', 'linkedin')
            ->assertJsonPath('data.status', 'published');

        $this->assertDatabaseHas('brand_posts', [
            'brand_id' => $brand->id,
            'user_id' => $user->id,
            'platform' => 'linkedin',
            'status' => 'published',
        ]);
    }

    public function test_authenticated_user_can_list_brand_posts(): void
    {
        $user = User::factory()->create();
        $brand = Brand::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user, 'sanctum')->postJson('/api/v1/brands/'.$brand->id.'/posts', [
            'provider' => 'internal',
            'platform' => 'x',
            'content' => 'A more disciplined publishing rhythm helps teams move faster without adding chaos.',
        ]);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/v1/brands/'.$brand->id.'/posts');

        $response->assertOk()
            ->assertJsonPath('data.0.platform', 'x');
    }
}
