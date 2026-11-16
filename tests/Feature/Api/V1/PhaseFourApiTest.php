<?php

namespace Tests\Feature\Api\V1;

use App\Models\Brand;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PhaseFourApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_generate_a_content_draft_for_a_brand(): void
    {
        $user = User::factory()->create();
        $brand = Brand::factory()->create([
            'user_id' => $user->id,
            'name' => 'Northstar Labs',
            'tone' => 'confident and practical',
            'audience' => 'startup founders',
        ]);

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/v1/brands/'.$brand->id.'/content-drafts', [
            'platform' => 'linkedin',
            'content_type' => 'caption',
            'prompt' => 'Create a post about building a calmer launch plan for product teams.',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.platform', 'linkedin')
            ->assertJsonPath('data.brand_id', $brand->id)
            ->assertJsonStructure(['data' => ['id', 'body', 'prompt', 'platform']]);

        $this->assertDatabaseHas('brand_content_drafts', [
            'brand_id' => $brand->id,
            'user_id' => $user->id,
            'platform' => 'linkedin',
        ]);
    }

    public function test_client_submitted_social_tokens_are_not_accepted_as_a_connection(): void
    {
        $user = User::factory()->create();
        $brand = Brand::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/v1/brands/'.$brand->id.'/social-accounts', [
            'provider' => 'linkedin',
            'display_name' => 'Northstar LinkedIn Page',
            'account_type' => 'page',
            'provider_account_id' => 'linkedin-page-001',
            'metadata' => ['scopes' => ['w_organization_social']],
            'access_token' => 'mock-access-token',
        ]);

        $response->assertStatus(409)
            ->assertJsonPath('error.code', 'oauth_required');

        $this->assertDatabaseMissing('social_accounts', [
            'brand_id' => $brand->id,
            'user_id' => $user->id,
            'provider' => 'linkedin',
        ]);
    }
}
