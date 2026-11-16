<?php

namespace Tests\Feature\Api\V1;

use App\Models\Brand;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PhaseSixApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_generate_ai_content_with_internal_provider(): void
    {
        $user = User::factory()->create();
        $brand = Brand::factory()->create([
            'user_id' => $user->id,
            'name' => 'Northstar Labs',
            'tone' => 'clear and energetic',
            'audience' => 'founders',
        ]);

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/v1/brands/'.$brand->id.'/generate-content', [
            'provider' => 'internal',
            'platform' => 'linkedin',
            'content_type' => 'caption',
            'prompt' => 'Write a polished launch post for a product team that wants to simplify operations.',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.platform', 'linkedin')
            ->assertJsonPath('data.provider', 'internal')
            ->assertJsonStructure(['data' => ['id', 'brand_id', 'body', 'prompt', 'platform', 'provider']]);

        $this->assertDatabaseHas('brand_content_drafts', [
            'brand_id' => $brand->id,
            'user_id' => $user->id,
            'ai_provider' => 'internal',
        ]);
    }

    public function test_authenticated_user_can_store_and_list_brand_analytics(): void
    {
        $user = User::factory()->create();
        $brand = Brand::factory()->create(['user_id' => $user->id]);

        $createResponse = $this->actingAs($user, 'sanctum')->postJson('/api/v1/brands/'.$brand->id.'/analytics', [
            'metric_date' => '2026-11-09',
            'followers' => 1250,
            'reach' => 9400,
            'impressions' => 9000,
            'engagements' => 420,
            'clicks' => 160,
            'shares' => 50,
            'saves' => 38,
            'replies' => 25,
            'engagement_rate' => 0.045,
            'metadata' => ['source' => 'dashboard'],
        ]);

        $createResponse->assertStatus(201)
            ->assertJsonPath('data.brand_id', $brand->id);

        $listResponse = $this->actingAs($user, 'sanctum')->getJson('/api/v1/brands/'.$brand->id.'/analytics');

        $listResponse->assertOk()
            ->assertJsonPath('data.0.brand_id', $brand->id)
            ->assertJsonPath('data.0.engagement_rate', 0.045);
    }
}
