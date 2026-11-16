<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\SocialAccount;
use App\Services\Social\SocialOAuthService;
use App\Services\SocialProviderCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SocialAccountManagementController extends Controller
{
    public function index(Request $request, Brand $brand): View
    {
        $this->authorizeBrand($request, $brand);
        $accounts = $brand->socialAccounts()->with('token')->latest()->get();
        $providers = app(SocialProviderCatalog::class)->statuses();

        return view('brands.social-accounts', compact('brand', 'accounts', 'providers'));
    }

    public function show(Request $request, Brand $brand, SocialAccount $socialAccount): View
    {
        $this->authorizeBrand($request, $brand);
        abort_unless($socialAccount->brand_id === $brand->id && $socialAccount->user_id === $request->user()->id, 404);
        $socialAccount->load('token');

        return view('brands.social-account-details', compact('brand', 'socialAccount'));
    }

    public function destroy(Request $request, Brand $brand, SocialAccount $socialAccount): RedirectResponse
    {
        $this->authorizeBrand($request, $brand);
        abort_unless($socialAccount->brand_id === $brand->id && $socialAccount->user_id === $request->user()->id, 404);
        $this->authorize('delete', $socialAccount);
        $result = app(SocialOAuthService::class)->disconnect($request->user(), $socialAccount);

        return redirect()->route('brands.social-accounts.index', $brand)
            ->with($result['provider_revoked'] ? 'success' : 'warning', $result['provider_revoked']
                ? 'Social account disconnected and provider access revoked.'
                : 'Social account disconnected locally; provider-side revocation could not be confirmed.');
    }

    public function check(Request $request, Brand $brand, SocialAccount $socialAccount): RedirectResponse
    {
        $this->authorizeBrand($request, $brand);
        abort_unless($socialAccount->brand_id === $brand->id && $socialAccount->user_id === $request->user()->id, 404);
        $this->authorize('view', $socialAccount);
        $result = app(SocialOAuthService::class)->checkOrRefresh($request->user(), $socialAccount);

        return redirect()->route('brands.social-accounts.index', $brand)
            ->with($result['status'] === 'connected' ? 'success' : 'warning', $result['message']);
    }

    private function authorizeBrand(Request $request, Brand $brand): void
    {
        abort_unless($brand->user_id === $request->user()->id, 403, 'Unauthorized brand access.');
    }
}
