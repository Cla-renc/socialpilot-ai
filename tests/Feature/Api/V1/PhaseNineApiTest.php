<?php

namespace Tests\Feature\Api\V1;

use App\Models\Brand;
use App\Models\BrandMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class PhaseNineApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_brand_owner_can_invite_a_member_and_the_member_can_access_brand_workflows(): void
    {
        $owner = User::factory()->create();
        $memberUser = User::factory()->create([
            'email' => 'member@example.com',
        ]);
        $brand = Brand::factory()->create(['user_id' => $owner->id]);

        $invite = $this->actingAs($owner, 'sanctum')->postJson('/api/v1/brands/'.$brand->id.'/members', [
            'email' => 'member@example.com',
            'role' => 'editor',
            'status' => 'active',
        ]);

        $invite->assertStatus(201)
            ->assertJsonPath('data.role', 'editor')
            ->assertJsonPath('data.user_id', $memberUser->id);

        $this->assertDatabaseHas('brand_members', [
            'brand_id' => $brand->id,
            'user_id' => $memberUser->id,
            'role' => 'editor',
        ]);

        $list = $this->actingAs($memberUser, 'sanctum')->getJson('/api/v1/brands/'.$brand->id.'/members');

        $list->assertOk()->assertJsonPath('data.0.role', 'editor');

        $approval = $this->actingAs($memberUser, 'sanctum')->postJson('/api/v1/brands/'.$brand->id.'/approval-requests', [
            'resource_type' => 'brand_post',
            'resource_id' => 42,
            'notes' => 'Please review this draft for publishing.',
        ]);

        $approval->assertStatus(201)
            ->assertJsonPath('data.resource_type', 'brand_post')
            ->assertJsonPath('data.status', 'pending');
    }

    public function test_brand_owner_or_reviewer_can_update_an_approval_request_status(): void
    {
        $owner = User::factory()->create();
        $reviewer = User::factory()->create();
        $brand = Brand::factory()->create(['user_id' => $owner->id]);

        BrandMember::create([
            'brand_id' => $brand->id,
            'user_id' => $reviewer->id,
            'role' => 'reviewer',
            'permissions' => ['read', 'approve'],
            'status' => 'active',
            'invited_by' => $owner->id,
            'joined_at' => now(),
        ]);

        $approval = $brand->approvalRequests()->create([
            'requested_by' => $reviewer->id,
            'resource_type' => 'brand_content_draft',
            'resource_id' => 7,
            'status' => 'pending',
            'notes' => 'Awaiting review',
            'metadata' => ['platform' => 'linkedin'],
        ]);

        $response = $this->actingAs($reviewer, 'sanctum')->putJson('/api/v1/brands/'.$brand->id.'/approval-requests/'.$approval->id, [
            'status' => 'approved',
            'notes' => 'Approved for publication.',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.status', 'approved')
            ->assertJsonPath('data.approved_by', $reviewer->id);
    }

    public function test_social_oauth_does_not_claim_success_without_provider_setup(): void
    {
        // Null out social credentials so the provider is treated as not configured.
        Config::set('services.social.facebook.client_id', null);
        Config::set('services.social.facebook.client_secret', null);

        $owner = User::factory()->create();
        $brand = Brand::factory()->create(['user_id' => $owner->id]);

        $start = $this->actingAs($owner, 'sanctum')->postJson('/api/v1/brands/'.$brand->id.'/social-providers/facebook/authorize');

        $start->assertStatus(409)
            ->assertJsonPath('error.code', 'configuration_required');
        $this->assertDatabaseMissing('social_accounts', [
            'brand_id' => $brand->id,
            'status' => 'connected',
        ]);
    }

    public function test_oauth_callback_rejects_an_invalid_state(): void
    {
        $owner = User::factory()->create();
        $brand = Brand::factory()->create(['user_id' => $owner->id]);

        $this->actingAs($owner, 'sanctum')
            ->getJson('/social/oauth/facebook/callback?state=attacker-state&code=sample')
            ->assertBadRequest();

        $this->assertDatabaseCount('social_accounts', 0);
    }
}
