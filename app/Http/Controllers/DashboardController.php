<?php

namespace App\Http\Controllers;

use App\Services\DashboardDataService;
use App\Services\DashboardNotificationService;
use App\Services\SocialProviderCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(
        private readonly DashboardDataService $dashboard,
        private readonly DashboardNotificationService $notifications,
        private readonly SocialProviderCatalog $providers,
    ) {}

    public function index(Request $request): View
    {
        $validated = $request->validate(['brand_id' => ['nullable', 'integer']]);
        $user = $request->user();
        $brand = $this->dashboard->resolveBrand($user, $validated['brand_id'] ?? null);
        $accounts = $this->dashboard->socialAccounts($user, $brand);
        $connectedProviders = collect($accounts)->pluck('provider')->all();

        return view('dashboard', [
            'brands' => $this->dashboard->brands($user),
            'selectedBrand' => $brand,
            'metrics' => $this->dashboard->overview($user, $brand),
            'activity' => $this->dashboard->contentActivity($user, $brand, 12),
            'calendar' => $this->dashboard->publishingCalendar($user, $brand, now(), now()->addDays(14)->endOfDay(), 10),
            'accounts' => $accounts,
            'unconnectedProviders' => collect($this->providers->statuses())
                ->reject(fn (array $provider) => in_array($provider['provider'], $connectedProviders, true))
                ->values(),
            'agents' => $this->dashboard->agentActivity($user, $brand),
            'notifications' => $this->notifications->recent($user, brandId: $brand?->id),
        ]);
    }

    public function calendar(Request $request): View
    {
        $validated = $request->validate(['brand_id' => ['nullable', 'integer']]);
        $user = $request->user();
        $brand = $this->dashboard->resolveBrand($user, $validated['brand_id'] ?? null);

        return view('dashboard-calendar', [
            'brands' => $this->dashboard->brands($user),
            'selectedBrand' => $brand,
            'calendar' => $this->dashboard->publishingCalendar(
                $user,
                $brand,
                now(),
                now()->addDays(90)->endOfDay(),
                50
            ),
        ]);
    }

    public function markNotificationRead(Request $request, string $id): RedirectResponse
    {
        $this->notifications->markRead($request->user(), $id);

        return back()->with('success', 'Notification marked as read.');
    }
}
