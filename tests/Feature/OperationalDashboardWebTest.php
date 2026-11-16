<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\BrandPost;
use App\Models\User;
use App\Notifications\DashboardEventNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OperationalDashboardWebTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_renders_and_brand_filter_is_owner_scoped(): void
    {
        $user = User::factory()->create();
        $brand = Brand::factory()->for($user)->create(['name' => 'Owned brand']);
        $otherOwnedBrand = Brand::factory()->for($user)->create(['name' => 'Second owned brand']);
        $foreignBrand = Brand::factory()->create();
        $user->notify(new DashboardEventNotification(
            'First brand update',
            'Only belongs to the first brand.',
            '/brands/'.$brand->id,
            $brand->id,
        ));
        $user->notify(new DashboardEventNotification(
            'Second brand update',
            'Only belongs to the second brand.',
            '/brands/'.$otherOwnedBrand->id,
            $otherOwnedBrand->id,
        ));

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('Operations overview')
            ->assertSee('Total brands')
            ->assertSee('Active brands')
            ->assertSee('Owned brand');

        $this->get('/dashboard?brand_id='.$brand->id)
            ->assertOk()
            ->assertSee('Owned brand')
            ->assertSee('First brand update')
            ->assertDontSee('Second brand update');

        $this->get('/dashboard?brand_id='.$foreignBrand->id)
            ->assertForbidden();
    }

    public function test_calendar_renders_only_real_scheduled_posts_and_notification_read_persists(): void
    {
        $user = User::factory()->create();
        $brand = Brand::factory()->for($user)->create();
        BrandPost::query()->create([
            'user_id' => $user->id,
            'brand_id' => $brand->id,
            'content' => 'Real calendar entry',
            'status' => 'scheduled',
            'scheduled_for' => now()->addDay(),
        ]);
        $user->notify(new DashboardEventNotification('Brand event', 'A real event.'));
        $notification = $user->notifications()->firstOrFail();

        $this->actingAs($user)
            ->get('/dashboard/calendar')
            ->assertOk()
            ->assertSee('Real calendar entry');

        $this->from('/dashboard')
            ->post('/notifications/'.$notification->id.'/read')
            ->assertRedirect('/dashboard');

        $this->assertNotNull($user->notifications()->findOrFail($notification->id)->read_at);
    }
}