<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BrandManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_create_a_brand(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/brands', [
            'name' => 'Bruce Lee Discipline Diaries',
            'description' => 'Personal development and discipline content',
            'category' => 'discipline',
            'audience' => 'Young adults',
            'region' => 'Kenya',
            'language' => 'en',
            'tone' => 'Bold and practical',
            'brand_voice' => 'Thought-provoking',
            'writing_style' => 'Educational',
            'content_formats' => 'quotes, essays, carousels',
            'brand_colours' => '#111827, #8b5cf6',
            'publishing_frequency' => 'daily',
            'timezone' => 'UTC',
        ]);

        $response->assertRedirect('/brands');
        $this->assertDatabaseHas('brands', ['name' => 'Bruce Lee Discipline Diaries', 'user_id' => $user->id]);
        $this->assertDatabaseHas('brand_settings', ['brand_voice' => 'Thought-provoking']);
    }

    public function test_user_can_view_own_brand_list(): void
    {
        $user = User::factory()->create();
        Brand::factory()->create(['user_id' => $user->id, 'name' => 'Growth Brand']);

        $response = $this->actingAs($user)->get('/brands');

        $response->assertOk();
        $response->assertSee('Growth Brand');
    }

    public function test_owner_can_create_filter_and_update_a_complete_brand_profile(): void
    {
        Storage::fake('public');
        $owner = User::factory()->create();

        $this->actingAs($owner)->post(route('brands.store'), [
            'name' => 'Northstar',
            'industry' => 'Design',
            'niche' => 'Brand strategy',
            'website_url' => 'https://northstar.example',
            'audience' => 'Independent founders',
            'target_age_group' => '25-34',
            'audience_interests' => 'design, startups',
            'brand_voice' => 'Helpful and direct',
            'brand_personality' => 'bold, curious',
            'marketing_objectives' => ['brand_awareness'],
            'logo' => UploadedFile::fake()->createWithContent(
                'northstar.png',
                base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/Y8sAAAAASUVORK5CYII=')
            ),
        ])->assertRedirect(route('brands.index'));

        $brand = Brand::query()->where('name', 'Northstar')->firstOrFail();
        Storage::disk('public')->assertExists($brand->logo_path);
        $this->assertSame(['design', 'startups'], $brand->audience_interests);
        $this->assertSame(['bold', 'curious'], $brand->settings->brand_personality);
        $this->assertSame(['brand_awareness'], $brand->settings->marketing_objectives);

        $this->get(route('brands.index', ['search' => 'Northstar', 'status' => 'draft']))
            ->assertOk()
            ->assertSee('Northstar');

        $this->put(route('brands.update', $brand), [
            'name' => 'Northstar Studio',
            'industry' => 'Design',
            'website_url' => '',
            'status' => 'active',
            'timezone' => 'UTC',
        ])->assertRedirect(route('brands.show', $brand));

        $this->assertDatabaseHas('brands', [
            'id' => $brand->id,
            'name' => 'Northstar Studio',
            'website_url' => null,
            'status' => 'active',
        ]);
    }

    public function test_user_cannot_view_or_change_another_owners_brand(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $brand = Brand::factory()->create(['user_id' => $owner->id]);

        $this->actingAs($otherUser)
            ->get(route('brands.show', $brand))
            ->assertForbidden();

        $this->put(route('brands.update', $brand), ['name' => 'Hijacked'])
            ->assertForbidden();
    }

    public function test_owner_can_edit_settings_view_social_account_details_sort_and_delete(): void
    {
        $owner = User::factory()->create();
        $brand = Brand::factory()->create([
            'user_id' => $owner->id,
            'name' => 'Northstar',
            'niche' => 'Brand strategy',
        ]);
        $account = SocialAccount::query()->create([
            'user_id' => $owner->id,
            'brand_id' => $brand->id,
            'provider' => 'youtube',
            'provider_account_id' => 'channel-55',
            'display_name' => 'Northstar Channel',
            'status' => 'connection_error',
        ]);

        $this->actingAs($owner)
            ->get(route('brands.settings', $brand))
            ->assertOk()
            ->assertSee('Brand settings');

        $this->put(route('brands.settings.update', $brand), [
            'brand_voice' => 'Helpful and direct',
            'brand_keywords' => 'launch, growth',
        ])->assertRedirect(route('brands.settings', $brand));

        $this->assertSame('Helpful and direct', $brand->fresh()->settings->brand_voice);
        $this->assertSame(['launch', 'growth'], $brand->fresh()->settings->brand_keywords);

        $this->get(route('brands.social-accounts.show', [$brand, $account]))
            ->assertOk()
            ->assertSee('Northstar Channel')
            ->assertSee('channel-55');

        $this->get(route('brands.index', ['sort' => 'name', 'direction' => 'asc']))
            ->assertOk()
            ->assertSee('Niche')
            ->assertSee('Social accounts');

        $this->delete(route('brands.destroy', $brand))
            ->assertRedirect(route('brands.index'));
        $this->assertDatabaseMissing('brands', ['id' => $brand->id]);
    }

    public function test_web_and_mobile_api_share_brand_changes_and_user_scope(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();

        $this->actingAs($owner)->post(route('brands.store'), [
            'name' => 'Web Created',
            'industry' => 'Retail',
            'language' => 'en',
        ])->assertRedirect(route('brands.index'));

        $webBrand = Brand::query()->where('name', 'Web Created')->firstOrFail();
        $this->actingAs($owner, 'sanctum')
            ->getJson('/api/v1/brands')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Web Created');

        $this->putJson('/api/v1/brands/'.$webBrand->id, [
            'name' => 'Edited on mobile',
            'industry' => 'Retail',
        ])->assertOk();

        $this->postJson('/api/v1/brands', [
            'name' => 'Mobile Created',
            'industry' => 'Technology',
            'language' => 'en',
        ])->assertCreated();

        $this->actingAs($owner)->get(route('brands.index'))
            ->assertOk()
            ->assertSee('Edited on mobile')
            ->assertSee('Mobile Created');

        $this->delete(route('brands.destroy', $webBrand))
            ->assertRedirect(route('brands.index'));

        $this->actingAs($owner, 'sanctum')
            ->getJson('/api/v1/brands/'.$webBrand->id)
            ->assertNotFound();

        $this->actingAs($otherUser, 'sanctum')
            ->getJson('/api/v1/brands')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }
}
