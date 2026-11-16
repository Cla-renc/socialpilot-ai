<?php

namespace Tests\Feature\Api\V1;

use App\Models\Brand;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class AuthAndProfileApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_forgot_password_api_dispatches_reset_link(): void
    {
        $user = User::factory()->create([
            'email' => 'author@socialpilot.test',
        ]);

        $response = $this->postJson('/api/v1/auth/forgot-password', [
            'email' => 'author@socialpilot.test',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
            ]);
    }

    public function test_forgot_password_api_rejects_unknown_email(): void
    {
        $response = $this->postJson('/api/v1/auth/forgot-password', [
            'email' => 'unknown@socialpilot.test',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_reset_password_api_resets_password_with_valid_token(): void
    {
        $user = User::factory()->create([
            'email' => 'resetuser@socialpilot.test',
            'password' => Hash::make('OldPassword123!'),
        ]);

        $token = Password::createToken($user);

        $response = $this->postJson('/api/v1/auth/reset-password', [
            'token' => $token,
            'email' => 'resetuser@socialpilot.test',
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
            ]);

        $this->assertTrue(Hash::check('NewPassword123!', $user->fresh()->password));
        $this->assertFalse(Hash::check('OldPassword123!', $user->fresh()->password));
    }

    public function test_authenticated_user_can_update_profile_via_api(): void
    {
        $user = User::factory()->create([
            'name' => 'Original Name',
            'email' => 'original@socialpilot.test',
        ]);

        $response = $this->actingAs($user, 'sanctum')->putJson('/api/v1/user/profile', [
            'name' => 'Updated Name',
            'email' => 'updated@socialpilot.test',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Profile updated successfully.',
                'user' => [
                    'id' => $user->id,
                    'name' => 'Updated Name',
                    'email' => 'updated@socialpilot.test',
                ],
            ]);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Updated Name',
            'email' => 'updated@socialpilot.test',
        ]);
    }

    public function test_authenticated_user_can_update_password_via_api(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('CurrentSecret123!'),
        ]);

        $response = $this->actingAs($user, 'sanctum')->putJson('/api/v1/user/password', [
            'current_password' => 'CurrentSecret123!',
            'password' => 'BrandNewSecret123!',
            'password_confirmation' => 'BrandNewSecret123!',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Password updated successfully.',
            ]);

        $this->assertTrue(Hash::check('BrandNewSecret123!', $user->fresh()->password));
    }

    public function test_update_password_via_api_requires_correct_current_password(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('CurrentSecret123!'),
        ]);

        $response = $this->actingAs($user, 'sanctum')->putJson('/api/v1/user/password', [
            'current_password' => 'WrongPassword!',
            'password' => 'BrandNewSecret123!',
            'password_confirmation' => 'BrandNewSecret123!',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['current_password']);
    }

    public function test_content_generation_accepts_text_post_format(): void
    {
        $user = User::factory()->create();
        $brand = Brand::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user, 'sanctum')->postJson("/api/v1/brands/{$brand->id}/content/generate", [
            'platform' => 'linkedin',
            'objective' => 'engagement',
            'content_format' => 'text_post',
            'sync' => true,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.content_format', 'text_post');
    }
}
