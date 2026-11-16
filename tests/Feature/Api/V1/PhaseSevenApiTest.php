<?php

namespace Tests\Feature\Api\V1;

use App\Models\Brand;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PhaseSevenApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_create_and_list_campaigns(): void
    {
        $user = User::factory()->create();
        $brand = Brand::factory()->create(['user_id' => $user->id]);

        $create = $this->actingAs($user, 'sanctum')->postJson('/api/v1/brands/'.$brand->id.'/campaigns', [
            'name' => 'Q4 Growth Sprint',
            'objective' => 'Increase awareness around product launches',
            'status' => 'active',
            'start_date' => '2026-11-15',
            'end_date' => '2026-11-30',
            'timezone' => 'UTC',
            'metadata' => ['goal' => 'reach 20k'],
        ]);

        $create->assertStatus(201)
            ->assertJsonPath('data.name', 'Q4 Growth Sprint')
            ->assertJsonPath('data.brand_id', $brand->id);

        $list = $this->actingAs($user, 'sanctum')->getJson('/api/v1/brands/'.$brand->id.'/campaigns');

        $list->assertOk()
            ->assertJsonPath('data.0.name', 'Q4 Growth Sprint');
    }

    public function test_campaigns_are_scoped_to_the_user_brand_owner(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $brand = Brand::factory()->create(['user_id' => $owner->id]);

        $this->actingAs($otherUser, 'sanctum')->getJson('/api/v1/brands/'.$brand->id.'/campaigns')
            ->assertStatus(403);
    }
}
