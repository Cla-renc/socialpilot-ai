<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\SocialAccountResource;
use App\Models\Brand;
use App\Models\SocialAccount;
use App\Services\Social\SocialOAuthService;
use App\Services\SocialProviderCatalog;
use Illuminate\Http\Request;

class SocialAccountController extends Controller
{
    public function index(Request $request)
    {
        return response()->json([
            'data' => SocialAccount::query()
                ->where('user_id', $request->user()->id)
                ->where(function ($query) use ($request): void {
                    $query->whereNull('brand_id')
                        ->orWhereHas('brand', fn ($brands) => $brands->where('user_id', $request->user()->id));
                })
                ->with(['brand:id,name', 'token:id,social_account_id,expires_at'])
                ->latest()
                ->get()
                ->map(fn (SocialAccount $account) => SocialAccountResource::make($account)->resolve($request)),
        ]);
    }

    public function forBrand(Request $request, Brand $brand)
    {
        $this->authorizeBrand($request, $brand);

        return response()->json([
            'data' => $brand->socialAccounts()
                ->where('user_id', $request->user()->id)
                ->with(['brand:id,name', 'token:id,social_account_id,expires_at'])
                ->latest()
                ->get()
                ->map(fn (SocialAccount $account) => SocialAccountResource::make($account)->resolve($request)),
            'providers' => app(SocialProviderCatalog::class)->statuses(),
        ]);
    }

    public function destroy(Request $request, SocialAccount $socialAccount)
    {
        $this->authorize('delete', $socialAccount);

        $result = app(SocialOAuthService::class)->disconnect($request->user(), $socialAccount);

        return response()->json([
            'message' => 'Social account disconnected from SocialPilot.'
                .($result['provider_revoked'] ? ' Provider access was revoked.' : ' Provider-side revocation could not be confirmed.'),
            'provider_revoked' => $result['provider_revoked'],
        ]);
    }

    public function check(Request $request, SocialAccount $socialAccount)
    {
        $this->authorize('view', $socialAccount);

        return response()->json([
            'data' => app(SocialOAuthService::class)->checkOrRefresh($request->user(), $socialAccount),
        ]);
    }

    public function status(Request $request, SocialAccount $socialAccount)
    {
        $this->authorize('view', $socialAccount);

        return response()->json([
            'data' => SocialAccountResource::make($socialAccount->load(['brand:id,name', 'token:id,social_account_id,expires_at']))->resolve($request),
        ]);
    }

    private function authorizeBrand(Request $request, Brand $brand): void
    {
        abort_unless($brand->user_id === $request->user()->id, 403, 'Unauthorized brand access.');
    }

}
